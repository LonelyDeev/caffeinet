<?php

namespace App\Services\Orders;

use App\Enums\CoffeenetStatus;
use App\Enums\OrderStatus;
use App\Models\Coffeenet;
use App\Models\Order;
use App\Models\OrderBroadcast;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Chat\ChatService;
use App\Services\Finance\SettlementService;
use App\Services\Notifications\NotificationService;
use App\Services\Settings\SettingsService;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * موتور تخصیص سفارش (فاز ۶ / به‌روز فاز ۱۱).
 *
 * چرخه (فاز ۱۱ — اتصال اول، پرداخت بعد):
 *   ثبت مشتری → broadcasting (۶۰ ثانیه) →
 *   ├─ اولین اپراتور/کافی‌نتِ قبول‌کننده → accepted (قفل اتمیک)
 *   └─ پایان مهلت → queued (صف تعیین‌تکلیف + پیامک) یا ری‌پخش (طبق تنظیمات)
 *   سپس: accepted → مشتری پرداخت → paid → in_progress → …
 *
 * تخصیص دستی ادمین: queued/broadcasting → accepted به کافی‌نت انتخابی.
 *
 * انقضای پخش به‌صورت «تنبل» هم بررسی می‌شود (هر بار که لیست پخش/جزئیات
 * خوانده می‌شود expireStale فراخوانی می‌شود) تا حتی بدون cron دقیق باشد.
 */
class OrderAssignmentService
{
    /** حداکثر دفعات ری‌پخش خودکار (وقتی assign_after_timeout=rebroadcast) */
    public const MAX_BROADCAST_ATTEMPTS = 3;

    public function __construct(
        protected SettingsService $settings,
        protected SmsManager $sms,
        protected ChatService $chat,
        protected NotificationService $notifications,
        protected SettlementService $settlement,
    ) {}

    /* ================================================================== */
    /* ۱) شروع/تکرر پخش                                                    */
    /* ================================================================== */

    /**
     * سفارش (یا درخواست تازه ثبت‌شدهٔ مشتری — فاز ۱۱) را بین کافی‌نت‌های واجد شرایط پخش می‌کند.
     *
     * @param  string  $note  یادداشت تاریخچه (مبنا: ثبت درخواست / پرداخت legacy / ری‌پخش دستی)
     * @return Order سفارش با وضعیت broadcasting
     */
    public function startBroadcast(Order $order, ?User $actor = null, string $note = 'شروع پخش سفارش'): Order
    {
        return DB::transaction(function () use ($order, $actor, $note) {
            $locked = $this->lockOrder($order);

            if (! in_array(OrderStatus::Broadcasting, $locked->status->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'status' => ['این سفارش در وضعیت قابل پخش نیست (وضعیت فعلی: '.$locked->status->label().').'],
                ]);
            }

            $targets = $this->targetCoffeenets($locked);
            $timeout = max(15, (int) $this->settings->get('orders.broadcast_timeout', 60));
            $attempts = (int) $locked->broadcast_attempts + 1;

            // رکوردهای پخش (idempotent — unique(order_id, coffeenet_id))
            $now = now();
            foreach ($targets as $coffeenet) {
                OrderBroadcast::query()->firstOrCreate(
                    ['order_id' => $locked->id, 'coffeenet_id' => $coffeenet->id],
                    ['sent_at' => $now],
                );
            }

            $from = $locked->status;

            $locked->forceFill([
                'status' => OrderStatus::Broadcasting,
                'broadcast_expires_at' => $now->copy()->addSeconds($timeout),
                'broadcast_attempts' => $attempts,
                'queued_at' => null,
            ])->save();

            $this->history($locked, $from, OrderStatus::Broadcasting, $actor?->id, $note.
                ' — گیرنده‌ها: '.fa_digits((string) $targets->count()).' کافی‌نت، مهلت: '.fa_digits((string) $timeout).' ثانیه');

            AuditLogger::log(
                'orders.broadcast_started',
                $locked,
                null,
                ['attempts' => $attempts, 'targets' => $targets->pluck('id')->all(), 'timeout' => $timeout],
                'پخش سفارش '.$locked->order_number.' (کوشش '.$attempts.')'
            );

            return $locked->refresh();
        });
    }

    /** لیست کافی‌نت‌های هدف طبق تنظیم scope (all | province | city) */
    public function targetCoffeenets(Order $order): Collection
    {
        $scope = (string) $this->settings->get('orders.broadcast_scope', 'all');

        $query = Coffeenet::query()
            ->where('status', CoffeenetStatus::Approved->value)
            ->select(['id', 'name', 'province_id', 'city_id']);

        if ($scope !== 'all') {
            $customer = $order->customer()->select(['id', 'province_id', 'city_id'])->first();

            if ($customer?->{"{$scope}_id"}) {
                $query->where("{$scope}_id", $customer->{"{$scope}_id"});
            }
        }

        $targets = $query->orderBy('id')->get();

        if ($targets->isEmpty()) {
            throw ValidationException::withMessages([
                'broadcast' => ['هیچ کافی‌نتِ فعالی برای پخش این سفارش یافت نشد؛ سفارش مستقیم به صف تعیین‌تکلیف منتقل می‌شود.'],
            ]);
        }

        return $targets;
    }

    /* ================================================================== */
    /* ۲) پذیرش اتمیک                                                      */
    /* ================================================================== */

    /**
     * پذیرش سفارش — اولین درندهٔ قفل را می‌گیرد (اتمیک).
     *
     * فاز ۱۱: اپراتورها هم می‌توانند مستقیم درخواست را بپذیرند؛
     * در این حالت سفارش به همان اپراتور سپرده می‌شود (operator_id)
     * و مشتری برای پرداخت به او وصل می‌شود.
     *
     * @param  bool  $manual  تخصیص دستی ادمین (بدون رکورد پخشِ الزامی)
     * @param  ?User  $operator  اپراتور قبول‌کننده (پذیرش از پنل اپراتور)
     */
    public function accept(Order $order, Coffeenet $coffeenet, ?User $actor = null, bool $manual = false, string $note = '', ?User $operator = null): Order
    {
        if ($coffeenet->status !== CoffeenetStatus::Approved) {
            throw ValidationException::withMessages([
                'coffeenet' => ['وضعیت این کافی‌نت فعال نیست.'],
            ]);
        }

        if ($operator && (int) $operator->id !== (int) ($actor?->id ?? 0)) {
            // در تخصیص دستی، ادمین اپراتورِ هدف را انتخاب می‌کند؛
            // در پذیرش خودکار، اپراتور همان کاربر اقدام‌کننده است.
            if (! $manual) {
                $operator = null;
            } else {
                $isMember = \App\Models\StaffAssignment::query()
                    ->where('coffeenet_id', $coffeenet->id)
                    ->where('user_id', $operator->id)
                    ->where('is_active', true)
                    ->exists();

                if (! $isMember) {
                    throw ValidationException::withMessages([
                        'operator_id' => ['اپراتور انتخابی عضو فعال کافی‌نت «'.$coffeenet->name.'» نیست.'],
                    ]);
                }
            }
        }

        $order = DB::transaction(function () use ($order, $coffeenet, $actor, $manual, $note, $operator) {
            $locked = $this->lockOrder($order);

            if (! in_array(OrderStatus::Accepted, $locked->status->allowedTransitions(), true)) {
                $winner = $locked->coffeenet_id && (int) $locked->coffeenet_id !== (int) $coffeenet->id;

                throw ValidationException::withMessages([
                    'status' => [$winner
                        ? 'این سفارش لحظاتی پیش توسط کافی‌نت «'.$locked->coffeenet?->name.'» پذیرفته شد.'
                        : 'این سفارش در وضعیت قابل پذیرش نیست (وضعیت فعلی: '.$locked->status->label().').'],
                ]);
            }

            // پخشِ فعال: فقط کافی‌نت‌های گیرندهٔ رکورد پخش حق پذیرش دارند
            if (! $manual && $locked->status === OrderStatus::Broadcasting) {
                $hasBroadcast = OrderBroadcast::query()
                    ->where('order_id', $locked->id)
                    ->where('coffeenet_id', $coffeenet->id)
                    ->exists();

                if (! $hasBroadcast) {
                    throw ValidationException::withMessages([
                        'broadcast' => ['این سفارش به کافی‌نت شما پخش نشده است.'],
                    ]);
                }
            }

            $from = $locked->status;

            $locked->forceFill([
                'status' => OrderStatus::Accepted,
                'coffeenet_id' => $coffeenet->id,
                'operator_id' => $operator?->id,
                'accepted_at' => now(),
                'broadcast_expires_at' => null,
            ])->save();

            $operatorName = $operator ? trim(($operator->name ?? '').' '.($operator->family ?? '')) : null;

            $this->history($locked, $from, OrderStatus::Accepted, $actor?->id, $note ?: ($manual
                ? 'تخصیص دستی به کافی‌نت «'.$coffeenet->name.'»'
                : ($operatorName
                    ? 'پذیرش توسط اپراتور «'.$operatorName.'» از کافی‌نت «'.$coffeenet->name.'»'
                    : 'پذیرش توسط کافی‌نت «'.$coffeenet->name.'»')));

            AuditLogger::log(
                $operator ? 'orders.accepted_by_operator' : ($manual ? 'orders.manual_assigned' : 'orders.accepted'),
                $locked,
                ['status' => $from->value, 'coffeenet_id' => $locked->getOriginal('coffeenet_id')],
                ['status' => OrderStatus::Accepted->value, 'coffeenet_id' => $coffeenet->id, 'operator_id' => $operator?->id],
                ($operator ? 'پذیرش توسط اپراتور' : ($manual ? 'تخصیص دستی' : 'پذیرش')).' سفارش '.$locked->order_number.' — کافی‌نت «'.$coffeenet->name.'»'.($operatorName ? ' / اپراتور «'.$operatorName.'»' : '')
            );

            return $locked;
        });

        $operatorName = $operator ? trim(($operator->name ?? '').' '.($operator->family ?? '')) : null;

        // پیامک پذیرش — پترن‌محور (درخواست بازخوردی ۶-۶)
        try {
            $mobile = $order->customer()->value('mobile');
            if ($mobile) {
                $this->sms->sendTemplate(
                    $mobile,
                    'order.accepted',
                    [
                        'order_number' => $order->order_number,
                        'accepted_by' => $operatorName
                            ? 'اپراتور «'.$operatorName.'» از کافی‌نت «'.$coffeenet->name.'»'
                            : 'کافی‌نت «'.$coffeenet->name.'»',
                    ],
                    $operatorName
                        ? 'درخواست '.$order->order_number.' شما توسط اپراتور «'.$operatorName.'» از کافی‌نت «'.$coffeenet->name.'» پذیرفته شد؛ برای شروع کار، پرداخت را در اپ انجام دهید. کافی‌نت آنلاین'
                        : 'درخواست '.$order->order_number.' شما توسط کافی‌نت «'.$coffeenet->name.'» پذیرفته شد؛ برای شروع کار، پرداخت را در اپ انجام دهید. کافی‌نت آنلاین'
                );
            }
        } catch (\Throwable) {
            // پیامک پذیرش را نمی‌شکند
        }

        // اعلان درون‌برنامه‌ای به مشتری (فاز ۱۰) + پوش دستگاه در صورت آفلاین بودن (v25)
        $this->notifications->tryNotifyEvent(
            $order->customer,
            'order.accepted_customer',
            [
                'order' => $order->order_number,
                'acceptor' => $operatorName ? 'اپراتور «'.$operatorName.'» از کافی‌نت «'.$coffeenet->name.'»' : 'کافی‌نت «'.$coffeenet->name.'»',
            ],
            ['order_id' => $order->id, 'order_number' => $order->order_number],
        );

        // پیام سیستمی پذیرش در گفتگوی سفارش (فاز ۷)
        $this->chat->systemMessage(
            $order,
            $operatorName
                ? 'اپراتور «'.$operatorName.'» از کافی‌نت «'.$coffeenet->name.'» درخواست شما را پذیرفت؛ گفتگوی شما همین‌جاست و پس از پرداخت، کار آغاز می‌شود.'
                : 'درخواست شما توسط کافی‌نت «'.$coffeenet->name.'» پذیرفته شد؛ گفتگوی شما با اپراتور همین‌جا برقرار است و پس از پرداخت، کار آغاز می‌شود.'
        );

        return $order->refresh();
    }

    /* ================================================================== */
    /* ۳) انقضای مهلت پخش (تنبل + زمان‌بندی‌شده)                            */
    /* ================================================================== */

    /**
     * سفارش‌های broadcasting منقضی‌شده را تعیین‌تکلیف می‌کند:
     *  - rebroadcast فعال و کوشش < سقف → پخش مجدد با مهلت تازه
     *  - در غیر این صورت → queued + پیامک به مشتری
     *
     * @return int تعداد سفارش‌های تعیین‌تکلیف‌شده
     */
    public function expireStale(int $limit = 50): int
    {
        $staleIds = Order::query()
            ->where('status', OrderStatus::Broadcasting->value)
            ->whereNotNull('broadcast_expires_at')
            ->where('broadcast_expires_at', '<=', now())
            ->limit($limit)
            ->pluck('id');

        $processed = 0;

        foreach ($staleIds as $orderId) {
            $processed += $this->resolveExpired($orderId) ? 1 : 0;
        }

        return $processed;
    }

    protected function resolveExpired(int $orderId): bool
    {
        try {
            $outcome = DB::transaction(function () use ($orderId) {
                /** @var Order|null $locked */
                $locked = Order::query()->whereKey($orderId)->lockForUpdate()->first();

                if (! $locked || $locked->status !== OrderStatus::Broadcasting
                    || ! $locked->broadcast_expires_at || $locked->broadcast_expires_at->isFuture()) {
                    return null; // هم‌زمانی: دیگری تعیین‌تکلیف کرده
                }

                $rebroadcast = (string) $this->settings->get('orders.assign_after_timeout', 'manual') === 'rebroadcast'
                    && (int) $locked->broadcast_attempts < self::MAX_BROADCAST_ATTEMPTS;

                if ($rebroadcast) {
                    $timeout = max(15, (int) $this->settings->get('orders.broadcast_timeout', 60));

                    $locked->forceFill([
                        'broadcast_expires_at' => now()->addSeconds($timeout),
                        'broadcast_attempts' => (int) $locked->broadcast_attempts + 1,
                    ])->save();

                    $this->history($locked, OrderStatus::Broadcasting, OrderStatus::Broadcasting, null,
                        'پایان مهلت بدون پذیرش — پخش مجدد خودکار (کوشش '.fa_digits((string) $locked->broadcast_attempts).')');

                    return 'rebroadcast';
                }

                $locked->forceFill([
                    'status' => OrderStatus::Queued,
                    'queued_at' => now(),
                    'broadcast_expires_at' => null,
                ])->save();

                $this->history($locked, OrderStatus::Broadcasting, OrderStatus::Queued, null,
                    'پایان مهلت پخش بدون پذیرش — انتقال به صف تعیین‌تکلیف دستی');

                AuditLogger::log(
                    'orders.queued',
                    $locked,
                    ['status' => OrderStatus::Broadcasting->value],
                    ['status' => OrderStatus::Queued->value, 'attempts' => $locked->broadcast_attempts],
                    'سفارش '.$locked->order_number.' بعد از مهلت پخش به صف تعیین‌تکلیف رفت'
                );

                return $locked;
            });
        } catch (\Throwable) {
            return false; // مثل قفل ردیف — دفعهٔ بعد دوباره تلاش می‌شود
        }

        // پیامک «به صف رفت» — بیرون از تراکنش (خطا مسیر اصلی را نمی‌شکند)
        if ($outcome instanceof Order) {
            $this->smsCustomer(
                $outcome,
                'order.queued',
                ['order_number' => $outcome->order_number],
                'سفارش '.$outcome->order_number.' شما در مهلت پخش پذیرفته نشد؛ به صف بررسی کارشناسان کافی‌نت آنلاین منتقل شد و نتیجه از طریق پیامک اطلاع داده می‌شود.'
            );

            $this->notifications->tryNotify(
                $outcome->customer,
                'order',
                'سفارش به صف بررسی رفت',
                'سفارش «'.$outcome->order_number.'» در مهلت پخش پذیرفته نشد و به صف بررسی کارشناسان منتقل شد.',
                ['order_id' => $outcome->id, 'order_number' => $outcome->order_number],
            );

            return true;
        }

        return $outcome === 'rebroadcast';
    }

    /* ================================================================== */
    /* ۴) عملیات ادمین                                                     */
    /* ================================================================== */

    /**
     * انتقال مستقیم به صف تعیین‌تکلیف (بدون پخش) — وقتی گیرنده‌ای وجود ندارد.
     * از وضعیت paid یا broadcasting مجاز است.
     */
    public function queueOrder(Order $order, ?User $actor = null, string $note = 'انتقال به صف تعیین‌تکلیف'): Order
    {
        $order = DB::transaction(function () use ($order, $actor, $note) {
            $locked = $this->lockOrder($order);

            if (! in_array(OrderStatus::Queued, $locked->status->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'status' => ['این سفارش در وضعیت قابل صف‌بندی نیست (وضعیت فعلی: '.$locked->status->label().').'],
                ]);
            }

            $from = $locked->status;

            $locked->forceFill([
                'status' => OrderStatus::Queued,
                'queued_at' => now(),
                'broadcast_expires_at' => null,
            ])->save();

            $this->history($locked, $from, OrderStatus::Queued, $actor?->id, $note);

            AuditLogger::log(
                'orders.queued',
                $locked,
                ['status' => $from->value],
                ['status' => OrderStatus::Queued->value],
                'سفارش '.$locked->order_number.' به صف تعیین‌تکلیف رفت — '.$note
            );

            return $locked;
        });

        $this->smsCustomer(
            $order,
            'order.queued',
            ['order_number' => $order->order_number],
            'سفارش '.$order->order_number.' شما به صف بررسی کارشناسان کافی‌نت آنلاین منتقل شد؛ نتیجه از طریق پیامک اطلاع داده می‌شود.'
        );

        $this->notifications->tryNotifyEvent(
            $order->customer,
            'order.queued_customer',
            ['order' => $order->order_number],
            ['order_id' => $order->id, 'order_number' => $order->order_number],
        );

        return $order->refresh();
    }

    /** تخصیص دستی صف/پخش به کافی‌نت انتخابی ادمین */
    public function manualAssign(Order $order, Coffeenet $coffeenet, User $admin, ?string $note = null, ?User $operator = null): Order
    {
        $order = $this->accept(
            $order,
            $coffeenet,
            $admin,
            manual: true,
            note: $note ? 'تخصیص دستی: '.mb_substr($note, 0, 400) : '',
            operator: $operator,
        );

        // v25 — اطلاع‌رسانی گیرندگان انتقال: مدیران کافی‌نت مقصد (و اپراتور انتخابی)
        // تا این‌جا فقط مشتری مطلع می‌شد؛ کافی‌نت/اپراتور باید بداند سفارش به آن‌ها رسیده.
        // اعلان درون‌برنامه + پوش دستگاه (اگر آفلاین) + پیامک (اگر آفلاین و تنظیم فعال باشد)
        try {
            $this->notifications->notifyCoffeenetManagersEvent(
                (int) $coffeenet->id,
                'order.transferred_coffeenet',
                [
                    'order' => $order->order_number,
                    'service' => $order->service?->name ?? '-',
                    'coffeenet' => $coffeenet->name,
                ],
                ['url' => '/coffeenet/'.$coffeenet->id.'/orders', 'ref' => ['order_id' => $order->id, 'order_number' => $order->order_number]],
            );

            $managers = \App\Models\StaffAssignment::query()
                ->where('coffeenet_id', $coffeenet->id)
                ->where('position', \App\Enums\StaffPosition::Manager->value)
                ->where('is_active', true)
                ->with('user')
                ->get()
                ->map(fn ($a) => $a->user)
                ->filter();

            foreach ($managers as $manager) {
                app(\App\Services\Sms\NotifySmsService::class)->orderTransferredOffline(
                    $manager,
                    ['order_number' => $order->order_number, 'role_name' => 'مدیر کافی‌نت'],
                );
            }
        } catch (\Throwable) {
            // اطلاع‌رسانی انتقال هرگز تخصیص را نمی‌شکند
        }

        if ($operator) {
            try {
                $this->notifications->tryNotifyEvent(
                    $operator,
                    'order.assigned_operator',
                    ['order' => $order->order_number, 'coffeenet' => $coffeenet->name],
                    ['url' => '/operator/orders', 'ref' => ['order_id' => $order->id, 'order_number' => $order->order_number]],
                );

                app(\App\Services\Sms\NotifySmsService::class)->orderTransferredOffline(
                    $operator,
                    ['order_number' => $order->order_number, 'role_name' => 'اپراتور'],
                );
            } catch (\Throwable) {
                // fail-safe
            }
        }

        return $order;
    }

    /**
     * واگذاری سفارشِ پذیرفته‌شدهٔ یک کافی‌نت به اپراتور همان کافی‌نت.
     * (درخواست بازخوردی — مدیر کافی‌نت/ادمین سفارش را به اپراتور ارجاع می‌دهد)
     *
     * - سفارش باید متعلق به کافی‌نت باشد و اپراتور، اعضای فعال همان کافی‌نت.
     * - اپراتور قبلی (در صورت وجود) جایگزین می‌شود.
     */
    public function assignOperator(Order $order, Coffeenet $coffeenet, User $operator, ?User $actor = null, ?string $note = null): Order
    {
        $assignment = \App\Models\StaffAssignment::query()
            ->where('coffeenet_id', $coffeenet->id)
            ->where('user_id', $operator->id)
            ->where('is_active', true)
            ->first();

        if (! $assignment) {
            throw ValidationException::withMessages([
                'operator_id' => ['این اپراتور عضو فعال کافی‌نت «'.$coffeenet->name.'» نیست.'],
            ]);
        }

        $order = DB::transaction(function () use ($order, $coffeenet, $operator, $actor, $note) {
            $locked = $this->lockOrder($order);

            if ((int) $locked->coffeenet_id !== (int) $coffeenet->id) {
                throw ValidationException::withMessages([
                    'coffeenet' => ['این سفارش به کافی‌نت «'.$coffeenet->name.'» سپرده نشده است.'],
                ]);
            }

            if (! in_array($locked->status->value, [OrderStatus::Accepted->value, OrderStatus::NeedsInfo->value, OrderStatus::InProgress->value, OrderStatus::Paid->value], true)) {
                throw ValidationException::withMessages([
                    'status' => ['سفارش در وضعیت «'.$locked->status->label().'» قابل واگذاری به اپراتور نیست.'],
                ]);
            }

            $previousId = $locked->operator_id;
            $operatorName = trim(($operator->name ?? '').' '.($operator->family ?? ''));

            $locked->forceFill(['operator_id' => $operator->id])->save();

            $this->history(
                $locked,
                $locked->status,
                $locked->status,
                $actor?->id,
                $note ?: 'واگذاری به اپراتور «'.$operatorName.'»'.($previousId ? ' (جایگزین اپراتور قبلی)' : '')
            );

            AuditLogger::log(
                'orders.operator_assigned',
                $locked,
                ['operator_id' => $previousId],
                ['operator_id' => $operator->id],
                'واگذاری سفارش '.$locked->order_number.' به اپراتور «'.$operatorName.'» از کافی‌نت «'.$coffeenet->name.'»'
            );

            return $locked;
        });

        $operatorName = trim(($operator->name ?? '').' '.($operator->family ?? ''));

        // اعلان به اپراتور جدید (v25 — رویدادی + پوش/پیامک آفلاین)
        $this->notifications->tryNotifyEvent(
            $operator,
            'order.assigned_operator',
            ['order' => $order->order_number, 'coffeenet' => $coffeenet->name],
            ['url' => '/operator/orders', 'ref' => ['order_id' => $order->id, 'order_number' => $order->order_number]],
        );

        // پیامک وقتی اپراتور آنلاین نیست و تنظیم فعال است
        try {
            app(\App\Services\Sms\NotifySmsService::class)->orderTransferredOffline(
                $operator,
                ['order_number' => $order->order_number, 'role_name' => 'اپراتور'],
            );
        } catch (\Throwable) {
            // fail-safe
        }

        // پیام سیستمی در گفتگو
        $this->chat->systemMessage(
            $order,
            'اپراتور «'.$operatorName.'» مسئول پیگیری این سفارش است.'
        );

        return $order->refresh();
    }

    /** ری‌پخش دستی سفارشِ صف‌شده (کوشش تازه) */
    public function rebroadcast(Order $order, User $admin): Order
    {
        return $this->startBroadcast(
            $order,
            $admin,
            'ری‌پخش دستی توسط مدیریت کل'
        );
    }

    /** لغو سفارش پرداخت‌شده/صف‌شده توسط ادمین (وجه در فاز ۸ تسویه می‌شود) */
    public function cancelByAdmin(Order $order, User $admin, string $reason): Order
    {
        $reason = trim(mb_substr($reason, 0, 490));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['ذکر دلیل لغو الزامی است.'],
            ]);
        }

        $order = DB::transaction(function () use ($order, $admin, $reason) {
            $locked = $this->lockOrder($order);

            if (! in_array(OrderStatus::Cancelled, $locked->status->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'status' => ['سفارش در وضعیت «'.$locked->status->label().'» قابل لغو نیست.'],
                ]);
            }

            $from = $locked->status;

            $locked->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $admin->id,
                'broadcast_expires_at' => null,
            ])->save();

            $this->history($locked, $from, OrderStatus::Cancelled, $admin->id, 'لغو توسط مدیریت کل — '.$reason);

            AuditLogger::log(
                'orders.cancelled_admin',
                $locked,
                ['status' => $from->value],
                ['status' => OrderStatus::Cancelled->value, 'reason' => $reason],
                'لغو سفارش '.$locked->order_number.' توسط مدیریت کل'
            );

            return $locked;
        });

        $this->smsCustomer(
            $order,
            'order.cancelled',
            ['order_number' => $order->order_number, 'reason' => $reason],
            'سفارش '.$order->order_number.' شما لغو شد: '.$reason.' — وجه پرداختی طبق فرایند تسویه برگشت داده می‌شود. کافی‌نت آنلاین'
        );

        $this->notifications->tryNotify(
            $order->customer,
            'order',
            'لغو سفارش',
            'سفارش «'.$order->order_number.'» لغو شد: '.$reason,
            ['order_id' => $order->id, 'order_number' => $order->order_number],
        );

        // پیام سیستمی لغو (فقط اگر گفتگو از قبل وجود دارد)
        $this->chat->systemMessage(
            $order,
            'سفارش توسط مدیریت کل لغو شد — '.$reason,
            onlyIfExists: true
        );

        return $order->refresh();
    }

    /* ================================================================== */
    /* ۶) تغییر وضعیت سفارش توسط کارکنان (مدیر کل / مدیر کافی‌نت)          */
    /* ================================================================== */

    /**
     * تغییر وضعیت سفارش توسط مدیر کل یا مدیر کافی‌نت.
     *
     * برخلاف اپراتور (که فقط in_progress/needs_info/delivered می‌تواند بگذارد)،
     * کارکنان مدیریتی به همهٔ گذارهای مجاز ماشین وضعیت دسترسی دارند؛
     * از جمله «تکمیل نهایی» (delivered→completed) و «عدم امکان انجام» (لغو).
     *
     * @param  string  $note  یادداشت/دلیل (برای لغو اجباری است)
     * @return array{order:Order,settled:bool|null,settled_reason:string|null}
     */
    public function changeStatusByStaff(Order $order, User $staff, OrderStatus $to, string $note = ''): array
    {
        $note = trim(mb_substr($note, 0, 490));

        if ($to === OrderStatus::Cancelled && $note === '') {
            throw ValidationException::withMessages([
                'reason' => ['ذکر دلیل لغو الزامی است.'],
            ]);
        }

        $order = DB::transaction(function () use ($order, $staff, $to, $note) {
            $locked = $this->lockOrder($order);

            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'status' => ['گذار از «'.$locked->status->label().'» به «'.$to->label().'» مجاز نیست.'],
                ]);
            }

            $from = $locked->status;
            $staffName = trim(($staff->name ?? '').' '.($staff->family ?? ''));

            $payload = ['status' => $to->value];
            if ($to === OrderStatus::Delivered && ! $locked->delivered_at) {
                $payload['delivered_at'] = now();
            }
            if ($to === OrderStatus::Cancelled) {
                $payload['cancel_reason'] = $note;
                $payload['cancelled_by'] = $staff->id;
                $payload['broadcast_expires_at'] = null;
            }
            $locked->forceFill($payload)->save();

            $this->history(
                $locked,
                $from,
                $to,
                $staff->id,
                $note !== '' ? $note : 'تغییر وضعیت به «'.$to->label().'» توسط '.$staffName
            );

            AuditLogger::log(
                'orders.status_changed_staff',
                $locked,
                ['status' => $from->value],
                ['status' => $to->value, 'note' => $note],
                'تغییر وضعیت سفارش '.$locked->order_number.' به «'.$to->label().'» توسط '.$staffName.' (پنل مدیریتی)'
            );

            $this->chat->systemMessage(
                $locked,
                match ($to) {
                    OrderStatus::InProgress => 'اپراتور کار روی سفارش را آغاز کرد.',
                    OrderStatus::NeedsInfo => 'برای ادامهٔ کار، اطلاعات تکمیلی از شما خواسته شد؛ لطفاً در همین گفتگو ارسال کنید.',
                    OrderStatus::Delivered => 'نتیجهٔ سفارش آماده و تحویل داده شد.',
                    OrderStatus::Completed => 'سفارش تکمیل و بسته شد؛ از بازخورد شما سپاسگزاریم.',
                    OrderStatus::Cancelled => 'سفارش لغو شد'.($note !== '' ? ' — '.$note : '').'؛ وجه پرداختی طبق فرایند تسویه برگشت داده می‌شود.',
                    default => 'وضعیت سفارش به «'.$to->label().'» تغییر کرد.',
                },
                onlyIfExists: true
            );

            return $locked;
        });

        // تسویهٔ کمیسیون در لحظهٔ تحویل (idempotent)
        $settled = null;
        $settledReason = null;
        if ($order->status === OrderStatus::Delivered) {
            try {
                $result = $this->settlement->settle($order);
                $settled = (bool) ($result['settled'] ?? false);
                $settledReason = (string) ($result['reason'] ?? '');
            } catch (\Throwable $e) {
                report($e);
                $settledReason = 'error';
            }
        }

        // اعلان درون‌برنامه‌ای + پیامک به مشتری (v25 — رویدادی + پوش آفلاین)
        try {
            match ($order->status) {
                OrderStatus::Delivered => $this->notifications->tryNotifyEvent(
                    $order->customer,
                    'order.delivered_customer',
                    ['order' => $order->order_number],
                    ['order_id' => $order->id, 'order_number' => $order->order_number],
                ),
                OrderStatus::Completed => $this->notifications->tryNotifyEvent(
                    $order->customer,
                    'order.status_customer',
                    ['order' => $order->order_number, 'status' => 'تکمیل شد'],
                    ['order_id' => $order->id, 'order_number' => $order->order_number],
                ),
                OrderStatus::Cancelled => $this->notifications->tryNotifyEvent(
                    $order->customer,
                    'order.cancelled_customer',
                    ['order' => $order->order_number, 'reason' => $note !== '' ? 'دلیل: '.$note : ''],
                    ['order_id' => $order->id, 'order_number' => $order->order_number],
                ),
                default => null,
            };
        } catch (\Throwable) {
            // اعلان نباید جریان اصلی را بشکند
        }

        match ($order->status) {
            OrderStatus::Delivered => $this->smsCustomer(
            $order,
            'order.delivered',
            ['order_number' => $order->order_number],
            'سفارش '.$order->order_number.' شما آماده و تحویل شد؛ برای مشاهدهٔ نتیجه به اپ مراجعه کنید. کافی‌نت آنلاین'
        ),
            OrderStatus::Cancelled => $this->smsCustomer(
            $order,
            'order.cancelled',
            ['order_number' => $order->order_number, 'reason' => $note],
            'سفارش '.$order->order_number.' شما لغو شد: '.$note.' — وجه پرداختی طبق فرایند تسویه برگشت داده می‌شود. کافی‌نت آنلاین'
        ),
            default => null,
        };

        return [
            'order' => $order->refresh(),
            'settled' => $settled,
            'settled_reason' => $settledReason,
        ];
    }

    /* ================================================================== */
    /* ابزارها                                                             */
    /* ================================================================== */

    /** قفل ردیف سفارش داخل تراکنش جاری */
    protected function lockOrder(Order $order): Order
    {
        /** @var Order $locked */
        $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

        abort_unless((bool) $locked, 404, 'سفارش یافت نشد.');

        return $locked;
    }

    /** ثبت تاریخچه وضعیت */
    protected function history(Order $order, OrderStatus $from, OrderStatus $to, ?int $userId, string $note): void
    {
        $order->statusHistory()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'user_id' => $userId,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /**
     * پیامک به مشتری (خطا هرگز جریان اصلی را نمی‌شکند — در sms_logs ثبت می‌شود)
     * v10 — مسیر قالبی/پترنی: sendTemplate ارسال پترنی را نیز پوشش می‌دهد.
     *
     * @param  array<string, string|int|float>  $vars
     */
    protected function smsCustomer(Order $order, string $templateKey, array $vars, ?string $fallback = null): void
    {
        try {
            $mobile = $order->customer()->value('mobile');

            if ($mobile) {
                $this->sms->sendTemplate($mobile, $templateKey, $vars, $fallback);
            }
        } catch (\Throwable) {
            // پیامک نباید موتور تخصیص را متوقف کند
        }
    }
}
