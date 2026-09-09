<?php

namespace App\Http\Controllers\Back\Operator;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderBroadcast;
use App\Services\Orders\OrderAssignmentService;
use App\Support\OperatorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * فاز ۱۱ — صندوق درخواست‌های مشتری در پنل اپراتور.
 *
 * درخواست‌های تازه ثبت‌شدهٔ مشتری (پخش‌شده به این کافی‌نت) با
 * شمارش معکوس؛ اپراتور با دسترسی orders.accept می‌تواند مستقیم
 * درخواست را بپذیرد — سفارش به او سپرده می‌شود و گفتگو باز می‌شود
 * تا مشتری پس از اتصال، پرداخت را انجام دهد.
 */
class RequestsController extends Controller
{
    public function __construct(protected OrderAssignmentService $assignment) {}

    /** GET /operator/requests */
    public function index(Request $request): View
    {
        $this->authorizeAccept($request);

        $timeout = max(15, (int) app(\App\Services\Settings\SettingsService::class)->get('orders.broadcast_timeout', 60));

        return view('back.operator.requests.index', [
            'coffeenet' => $request->attributes->get('current_coffeenet'),
            'broadcastTimeout' => $timeout,
        ]);
    }

    /**
     * GET /operator/requests/data — لیست زندهٔ درخواست‌ها (polling ~۴s).
     * انقضای تنبل پخش هم همین‌جا اجرا می‌شود.
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeAccept($request);

        $coffeenet = $request->attributes->get('current_coffeenet');

        // انقضای تنبل (ری‌پخش/صف) پیش از خواندن لیست
        $this->assignment->expireStale();

        $orders = Order::query()
            ->where('status', OrderStatus::Broadcasting->value)
            ->whereHas('broadcasts', fn ($q) => $q->where('coffeenet_id', $coffeenet->id))
            ->with([
                'service' => fn ($q) => $q->select(['id', 'name', 'estimated_time']),
                'service.category' => fn ($q) => $q->select(['id', 'name', 'icon']),
                'customer' => fn ($q) => $q->select(['id', 'name', 'family']),
                'serviceVersion:id,service_id,version,snapshot',
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
            'requests' => $orders->map(fn (Order $order) => [
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
                // خلاصهٔ پاسخ‌های فرم (برای تصمیم پذیرش)
                'form_data_display' => collect($order->form_data ?? [])->map(function ($value, $key) use ($order) {
                    $label = $key;

                    foreach ($order->serviceVersion?->snapshot['form_fields'] ?? [] as $field) {
                        if (($field['name'] ?? null) === $key) {
                            $label = $field['label'] ?? $key;
                            break;
                        }
                    }

                    return [
                        'label' => $label,
                        'value' => is_array($value) ? implode('، ', $value) : (string) $value,
                    ];
                })->take(6)->values(),
            ]),
        ]);
    }

    /** GET /operator/requests/badge — شمارندهٔ سبک سایدبار (منطبق با لیست) */
    public function badge(Request $request): JsonResponse
    {
        $this->authorizeAccept($request);

        $coffeenet = $request->attributes->get('current_coffeenet');

        // انقضای تنبل — تا بج با لیست درخواست‌ها همیشه هم‌خوان باشد
        $this->assignment->expireStale();

        $count = Order::query()
            ->where('status', OrderStatus::Broadcasting->value)
            ->whereHas('broadcasts', fn ($q) => $q->where('coffeenet_id', $coffeenet->id))
            ->count();

        return response()->json(['count' => $count]);
    }

    /** POST /operator/requests/{order}/accept — پذیرش مستقیم توسط اپراتور */
    public function accept(Request $request, Order $order): JsonResponse
    {
        $this->authorizeAccept($request);

        $coffeenet = $request->attributes->get('current_coffeenet');

        // فقط درخواست‌های در حال پخشِ این کافی‌نت قابل پذیرش‌اند
        if ($order->status === OrderStatus::Broadcasting) {
            $hasBroadcast = OrderBroadcast::query()
                ->where('order_id', $order->id)
                ->where('coffeenet_id', $coffeenet->id)
                ->exists();

            abort_unless($hasBroadcast, 404, 'این درخواست به کافی‌نت شما پخش نشده است.');
        }

        $order = $this->assignment->accept(
            $order,
            $coffeenet,
            $request->user(),
            manual: false,
            note: 'پذیرش درخواست مشتری توسط اپراتور '.$request->user()->full_name,
            operator: $request->user(),
        );

        return response()->json([
            'message' => 'درخواست '.$order->order_number.' پذیرفته شد و به شما سپرده شد؛ گفتگو با مشتری آماده است — مشتری پس از پرداخت، کار را شروع می‌کند.',
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'chat_url' => route('operator.orders.chat', $order),
            ],
        ]);
    }

    /* ---------- ابزارها ---------- */

    protected function authorizeAccept(Request $request): void
    {
        /** @var \App\Models\StaffAssignment $assignment */
        $assignment = $request->attributes->get('operator_assignment');
        $permissions = OperatorPermissions::filter($assignment->permissions ?? []);

        abort_unless(in_array('orders.accept', $permissions, true), 403, 'دسترسی قبول درخواست برای شما فعال نیست.');
    }
}
