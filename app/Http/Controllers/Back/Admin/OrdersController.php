<?php

namespace App\Http\Controllers\Back\Admin;

use App\Enums\CoffeenetStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Order;
use App\Models\OrderFile;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Orders\OrderAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * فاز ۶ — مدیریت سفارش‌ها (پنل مدیریت کل):
 *  نظارت کامل + تعیین‌تکلیف سفارش‌های صف (تخصیص دستی / ری‌پخش / لغو).
 */
class OrdersController extends Controller
{
    public function __construct(
        protected OrderAssignmentService $assignment,
        protected ChatService $chat,
    ) {}

    /** GET /admin/orders */
    public function index(Request $request): View
    {
        $this->assignment->expireStale();

        return view('back.admin.orders.index', [
            'statuses' => collect(OrderStatus::cases())
                ->map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->all(),
        ]);
    }

    /** GET /admin/orders/data — لیست (AJAX + فیلتر وضعیت + جستجو + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $this->assignment->expireStale();

        $query = Order::query()
            ->with([
                'service' => fn ($q) => $q->select(['id', 'name']),
                'customer' => fn ($q) => $q->select(['id', 'name', 'family', 'mobile']),
                'coffeenet' => fn ($q) => $q->select(['id', 'name']),
            ]);

        if ($status = (string) $request->query('status')) {
            if ($status === 'active') {
                $query->whereIn('status', [
                    OrderStatus::Broadcasting->value,
                    OrderStatus::Queued->value,
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
            } else {
                $enum = OrderStatus::tryFrom($status);
                abort_unless((bool) $enum, 422, 'وضعیت سفارش نامعتبر است.');
                $query->where('status', $enum->value);
            }
        }

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('family', 'like', "%{$q}%")
                        ->orWhere('mobile', 'like', "%{$q}%"));
            });
        }

        $rows = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $rows->through(fn (Order $order) => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'service_name' => $order->service?->name ?? '—',
            'customer_name' => trim(($order->customer?->name ?? '').' '.($order->customer?->family ?? '')) ?: '—',
            'customer_mobile' => $order->customer?->mobile,
            'coffeenet_name' => $order->coffeenet?->name,
            'total' => (float) $order->price + (float) $order->expenses,
            'status' => [
                'value' => $order->status->value,
                'label' => $order->status->label(),
                'color' => $order->status->color(),
            ],
            'seconds_left' => $order->broadcastSecondsLeft(),
            'created_fa' => fa_date($order->created_at, 'Y/m/d H:i'),
            'paid' => $order->paid_at !== null,
        ]);

        return response()->json($rows);
    }

    /** GET /admin/orders/counts — چیپ‌های آماری (polling ملایم) */
    public function counts(): JsonResponse
    {
        $this->assignment->expireStale();

        $counts = Order::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $map = fn ($statuses) => (int) collect($statuses)->sum(fn ($s) => $counts->get($s, 0));

        return response()->json([
            'broadcasting' => (int) $counts->get(OrderStatus::Broadcasting->value, 0),
            'queued' => (int) $counts->get(OrderStatus::Queued->value, 0),
            'active' => $map([OrderStatus::Accepted->value, OrderStatus::InProgress->value, OrderStatus::NeedsInfo->value]),
            'done' => $map([OrderStatus::Delivered->value, OrderStatus::Completed->value]),
            'total' => (int) $counts->sum(),
        ]);
    }

    /** GET /admin/orders/{order}/view — صفحهٔ کامل جزئیات سفارش (از نوتیف/لیست) */
    public function view(Request $request, Order $order): View
    {
        $this->assignment->expireStale();

        return view('back.admin.orders.show', [
            'order' => $order->load([
                'service' => fn ($q) => $q->select(['id', 'name']),
                'customer' => fn ($q) => $q->select(['id', 'name', 'family', 'mobile']),
            ]),
        ]);
    }

    /** GET /admin/orders/{order} — جزئیات کامل (مودال/صفحه — JSON) */
    public function show(Order $order): JsonResponse
    {
        $this->assignment->expireStale();

        $order->load([
            'service' => fn ($q) => $q->select(['id', 'name', 'description', 'estimated_time']),
            'service.category' => fn ($q) => $q->select(['id', 'name', 'icon']),
            'serviceVersion:id,service_id,version,snapshot',
            'customer' => fn ($q) => $q->select(['id', 'name', 'family', 'mobile']),
            'customer.city' => fn ($q) => $q->select(['id', 'name']),
            'coffeenet' => fn ($q) => $q->select(['id', 'name', 'phone']),
            'operator' => fn ($q) => $q->select(['id', 'name', 'family']),
            'files',
            'statusHistory',
            'payments',
            'rating',
            'broadcasts.coffeenet' => fn ($q) => $q->select(['id', 'name']),
        ]);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => ['value' => $order->status->value, 'label' => $order->status->label(), 'color' => $order->status->color()],
                'seconds_left' => $order->broadcastSecondsLeft(),
                'attempts' => (int) $order->broadcast_attempts,
                'total' => (float) $order->price + (float) $order->expenses,
                'price' => (float) $order->price,
                'expenses' => (float) $order->expenses,
                'created_fa' => fa_date($order->created_at, 'Y/m/d H:i'),
                'paid_at_fa' => $order->paid_at ? fa_date($order->paid_at, 'Y/m/d H:i') : null,
                'accepted_at_fa' => $order->accepted_at ? fa_date($order->accepted_at, 'Y/m/d H:i') : null,
                'queued_at_fa' => $order->queued_at ? fa_date($order->queued_at, 'Y/m/d H:i') : null,
                'cancel_reason' => $order->cancel_reason,

                'service' => [
                    'name' => $order->service?->name,
                    'icon' => $order->service?->category?->icon ?: '📄',
                    'description' => $order->service?->description,
                    'estimated_time' => $order->service?->estimated_time,
                ],
                'customer' => [
                    'name' => trim(($order->customer?->name ?? '').' '.($order->customer?->family ?? '')) ?: '—',
                    'mobile' => $order->customer?->mobile,
                    'city' => $order->customer?->city?->name,
                ],
                'coffeenet' => $order->coffeenet ? [
                    'id' => $order->coffeenet->id,
                    'name' => $order->coffeenet->name,
                    'phone' => $order->coffeenet->phone,
                ] : null,
                'operator_name' => $order->operator ? trim(($order->operator->name ?? '').' '.($order->operator->family ?? '')) : null,

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
                })->values()->all(),

                'files' => $order->files->filter(fn (OrderFile $f) => $f->file_type === 'document')
                    ->map(fn (OrderFile $f) => [
                        'id' => $f->id,
                        'original_name' => $f->original_name,
                        'size_kb' => (int) round((int) $f->size / 1024),
                        'url' => URL::temporarySignedRoute('files.order', now()->addHours(6), ['file' => $f->id]),
                    ])->values()->all(),

                'broadcasts' => $order->broadcasts->map(fn ($b) => [
                    'coffeenet_name' => $b->coffeenet?->name ?? '—',
                    'sent_fa' => $b->sent_at ? fa_date($b->sent_at, 'H:i') : '—',
                    'seen_fa' => $b->seen_at ? fa_date($b->seen_at, 'H:i') : null,
                ])->all(),

                'status_history' => $order->statusHistory->map(fn ($h) => [
                    'to_status_label' => OrderStatus::tryFrom((string) $h->to_status)?->label(),
                    'note' => $h->note,
                    'created_at_fa' => $h->created_at ? fa_date($h->created_at, 'Y/m/d H:i') : null,
                ])->all(),

                'payments' => $order->payments->map(fn ($p) => [
                    'driver' => $p->driver,
                    'amount' => (float) $p->amount,
                    'status_label' => $p->status->label(),
                    'ref_id' => $p->ref_id,
                    'paid_at_fa' => $p->paid_at ? fa_date($p->paid_at, 'Y/m/d H:i') : null,
                ])->all(),

                'rating' => $order->rating ? [
                    'rating' => (int) $order->rating->rating,
                    'comment' => $order->rating->comment,
                    'rated_at_fa' => $order->rating->rated_at ? fa_date($order->rating->rated_at, 'Y/m/d H:i') : null,
                ] : null,
            ],
        ]);
    }

    /** GET /admin/orders/coffeenets?q= — کافی‌نت‌های فعال برای مودال تخصیص */
    public function coffeenets(Request $request): JsonResponse
    {
        $query = Coffeenet::query()
            ->where('status', CoffeenetStatus::Approved->value)
            ->with('city:id,name')
            ->select(['id', 'name', 'phone', 'city_id']);

        if ($q = trim((string) $request->query('q'))) {
            $query->where('name', 'like', "%{$q}%");
        }

        return response()->json([
            'data' => $query->orderBy('name')->limit(20)->get()
                ->map(fn (Coffeenet $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'city' => $c->city?->name,
                ]),
        ]);
    }

    /** PATCH /admin/orders/{order}/assign — تخصیص دستی به کافی‌نت (+ اپراتور اختیاری) */
    public function assign(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'coffeenet_id' => ['required', 'integer', 'exists:coffeenets,id'],
            'operator_id' => ['nullable', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:400'],
        ], [
            'coffeenet_id.required' => 'انتخاب کافی‌نت الزامی است.',
            'coffeenet_id.exists' => 'کافی‌نت انتخابی یافت نشد.',
            'operator_id.exists' => 'اپراتور انتخابی یافت نشد.',
        ]);

        $coffeenet = Coffeenet::query()->findOrFail((int) $data['coffeenet_id']);
        $operator = ! empty($data['operator_id']) ? User::findOrFail((int) $data['operator_id']) : null;

        $order = $this->assignment->manualAssign(
            $order,
            $coffeenet,
            $request->user(),
            $data['note'] ?? null,
            $operator
        );

        $operatorName = $operator ? trim(($operator->name ?? '').' '.($operator->family ?? '')) : null;

        return response()->json([
            'message' => 'سفارش '.$order->order_number.' به کافی‌نت «'.$coffeenet->name.'» تخصیص یافت.'
                .($operatorName ? ' (اپراتور مسئول: «'.$operatorName.'»)' : ''),
        ]);
    }

    /** GET /admin/orders/{order}/operators?coffeenet_id= — اپراتورهای فعال یک کافی‌نت */
    public function operators(Request $request, Order $order): JsonResponse
    {
        $coffeenetId = (int) $request->query('coffeenet_id', (int) $order->coffeenet_id);
        abort_unless($coffeenetId > 0, 422, 'شناسهٔ کافی‌نت لازم است.');

        $rows = StaffAssignment::query()
            ->where('coffeenet_id', $coffeenetId)
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

    /** PATCH /admin/orders/{order}/operator — واگذاری به اپراتور کافی‌نت پذیرنده */
    public function assignOperator(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'operator_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:400'],
        ], [
            'operator_id.required' => 'انتخاب اپراتور الزامی است.',
            'operator_id.exists' => 'اپراتور انتخابی یافت نشد.',
        ]);

        $coffeenet = $order->coffeenet()->firstOrFail();
        $operator = User::query()->findOrFail((int) $data['operator_id']);

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
     * PATCH /admin/orders/{order}/status — تغییر وضعیت توسط مدیریت کل.
     *
     * همهٔ گذارهای مجاز ماشین وضعیت پذیرفته می‌شود (شروع/ادامه کار، تحویل،
     * تکمیل نهایی، لغو و…)؛ برای لغو، «reason» اجباری است.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
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

    /** POST /admin/orders/{order}/rebroadcast — ری‌پخش سفارش صف‌شده */
    public function rebroadcast(Request $request, Order $order): JsonResponse
    {
        $order = $this->assignment->rebroadcast($order, $request->user());

        return response()->json([
            'message' => 'سفارش '.$order->order_number.' مجدداً بین کافی‌نت‌ها پخش شد (کوشش '.fa_digits((string) $order->broadcast_attempts).').',
        ]);
    }

    /** PATCH /admin/orders/{order}/cancel — لغو با دلیل */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:490'],
        ], [
            'reason.required' => 'ذکر دلیل لغو الزامی است.',
            'reason.min' => 'دلیل لغو باید حداقل ۳ حرف باشد.',
        ]);

        $order = $this->assignment->cancelByAdmin($order, $request->user(), (string) $data['reason']);

        return response()->json([
            'message' => 'سفارش '.$order->order_number.' لغو شد.',
        ]);
    }
}
