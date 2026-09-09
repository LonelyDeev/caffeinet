<?php

namespace App\Http\Controllers\Back\Operator;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Chat\ChatService;
use App\Support\OperatorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * گفتگوهای پنل اپراتور (فاز ۷ — چت تلگرام‌گونه).
 *
 * محدودهٔ دید مثل سفارش‌ها:
 *  - orders.view → گفتگوی همهٔ سفارش‌های کافی‌نت جاری
 *  - orders.view.own → فقط گفتگوی سفارش‌های خودش
 *  - هیچ‌کدام → 403
 */
class ChatController extends Controller
{
    public function __construct(protected ChatService $chat) {}

    /** GET /operator/chat — فهرست گفتگوها */
    public function index(Request $request): View
    {
        $this->authorizeChat($request);

        return view('back.operator.chat.index', [
            'coffeenet' => $request->attributes->get('current_coffeenet'),
        ]);
    }

    /** GET /operator/chat/data — فهرست AJAX (فیلتر + جستجو + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeChat($request);

        $coffeenet = $request->attributes->get('current_coffeenet');
        $canAll = $this->canAll($request);

        $query = Order::query()
            ->where('coffeenet_id', $coffeenet->id)
            ->whereHas('conversation')
            ->when(! $canAll, fn ($q) => $q->where('operator_id', $request->user()->id))
            ->with([
                'service' => fn ($q) => $q->select(['id', 'name']),
                'customer' => fn ($q) => $q->select(['id', 'name', 'family']),
                'operator' => fn ($q) => $q->select(['id', 'name', 'family']),
                'conversation:id,order_id',
            ]);

        $filter = (string) $request->query('filter', 'active');
        if ($filter === 'active') {
            $query->whereIn('status', \App\Enums\OrderStatus::chattableValues());
        } elseif ($filter === 'done') {
            $query->whereIn('status', [\App\Enums\OrderStatus::Delivered->value, \App\Enums\OrderStatus::Completed->value]);
        } else {
            $filter = 'all';
        }

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%")->orWhere('family', 'like', "%{$q}%"));
            });
        }

        // آخرین پیامِ هر گفتگو در یک کوئری (بدون باگ eager-load با limit)
        $rows = $query->get();
        $this->lastMessageMap = [];
        $convIds = $rows->pluck('conversation.id')->filter()->values()->all();
        if ($convIds) {
            $this->lastMessageMap = \App\Models\Message::query()
                ->selectRaw('conversation_id, MAX(id) as mid')
                ->whereIn('conversation_id', $convIds)
                ->groupBy('conversation_id')
                ->pluck('mid', 'conversation_id')
                ->all();
        }

        // مرتب‌سازی: آخرین پیام (سفارش بدون پیام آخر)
        $rows = $rows
            ->sortByDesc(fn (Order $order) => (int) ($this->lastMessageMap[$order->conversation?->id] ?? 0))
            ->values();

        // شمارش ناخوانده برای هر گفتگو (پیام مشتری دیده‌نشده)
        $unseen = $this->unseenByOrder($coffeenet->id, $canAll ? null : $request->user()->id);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values()->map(fn (Order $order) => $this->rowPayload($order, $unseen))->all();

        return response()->json([
            'data' => $items,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'total' => $total,
            'from' => $total ? ($page - 1) * $perPage + 1 : 0,
            'to' => min($total, $page * $perPage),
            'unseen_total' => array_sum($unseen),
        ]);
    }

    /** نقشهٔ آخرین پیام هر گفتگو (conversation_id => message_id) */
    protected array $lastMessageMap = [];

    /** GET /operator/chat/badge — بج ناخواندهٔ سایدبار */
    public function badge(Request $request): JsonResponse
    {
        $this->authorizeChat($request);

        $coffeenet = $request->attributes->get('current_coffeenet');

        return response()->json([
            'unseen' => $this->chat->unseenForCoffeenet($coffeenet->id),
        ]);
    }

    /** GET /operator/orders/{order}/chat — صفحهٔ گفتگو */
    public function show(Request $request, Order $order): View
    {
        $this->authorizeOrder($request, $order);

        $assignment = $request->attributes->get('operator_assignment');
        $permissions = OperatorPermissions::filter($assignment->permissions ?? []);

        return view('back.operator.chat.show', [
            'order' => $order->load([
                'service' => fn ($q) => $q->select(['id', 'name']),
                'customer' => fn ($q) => $q->select(['id', 'name', 'family']),
                'operator' => fn ($q) => $q->select(['id', 'name', 'family']),
            ]),
            'canUpdateStatus' => in_array('orders.update_status', $permissions, true),
            'chatMeta' => $this->chat->chatMeta($order),
        ]);
    }

    /** GET /operator/orders/{order}/chat/data?after_id= — پولینگ پیام‌ها */
    public function messages(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        $conversation = $order->conversation()->first();

        if (! $conversation) {
            return response()->json($this->chat->payload($order, [], $request->user()->id));
        }

        $afterId = (int) $request->query('after_id', 0);
        $messages = $this->chat->messages($conversation, $afterId ?: null);

        // پیام‌های مشتری که همین حالا دیده شد
        $this->chat->markSeen($conversation, 'operator');

        return response()->json($this->chat->payload($order, $messages, $request->user()->id));
    }

    /** POST /operator/orders/{order}/chat/send — ارسال (متن یا فایل) */
    public function send(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        $type = (string) $request->input('type', 'text');

        try {
            $message = $this->chat->send(
                $order,
                $request->user(),
                $type,
                $type === 'text' ? (string) $request->input('content', '') : $request->input('content'),
                $request->file('file'),
                $request->filled('duration') ? (float) $request->input('duration') : null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'ارسال پیام ناموفق بود.',
                'errors' => $e->errors(),
            ], 422);
        }

        $message->loadMissing('sender:id,name,family');

        return response()->json([
            'message' => 'پیام ارسال شد.',
            'data' => $this->chat->serializeMessage($message, $request->user()->id, $order),
        ], 201);
    }

    /* ================================================================== */
    /* ابزارها                                                             */
    /* ================================================================== */

    protected function authorizeChat(Request $request): void
    {
        /** @var \App\Models\StaffAssignment $assignment */
        $assignment = $request->attributes->get('operator_assignment');
        $permissions = OperatorPermissions::filter($assignment->permissions ?? []);

        abort_unless(
            in_array('orders.view', $permissions, true) || in_array('orders.view.own', $permissions, true),
            403,
            'دسترسی گفتگو برای شما فعال نیست.'
        );
    }

    protected function authorizeOrder(Request $request, Order $order): void
    {
        $this->authorizeChat($request);

        $coffeenet = $request->attributes->get('current_coffeenet');

        abort_unless((int) $order->coffeenet_id === (int) $coffeenet->id, 404, 'سفارش یافت نشد.');

        if (! $this->canAll($request)) {
            abort_unless((int) $order->operator_id === (int) $request->user()->id, 403,
                'این سفارش به شما سپرده نشده است؛ گفتگوی آن در دسترس شما نیست.');
        }

        // گفتگو فقط در وضعیت‌های مرتبط معنا دارد
        if (! $this->chat->canView($order)) {
            abort(404, 'گفتگویی برای این سفارش وجود ندارد.');
        }
    }

    protected function canAll(Request $request): bool
    {
        /** @var \App\Models\StaffAssignment $assignment */
        $assignment = $request->attributes->get('operator_assignment');

        return in_array('orders.view', OperatorPermissions::filter($assignment->permissions ?? []), true);
    }

    /** نگاشت order_id → تعداد پیام دیده‌نشدهٔ مشتری */
    protected function unseenByOrder(int $coffeenetId, ?int $operatorId = null): array
    {
        $query = \App\Models\Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('orders', 'orders.id', '=', 'conversations.order_id')
            ->where('orders.coffeenet_id', $coffeenetId)
            ->whereNull('messages.seen_at')
            ->whereColumn('messages.sender_id', 'orders.customer_id')
            ->when($operatorId, fn ($q) => $q->where('orders.operator_id', $operatorId));

        return $query->groupBy('orders.id')
            ->selectRaw('orders.id as oid, count(*) as c')
            ->pluck('c', 'oid')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /** یک ردیف فهرست گفتگوها */
    protected function rowPayload(Order $order, array $unseen): array
    {
        $last = null;
        $lastMsgId = $order->conversation ? ($this->lastMessageMap[$order->conversation->id] ?? null) : null;
        if ($lastMsgId) {
            $last = \App\Models\Message::with('sender:id,name,family')->find($lastMsgId);
        }

        $preview = 'گفتگو آغاز شد';
        $previewType = 'none';

        if ($last) {
            $previewType = $last->message_type;
            $preview = match ($last->message_type) {
                'image' => '📷 تصویر',
                'audio' => '🎤 پیام صوتی',
                'video' => '🎬 ویدیو',
                'file' => '📎 '.($last->file_meta['name'] ?? 'فایل'),
                'system' => (string) $last->content,
                default => (string) $last->content,
            };
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'service_name' => $order->service?->name ?? '—',
            'customer_name' => trim(($order->customer?->name ?? '').' '.($order->customer?->family ?? '')) ?: '—',
            'operator_name' => $order->operator ? trim(($order->operator->name ?? '').' '.($order->operator?->family ?? '')) : null,
            'status' => [
                'value' => $order->status->value,
                'label' => $order->status->label(),
                'color' => $order->status->color(),
            ],
            'last_preview' => mb_substr($preview, 0, 90),
            'last_type' => $previewType,
            'last_time_fa' => $last?->created_at ? fa_date($last->created_at, 'm/d H:i') : null,
            'unseen' => (int) ($unseen[$order->id] ?? 0),
        ];
    }
}
