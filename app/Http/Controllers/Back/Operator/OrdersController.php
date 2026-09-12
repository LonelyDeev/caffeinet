<?php

namespace App\Http\Controllers\Back\Operator;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StaffAssignment;
use App\Services\Audit\AuditLogger;
use App\Services\Chat\ChatService;
use App\Services\Orders\OrderAssignmentService;
use App\Support\OperatorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Services\Sms\CustomerSmsService;
use App\Services\Notifications\NotificationService;
use App\Services\Finance\SettlementService;
use Throwable;

/**
 * سفارش‌های پنل اپراتور.
 *
 * محدودهٔ دید بر اساس دسترسی‌ها:
 *  - orders.view → همه سفارش‌های کافی‌نت جاری
 *  - orders.view.own → فقط سفارش‌هایی که operator_id اوست
 *  - هیچ‌کدام → 403
 *
 * فاز ۷: تغییر وضعیت سریع (orders.update_status) با پیام سیستمی در چت.
 */
class OrdersController extends Controller
{
    public function __construct(
        protected OrderAssignmentService $assignment,
        protected ChatService $chat,
        protected SettlementService $settlement,
        protected NotificationService $notifications,
        protected CustomerSmsService $customerSms,
    ) {}

    /** GET /operator/orders */
    public function index(Request $request): View
    {
        $this->authorizeScope($request);

        return view('back.operator.orders.index', [
            'coffeenet' => $request->attributes->get('current_coffeenet'),
            'canAll' => $this->canAll($request),
        ]);
    }

    /** GET /operator/orders/data — جدول AJAX (فیلتر + جستجو + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeScope($request);

        $coffeenet = $request->attributes->get('current_coffeenet');
        $assignment = $request->attributes->get('operator_assignment');
        $canAll = $this->canAll($request);

        // انقضای تنزل مهلت پخش (سفارش‌های در حال پخش تعیین‌تکلیف شوند)
        $this->assignment->expireStale();

        $query = Order::query()
            ->where('coffeenet_id', $coffeenet->id)
            ->when(! $canAll, fn ($q) => $q->where('operator_id', $request->user()->id))
            ->with([
                'service' => fn ($q) => $q->select(['id', 'name']),
                'customer' => fn ($q) => $q->select(['id', 'name', 'family']),
                'operator' => fn ($q) => $q->select(['id', 'name', 'family']),
            ]);

        $status = (string) $request->query('status', 'active');
        if ($status === 'active') {
            // سفارش‌های باز: پذیرفته‌شده، پرداخت‌شده (منتظر شروع کار)، در حال انجام و نیازمند اطلاعات
            $query->whereIn('status', [
                OrderStatus::Accepted->value,
                OrderStatus::Paid->value,
                OrderStatus::InProgress->value,
                OrderStatus::NeedsInfo->value,
            ]);
        } elseif ($status === 'done') {
            $query->whereIn('status', [
                OrderStatus::Delivered->value,
                OrderStatus::Completed->value,
            ]);
        } elseif ($status === 'mine_open') {
            $query->whereIn('status', [
                OrderStatus::Accepted->value,
                OrderStatus::Paid->value,
                OrderStatus::InProgress->value,
                OrderStatus::NeedsInfo->value,
            ])->where('operator_id', $request->user()->id);
        } elseif ($status !== 'all') {
            $enum = OrderStatus::tryFrom($status);
            abort_unless((bool) $enum, 422, 'وضعیت سفارش نامعتبر است.');
            $query->where('status', $enum->value);
        }

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%")->orWhere('family', 'like', "%{$q}%"));
            });
        }

        $rows = $query->orderByDesc('accepted_at')->orderByDesc('id')->paginate(15)->withQueryString();

        $rows->through(fn (Order $order) => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'service_name' => $order->service?->name ?? '—',
            'customer_name' => trim(($order->customer?->name ?? '').' '.($order->customer?->family ?? '')) ?: '—',
            'operator_name' => $order->operator ? trim(($order->operator->name ?? '').' '.($order->operator->family ?? '')) : null,
            'total' => (float) $order->price + (float) $order->expenses,
            'is_paid' => (bool) $order->paid_at,
            'status' => [
                'value' => $order->status->value,
                'label' => $order->status->label(),
                'color' => $order->status->color(),
            ],
            'accepted_at_fa' => $order->accepted_at ? fa_date($order->accepted_at, 'Y/m/d H:i') : null,
            'delivered_at_fa' => $order->delivered_at ? fa_date($order->delivered_at, 'Y/m/d H:i') : null,
        ]);

        return response()->json($rows);
    }

    /* ================================================================== */
    /* ۷) تغییر وضعیت سریع (فاز ۷ — از داخل گفتگو)                          */
    /* ================================================================== */

    /** PATCH /operator/orders/{order}/status {status} */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $assignment = $request->attributes->get('operator_assignment');
        $permissions = OperatorPermissions::filter($assignment->permissions ?? []);

        abort_unless(in_array('orders.update_status', $permissions, true), 403, 'دسترسی تغییر وضعیت سفارش برای شما فعال نیست.');

        $coffeenet = $request->attributes->get('current_coffeenet');
        abort_unless((int) $order->coffeenet_id === (int) $coffeenet->id, 404, 'سفارش یافت نشد.');

        if (! $this->canAll($request)) {
            abort_unless((int) $order->operator_id === (int) $request->user()->id, 403, 'این سفارش به شما سپرده نشده است.');
        }

        $to = OrderStatus::tryFrom((string) $request->input('status'));

        if (! $to || ! in_array($to, [OrderStatus::InProgress, OrderStatus::NeedsInfo, OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            return response()->json(['message' => 'وضعیت هدف برای تغییر توسط اپراتور مجاز نیست.'], 422);
        }

        // فاز ۱۱ — شروع کار/تحویل فقط پس از پرداخت مشتری
        if (in_array($to, [OrderStatus::InProgress, OrderStatus::Delivered], true) && ! $order->paid_at) {
            return response()->json([
                'message' => 'مشتری هنوز پرداخت را انجام نداده است؛ تا زمان پرداخت فقط «نیازمند اطلاعات» قابل انتخاب است.',
            ], 422);
        }

        // لغو توسط اپراتور («عدم امکان انجام کار») — ذکر دلیل اجباری است
        $reason = trim((string) $request->input('reason', ''));
        if ($to === OrderStatus::Cancelled && mb_strlen($reason) < 3) {
            return response()->json([
                'message' => 'برای «عدم امکان انجام»، ذکر دلیل (حداقل ۳ حرف) الزامی است.',
            ], 422);
        }

        $order = DB::transaction(function () use ($order, $to, $request, $reason) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

            abort_unless((bool) $locked, 404, 'سفارش یافت نشد.');

            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                abort(response()->json([
                    'message' => 'گذار از «'.$locked->status->label().'» به «'.$to->label().'» مجاز نیست.',
                ], 422));
            }

            $from = $locked->status;

            $payload = [
                'status' => $to->value,
                'delivered_at' => $to === OrderStatus::Delivered ? now() : $locked->delivered_at,
            ];
            if ($to === OrderStatus::Cancelled) {
                $payload['cancel_reason'] = $reason;
                $payload['cancelled_by'] = $request->user()->id;
                $payload['broadcast_expires_at'] = null;
            }
            $locked->forceFill($payload)->save();

            $locked->statusHistory()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'user_id' => $request->user()->id,
                'note' => $to === OrderStatus::Cancelled
                    ? 'عدم امکان انجام توسط اپراتور «'.trim($request->user()->name.' '.$request->user()->family).'» — '.$reason
                    : 'تغییر وضعیت توسط اپراتور «'.trim($request->user()->name.' '.$request->user()->family).'»',
                'created_at' => now(),
            ]);

            AuditLogger::log(
                'orders.status_changed',
                $locked,
                ['status' => $from->value],
                ['status' => $to->value, 'reason' => $to === OrderStatus::Cancelled ? $reason : null],
                'تغییر وضعیت سفارش '.$locked->order_number.' به «'.$to->label().'» توسط اپراتور'
            );

            // پیام سیستمی در چت
            $this->chat->systemMessage($locked, match ($to) {
                OrderStatus::InProgress => 'اپراتور کار روی سفارش را آغاز کرد.',
                OrderStatus::NeedsInfo => 'برای ادامهٔ کار، اطلاعات تکمیلی از شما خواسته شد؛ لطفاً در همین گفتگو ارسال کنید.',
                OrderStatus::Delivered => 'نتیجهٔ سفارش آماده و تحویل داده شد.',
                OrderStatus::Cancelled => 'اپراتور اعلام کرد انجام این سفارش ممکن نیست — '.$reason.'؛ وجه پرداختی طبق فرایند تسویه برگشت داده می‌شود.',
                default => 'وضعیت سفارش به «'.$to->label().'» تغییر کرد.',
            }, onlyIfExists: true);

            return $locked->refresh();
        });

        // فاز ۸ — تسویهٔ کمیسیون در لحظهٔ تحویل (idempotent؛ هرگز مسیر اصلی را نمی‌شکند)
        $settlement = null;
        if ($order->status === OrderStatus::Delivered) {
            try {
                $settlement = $this->settlement->settle($order);
            } catch (Throwable $e) {
                report($e);
            }
        }

        // پیامک‌های رویداد به مشتری (درخواست بازخوردی ۶-۶ — fail-safe)
        try {
            match ($order->status) {
                OrderStatus::NeedsInfo => $this->customerSms->orderNeedsInfo($order),
                OrderStatus::InProgress => $this->customerSms->orderInProgress(
                    $order,
                    trim($request->user()->name.' '.$request->user()->family)
                ),
                default => null,
            };
        } catch (Throwable) {
            // پیامک تغییر وضعیت را نمی‌شکند
        }

        // پیامک تحویل به مشتری (خارج از تراکنش — هرگز مسیر اصلی را نمی‌شکند)
        // v10 — مسیر قالبی/پترنی (sendTemplate داخل CustomerSmsService)
        if ($order->status === OrderStatus::Delivered) {
            try {
                $this->customerSms->orderDelivered($order);
            } catch (\Throwable) {
                // noop
            }

            // اعلان درون‌برنامه‌ای تحویل (فاز ۱۰) + پوش دستگاه (v25)
            $this->notifications->tryNotifyEvent(
                $order->customer,
                'order.delivered_customer',
                ['order' => $order->order_number],
                ['order_id' => $order->id, 'order_number' => $order->order_number],
            );
        }

        // لغو توسط اپراتور: پیامک + اعلان به مشتری (وجه طبق فرایند تسویه برمی‌گردد)
        // v10 — مسیر قالبی/پترنی (sendTemplate داخل CustomerSmsService)
        if ($order->status === OrderStatus::Cancelled) {
            try {
                $this->customerSms->orderCancelled($order, $reason);
            } catch (\Throwable) {
                // noop
            }

            $this->notifications->tryNotifyEvent(
                $order->customer,
                'order.cancelled_customer',
                ['order' => $order->order_number, 'reason' => 'توسط اپراتور لغو شد — دلیل: '.$reason],
                ['order_id' => $order->id, 'order_number' => $order->order_number],
            );
        }

        $message = 'وضعیت سفارش به «'.$order->status->label().'» تغییر کرد.';
        if ($settlement && ($settlement['settled'] ?? false)) {
            $message .= ' سهم‌های کمیسیون تسویه شد.';
        } elseif ($settlement && ($settlement['reason'] ?? '') === 'no_rule') {
            $message .= ' (قاعدهٔ کمیسیون فعال یافت نشد — تسویه انجام نشد)';
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'status' => [
                    'value' => $order->status->value,
                    'label' => $order->status->label(),
                    'color' => $order->status->color(),
                ],
                'chat' => $this->chat->chatMeta($order),
                'settlement' => $settlement,
            ],
        ]);
    }

    /* ---------- ابزارها ---------- */

    protected function canAll(Request $request): bool
    {
        /** @var StaffAssignment $assignment */
        $assignment = $request->attributes->get('operator_assignment');

        return in_array('orders.view', OperatorPermissions::filter($assignment->permissions ?? []), true);
    }

    protected function authorizeScope(Request $request): void
    {
        /** @var StaffAssignment $assignment */
        $assignment = $request->attributes->get('operator_assignment');
        $permissions = OperatorPermissions::filter($assignment->permissions ?? []);

        abort_unless(
            in_array('orders.view', $permissions, true) || in_array('orders.view.own', $permissions, true),
            403,
            'دسترسی مشاهده سفارش برای شما فعال نیست.'
        );
    }
}
