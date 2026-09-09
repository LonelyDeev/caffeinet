<?php

namespace App\Services\Customer;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Chat\ChatService;
use App\Services\Finance\WalletException;
use App\Services\Finance\WalletService;
use App\Services\Orders\OrderAssignmentService;
use App\Services\Settings\SettingsService;
use App\Services\Sms\CustomerSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\RedirectionForm;
use Shetabit\Payment\Facade\Payment as PaymentFacade;

/**
 * موتور پرداخت مشتری — دو مسیر:
 *
 *  ۱) کیف پول: بدهکار اتمیک + ثبت Payment موفق در همان تراکنش دیتابیس.
 *  ۲) آنلاین: shetabit/multipay با درایور انتخابی از تنظیمات
 *     (local = درگاه تست/موک در توسعه — zarinpal در پروداکشن).
 *
 * فاز ۱۱ — جریان «اتصال اول، پرداخت بعد»:
 * سفارش‌های تازه در وضعیت accepted (اپراتور وصل شده) قابل پرداخت‌اند؛
 * سفارش‌های قدیمی pending_payment (legacy) هم همچنان پرداخت‌شدنی‌اند.
 *
 * فراخوانی درگاه از مسیر وبِ امضاشده انجام می‌شود تا اپ موبایل و وب
 * هر دو از یک جریان واحد استفاده کنند.
 */
class PaymentGatewayService
{
    public function __construct(
        protected SettingsService $settings,
        protected WalletService $wallets,
        protected OrderAssignmentService $assignment,
        protected ChatService $chat,
        protected CustomerSmsService $customerSms,
    ) {}

    /** درایور فعال پرداخت آنلاین (از تنظیمات پنل) */
    public function driverName(): string
    {
        $driver = (string) $this->settings->get('payment.driver', config('payment.default', 'local'));

        return in_array($driver, ['local', 'zarinpal', 'zibal'], true) ? $driver : 'local';
    }

    /**
     * اعمال تنظیمات درگاه از دیتابیس روی config پکیج (درخواست بازخوردی).
     * پیش از هر خرید/تأیید فراخوانی می‌شود تا credentialهای پنل خوانده شوند.
     */
    protected function syncGatewayConfig(?string $driver = null): void
    {
        $driver ??= $this->driverName();

        if ($driver === 'zarinpal') {
            $merchant = (string) $this->settings->get('payment.zarinpal.merchant_id', '');
            $sandbox = (bool) $this->settings->get('payment.zarinpal.sandbox', false);

            if ($merchant !== '') {
                config(['payment.drivers.zarinpal.merchantId' => $merchant]);
            }
            config(['payment.drivers.zarinpal.sandbox' => $sandbox]);
        } elseif ($driver === 'zibal') {
            $merchant = (string) $this->settings->get('payment.zibal.merchant_id', '');

            if ($merchant !== '') {
                config(['payment.drivers.zibal.merchantId' => $merchant]);
            }
        }
    }

    /** مبلغ قابل پرداخت سفارش */
    public function orderTotal(Order $order): float
    {
        return round((float) $order->price + (float) $order->expenses, 2);
    }

    /** مبلغ پرداخت (سفارش یا شارژ کیف) */
    public function paymentAmount(Payment $payment): float
    {
        return $payment->isForWallet()
            ? round((float) $payment->amount, 2)
            : $this->orderTotal($payment->order);
    }

    /** برچسب سفارش/عملیات برای نمایش در درگاه (شماره سفارش یا «شارژ کیف پول») */
    protected function paymentLabel(Payment $payment): string
    {
        return $payment->isForWallet()
            ? 'شارژ کیف پول'
            : (string) $payment->order?->order_number;
    }

    /**
     * آغاز پرداخت آنلاین — ردیف Payment معلق می‌سازد.
     * ردیف‌های معلق قبلی همان سفارش باطل می‌شوند.
     */
    public function startOnline(Order $order, User $user): Payment
    {
        if ($order->customer_id !== $user->id) {
            abort(403, 'این سفارش متعلق به شما نیست.');
        }

        if (! $this->payable($order)) {
            throw ValidationException::withMessages([
                'order' => ['این سفارش در وضعیت قابل پرداخت نیست (پرداخت پس از پذیرش اپراتور انجام می‌شود).'],
            ]);
        }

        return DB::transaction(function () use ($order, $user) {
            $order->payments()
                ->where('status', PaymentStatus::Pending)
                ->where('driver', '!=', 'wallet')
                ->update(['status' => PaymentStatus::Cancelled, 'meta' => ['reason' => 'پرداخت جدید جایگزین شد']]);

            return $order->payments()->create([
                'user_id' => $user->id,
                'purpose' => Payment::PURPOSE_ORDER,
                'amount' => $this->orderTotal($order),
                'driver' => $this->driverName(),
                'status' => PaymentStatus::Pending,
                'meta' => ['order_number' => $order->order_number],
            ]);
        });
    }

    /**
     * آغاز شارژ کیف پول از درگاه — ردیف Payment معلق (بدون سفارش) می‌سازد.
     * ردیف‌های معلق قبلی شارژِ همان کاربر باطل می‌شوند.
     */
    public function startWalletCharge(User $user, float $amount): Payment
    {
        return DB::transaction(function () use ($user, $amount) {
            $user->payments()
                ->where('status', PaymentStatus::Pending)
                ->where('purpose', Payment::PURPOSE_WALLET)
                ->where('driver', '!=', 'wallet')
                ->update(['status' => PaymentStatus::Cancelled, 'meta' => ['reason' => 'شارژ جدید جایگزین شد']]);

            return $user->payments()->create([
                'order_id' => null,
                'purpose' => Payment::PURPOSE_WALLET,
                'amount' => round($amount, 2),
                'driver' => $this->driverName(),
                'status' => PaymentStatus::Pending,
                'meta' => ['title' => 'افزایش اعتبار کیف پول'],
            ]);
        });
    }

    /** لینک امن شروع پرداخت (امضاشده موقت — از اپ وب/موبایل) */
    public function paymentUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('payment.start', now()->addMinutes(20), ['payment' => $payment->id]);
    }

    /**
     * مسیر نسبیِ همان لینک امضاشده (بدون دامنه) — برای redirect درون‌برنامه‌ای؛
     * هم در لوکال، هم پشت گیت‌وی (با ?XTransformPort=) و هم روی دامنهٔ واقعی کار می‌کند.
     */
    public function paymentPath(Payment $payment): string
    {
        $url = $this->paymentUrl($payment);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }

    /**
     * رندر صفحه درگاه — فرم redirect درایور.
     *
     * نمای اختصاصی CSP-سازگار جایگزین نمای پیش‌فرض پکیج می‌شود:
     *  - local  → test-gateway.php (بدون JS؛ دکمه‌های submit واقعی)
     *  - سایر   → redirect.php     (فرم انتقال با دکمه، بدون اتوسابمیت)
     * callback نسبی پاس می‌شود تا از هر مبدأ (لوکال/گیت‌وی/دامنه) کار کند.
     * transactionId تولیدی درایور در ref_id ذخیره می‌شود.
     */
    public function renderGateway(Payment $payment): string
    {
        $this->syncGatewayConfig($payment->driver);

        $form = PaymentFacade::via($payment->driver)
            ->amount((int) round($this->paymentAmount($payment)))
            ->transactionId($payment->id)
            ->detail('orderId', $this->paymentLabel($payment))
            ->detail('title', $payment->isForWallet() ? 'افزایش اعتبار کیف پول' : 'پرداخت سفارش')
            ->callbackUrl('/payment/callback')
            ->purchase(null, function ($driver, $transactionId) use ($payment) {
                $payment->forceFill(['ref_id' => (string) $transactionId])->save();
            })
            ->pay();

        // نمای اختصاصی (بعد از pay که درایور مسیر خودش را ست می‌کند، قبل از render)
        RedirectionForm::setViewPath(
            $payment->driver === 'local'
                ? resource_path('views/payment/test-gateway.php')
                : resource_path('views/payment/redirect.php')
        );

        return $form->render();
    }

    /**
     * پرداخت با کیف پول — بدهکار اتمیک + ثبت سفارش paid در یک تراکنش.
     * مبلغ صفر → بدون برداشت، مستقیم نهایی می‌شود.
     */
    public function payWithWallet(Order $order, User $user): Payment
    {
        if ($order->customer_id !== $user->id) {
            abort(403, 'این سفارش متعلق به شما نیست.');
        }

        if (! $this->payable($order)) {
            throw ValidationException::withMessages([
                'order' => ['این سفارش در وضعیت قابل پرداخت نیست (پرداخت پس از پذیرش اپراتور انجام می‌شود).'],
            ]);
        }

        $total = $this->orderTotal($order);

        if ($total <= 0) {
            // مبلغ صفر — نیازی به برداشت نیست
            $this->markOrderPaid($order, $user, 'مبلغ صفر — نهایی‌سازی بدون پرداخت', 'wallet');

            return $order->payments()->latest('id')->first() ?? $order->payments()->create([
                'user_id' => $user->id,
                'amount' => 0,
                'driver' => 'wallet',
                'status' => PaymentStatus::Success,
                'paid_at' => now(),
                'meta' => ['zero' => true],
            ]);
        }

        try {
            return DB::transaction(function () use ($order, $user, $total) {
                $transaction = $this->wallets->debit(
                    $user,
                    $total,
                    'order',
                    $order->id,
                    'پرداخت سفارش '.$order->order_number,
                    ['order_number' => $order->order_number, 'service' => $order->service?->name]
                );

                $payment = $order->payments()->create([
                    'user_id' => $user->id,
                    'amount' => $total,
                    'driver' => 'wallet',
                    'ref_id' => 'TX-'.$transaction->id,
                    'status' => PaymentStatus::Success,
                    'paid_at' => now(),
                    'meta' => ['transaction_id' => $transaction->id, 'balance_after' => (float) $transaction->balance_after],
                ]);

                $this->markOrderPaid($order, $user, 'پرداخت از کیف پول', 'wallet');

                return $payment;
            });
        } catch (WalletException $e) {
            throw ValidationException::withMessages([
                'method' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * callback درگاه (وب) — تأیید تراکنش و نهایی‌سازی (سفارش یا شارژ کیف).
     *
     * @return Payment تراکنش تأییدشده (purpose تعیین می‌کند مقصد چیست)
     */
    public function handleCallback(Request $request): Payment
    {
        $transactionId = (string) en_digits((string) $request->input('transactionId', ''));

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('ref_id', $transactionId)
            ->where('driver', '!=', 'wallet')
            ->orderByDesc('id')
            ->first();

        if (! $payment) {
            abort(404, 'تراکنش پرداخت یافت نشد.');
        }

        // idempotent — اگر قبلاً موفق شده
        if ($payment->status === PaymentStatus::Success) {
            return $payment;
        }

        $cancelled = $request->boolean('cancel');

        try {
            $this->syncGatewayConfig($payment->driver);

            $receipt = PaymentFacade::via($payment->driver)
                ->amount((int) round((float) $payment->amount))
                ->transactionId($transactionId)
                ->verify();

            DB::transaction(function () use ($payment, $receipt, $request) {
                $meta = is_array($payment->meta) ? $payment->meta : [];
                $payment->forceFill([
                    'status' => PaymentStatus::Success,
                    'paid_at' => now(),
                    'meta' => array_merge($meta, [
                        'reference' => (string) $receipt->getReferenceId(),
                        'driver' => (string) $receipt->getDriver(),
                        'details' => $receipt->getDetails(),
                        'verified_at' => now()->toIso8601String(),
                    ]),
                ])->save();

                if ($payment->isForWallet()) {
                    $this->markWalletCharged(
                        $payment,
                        'شارژ کیف پول از درگاه (مرجع: '.fa_digits((string) $receipt->getReferenceId()).')'
                    );
                } else {
                    $this->markOrderPaid(
                        $payment->order,
                        $payment->user,
                        'پرداخت آنلاین (مرجع: '.fa_digits((string) $receipt->getReferenceId()).')',
                        $payment->driver
                    );
                }
            });

            return $payment;
        } catch (InvalidPaymentException $e) {
            DB::transaction(function () use ($payment, $e, $cancelled) {
                $meta = is_array($payment->meta) ? $payment->meta : [];
                $payment->forceFill([
                    'status' => $cancelled ? PaymentStatus::Cancelled : PaymentStatus::Failed,
                    'meta' => array_merge($meta, ['error' => $e->getMessage()]),
                ])->save();
            });

            // پیام برای redirect
            throw ValidationException::withMessages([
                'payment' => [$cancelled ? 'پرداخت توسط شما لغو شد.' : 'پرداخت ناموفق بود؛ می‌توانید دوباره تلاش کنید.'],
            ]);
        }
    }

    /**
     * بستانکارِ اتمیک کیف پول پس از تأیید درگاه (idempotent).
     * پیامک «واریز کیف» داخل WalletService::credit ارسال می‌شود.
     */
    protected function markWalletCharged(Payment $payment, string $note): void
    {
        $payment = $payment->refresh();
        $user = $payment->user;

        if (! $user || ($payment->meta['wallet_transaction_id'] ?? null)) {
            return; // قبلاً اعمال شده
        }

        $transaction = $this->wallets->credit(
            $user,
            (float) $payment->amount,
            'gateway',
            $payment->id,
            $note,
            ['payment_id' => $payment->id, 'driver' => $payment->driver]
        );

        $meta = is_array($payment->meta) ? $payment->meta : [];
        $payment->forceFill([
            'ref_id' => $payment->ref_id ?: ('TX-'.$transaction->id),
            'meta' => array_merge($meta, [
                'wallet_transaction_id' => $transaction->id,
                'balance_after' => (float) $transaction->balance_after,
            ]),
        ])->save();

        AuditLogger::log(
            'customer.wallet_charged',
            $payment,
            [],
            ['amount' => (float) $payment->amount, 'driver' => $payment->driver, 'transaction_id' => $transaction->id],
            $note.' — کاربر: '.($user->name ?: $user->mobile)
        );
    }

    /** سفارشِ قابل پرداخت؟ (فاز ۱۱: accepted — یا legacy: pending_payment) */
    protected function payable(Order $order): bool
    {
        return ! $order->paid_at && in_array($order->status, [
            OrderStatus::Accepted,
            OrderStatus::PendingPayment, // سفارش‌های قدیمی قبل از فاز ۱۱
        ], true);
    }

    /** سفارش → paid + تاریخچه + لاگ (idempotent) — دو مسیر فاز ۱۱/legacy */
    protected function markOrderPaid(Order $order, ?User $user, string $note, string $driver): void
    {
        $order = $order->refresh();

        $from = $order->status;

        if (! $this->payable($order)) {
            return; // قبلاً پرداخت شده / وضعیت نامعتبر
        }

        $order->forceFill([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ])->save();

        $order->statusHistory()->create([
            'from_status' => $from->value,
            'to_status' => OrderStatus::Paid->value,
            'user_id' => $user?->id,
            'note' => $note.' — درایور: '.$driver,
            'created_at' => now(),
        ]);

        AuditLogger::log(
            'customer.order_paid',
            $order,
            ['status' => $from->value],
            ['driver' => $driver, 'amount' => $this->orderTotal($order)],
            'پرداخت سفارش '.$order->order_number.' ('.$driver.')'
        );

        // پیامک پرداخت موفق به مشتری (درخواست بازخوردی ۶-۶ — fail-safe)
        try {
            $trace = $order->payments()->latest('id')->value('trace');
            $this->customerSms->orderPaid($order, $trace);
        } catch (\Throwable) {
            // پیامک پرداخت را نمی‌شکند
        }

        if ($from === OrderStatus::Accepted) {
            // فاز ۱۱ — اپراتور قبلاً وصل است؛ پخشی در کار نیست.
            // اعلان در گفتگو تا اپراتور بداند کار قابل شروع است.
            try {
                $this->chat->systemMessage(
                    $order,
                    'پرداخت سفارش توسط مشتری انجام شد — کار قابل شروع است.'
                );
            } catch (\Throwable) {
                // چت هرگز پرداخت موفق را نمی‌شکند
            }

            return;
        }

        // مسیر legacy (pending_payment): پخش بعد از پرداخت
        try {
            $this->assignment->startBroadcast($order->refresh(), $user, 'شروع پخش پس از پرداخت ('.$driver.')');
        } catch (ValidationException $e) {
            try {
                $this->assignment->queueOrder(
                    $order->refresh(),
                    $user,
                    'بدون گیرندهٔ پخش — انتقال مستقیم به صف تعیین‌تکلیف'
                );
            } catch (ValidationException) {
                // هم‌زمانی نادر؛ سفارش در وضعیت paid می‌ماند و ادمین تعیین‌تکلیف می‌کند
            }
        }
    }
}
