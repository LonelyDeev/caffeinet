<?php

namespace App\Http\Controllers\Back\Coffeenet;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Order;
use App\Models\OrderBroadcast;
use App\Models\StaffAssignment;
use App\Services\Chat\ChatService;
use App\Services\Orders\OrderAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * فاز ۶ — سفارش‌های پنل کافی‌نت:
 *  صندوق پخش زنده (۶۰ ثانیه + شمارش معکوس) + پذیرش اتمیک + لیست سفارش‌های خود کافی‌نت.
 */
class OrdersController extends Controller
{
    use WorksInCoffeenet;

    public function __construct(
        protected OrderAssignmentService $assignment,
        protected ChatService $chat,
    ) {}

    /** GET /coffeenet/{coffeenet}/orders — صندوق پخش + سفارش‌های من */
    public function index(Request $request): View
    {
        $coffeenet = $this->currentCoffeenet($request);

        return view('back.coffeenet.orders.index', [
            'coffeenet' => $coffeenet,
            'broadcastTimeout' => max(15, (int) app(\App\Services\Settings\SettingsService::class)->get('orders.broadcast_timeout', 60)),
        ]);
    }

    /**
     * GET /coffeenet/{coffeenet}/orders/broadcast/data — لیست زندهٔ پخش (polling ~۴s).
     * انقضای تنبل هم همین‌جا اجرا می‌شود تا تایمر ۶۰ ثانیه‌ای بدون cron دقیق باشد.
     */
    public function broadcastData(Request $request): JsonResponse
    {
        $coffeenet = $this->currentCoffeenet($request);

        // انقضای تنبل (ری‌پخش/صف) پیش از خواندن لیست
        $this->assignment->expireStale();

        $orders = Order::query()
            ->where('status', OrderStatus::Broadcasting->value)
            ->whereHas('broadcasts', fn ($q) => $q->where('coffeenet_id', $coffeenet->id))
            ->with([
                'service' => fn ($q) => $q->select(['id', 'name', 'estimated_time']),
                'service.category' => fn ($q) => $q->select(['id', 'name', 'icon']),
                'customer' => fn ($q) => $q->select(['id', 'name', 'family']),
            ])
            ->orderBy('id')
            ->get();

        // علامت‌گذاری دیده‌شدن
        OrderBroadcast::query()
            ->whereNull('seen_at')
            ->where('coffeenet_id', $coffeenet->id)
            ->whereIn('order_id', $orders->pluck('id'))
            ->update(['seen_at' => now()]);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'count' => $orders->count(),
            'orders' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'service_name' => $order->service?->name,
                'service_icon' => $order->service?->category?->icon ?: '📄',
                'estimated_time' => $order->service?->estimated_time,
                'customer_name' => trim(($order->customer?->name ?? '').' '.($order->customer?->family ?? '')) ?: '—',
                'total' => (float) $order->price + (float) $order->expenses,
                'seconds_left' => $order->broadcastSecondsLeft(),
                'attempts' => (int) $order->broadcast_attempts,
                'created_fa' => fa_date($order->created_at, 'H:i'),
                'has_files' => $order->files()->exists(),
            ]),
        ]);
    }

    /** GET /coffeenet/{coffeenet}/orders/data — سفارش‌های پذیرفته‌شدهٔ این کافی‌نت (AJAX) */
    public function data(Request $request): JsonResponse
    {
        $coffeenet = $this->currentCoffeenet($request);

        $this->assignment->expireStale();

        $query = Order::query()
            ->where('coffeenet_id', $coffeenet->id)
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

        $rows = $query->orderByDesc('accepted_at')->paginate(15)->withQueryString();

        $rows->through(fn (Order $order) => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'service_name' => $order->service?->name ?? '—',
            'customer_name' => trim(($order->customer?->name ?? '').' '.($order->customer?->family ?? '')) ?: '—',
            'operator_name' => $order->operator ? trim(($order->operator->name ?? '').' '.($order->operator->family ?? '')) : null,
            'total' => (float) $order->price + (float) $order->expenses,
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

    /** POST /coffeenet/{coffeenet}/orders/{order}/accept — پذیرش اتمیک */
    public function accept(Request $request, Coffeenet $coffeenet, Order $order): JsonResponse
    {
        $session = $this->currentCoffeenet($request);

        if ($coffeenet->id !== $session->id) {
            return response()->json(['message' => 'کافی‌نت مسیر با جلسه فعلی شما مطابقت ندارد.'], 403);
        }

        $order = $this->assignment->accept(
            $order,
            $coffeenet,
            $request->user(),
            manual: false,
            note: 'پذیرش از پنل کافی‌نت توسط '.$request->user()->full_name
        );

        return response()->json([
            'message' => 'سفارش '.$order->order_number.' پذیرفته شد؛ به فهرست «سفارش‌های من» اضافه شد.',
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
            ],
        ]);
    }

    /**
     * GET /coffeenet/{coffeenet}/orders/operators — اپراتورهای فعال این کافی‌نت
     * (برای مودال «ارجاع به اپراتور»).
     */
    public function operators(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        $session = $this->currentCoffeenet($request);

        if ($coffeenet->id !== $session->id) {
            return response()->json(['message' => 'کافی‌نت مسیر با جلسه فعلی شما مطابقت ندارد.'], 403);
        }

        $rows = StaffAssignment::query()
            ->where('coffeenet_id', $coffeenet->id)
            ->where('is_active', true)
            ->with('user:id,name,family,mobile')
            ->get()
            ->map(fn (StaffAssignment $s) => [
                'id' => $s->user_id,
                'name' => $s->user?->full_name ?? '—',
                'mobile' => $s->user?->mobile,
                'position' => $s->position->value,
                'position_label' => $s->position->label(),
            ]);

        return response()->json(['data' => $rows]);
    }

    /** PATCH /coffeenet/{coffeenet}/orders/{order}/operator — ارجاع سفارش به اپراتور */
    public function assignOperator(Request $request, Coffeenet $coffeenet, Order $order): JsonResponse
    {
        $session = $this->currentCoffeenet($request);

        if ($coffeenet->id !== $session->id) {
            return response()->json(['message' => 'کافی‌نت مسیر با جلسه فعلی شما مطابقت ندارد.'], 403);
        }

        $data = $request->validate([
            'operator_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:400'],
        ], [
            'operator_id.required' => 'انتخاب اپراتور الزامی است.',
            'operator_id.exists' => 'اپراتور انتخابی یافت نشد.',
        ]);

        $operator = \App\Models\User::query()->findOrFail((int) $data['operator_id']);

        $order = $this->assignment->assignOperator(
            $order,
            $coffeenet,
            $operator,
            $request->user(),
            $data['note'] ?? null
        );

        $operatorName = trim(($operator->name ?? '').' '.($operator->family ?? ''));

        return response()->json([
            'message' => 'سفارش '.$order->order_number.' به اپراتور «'.$operatorName.'» واگذار شد.',
        ]);
    }

    /**
     * PATCH /coffeenet/{coffeenet}/orders/{order}/status — تغییر وضعیت توسط مدیر کافی‌نت.
     *
     * همهٔ گذارهای مجاز ماشین وضعیت پذیرفته می‌شود (شروع/ادامه کار، تحویل،
     * تکمیل نهایی، لغو و…)؛ برای لغو، «reason» اجباری است.
     */
    public function updateStatus(Request $request, Coffeenet $coffeenet, Order $order): JsonResponse
    {
        $session = $this->currentCoffeenet($request);

        if ($coffeenet->id !== $session->id) {
            return response()->json(['message' => 'کافی‌نت مسیر با جلسه فعلی شما مطابقت ندارد.'], 403);
        }

        if ((int) $order->coffeenet_id !== (int) $coffeenet->id) {
            return response()->json(['message' => 'سفارش یافت نشد.'], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:490'],
        ], [
            'status.required' => 'وضعیت جدید الزامی است.',
        ]);

        $to = OrderStatus::tryFrom((string) $data['status']);
        abort_unless((bool) $to, 422, 'وضعیت سفارش نامعتبر است.');

        $result = $this->assignment->changeStatusByStaff(
            $order,
            $request->user(),
            $to,
            (string) ($data['reason'] ?? '')
        );

        $updated = $result['order'];

        $message = 'وضعیت سفارش به «'.$updated->status->label().'» تغییر کرد.';
        if ($result['settled']) {
            $message .= ' سهم‌های کمیسیون تسویه شد.';
        } elseif (($result['settled_reason'] ?? '') === 'no_rule') {
            $message .= ' (قاعدهٔ کمیسیون فعال یافت نشد — تسویه انجام نشد)';
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'status' => [
                    'value' => $updated->status->value,
                    'label' => $updated->status->label(),
                    'color' => $updated->status->color(),
                ],
                'chat' => $this->chat->chatMeta($updated),
            ],
        ]);
    }
}
