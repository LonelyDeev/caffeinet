<?php

namespace App\Http\Controllers\Back\Shared;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * گفتگوی سفارش برای مدیر کل و مدیر کافی‌نت (درخواست بازخوردی).
 *
 * همان موتور چتِ پنل اپراتور را با همان viewها استفاده می‌کند؛
 * تنها تفاوت، محدودهٔ دید و URLهای data/send است که از blade پاس داده می‌شود:
 *
 *  - مدیر کل (role:admin): همهٔ گفتگوها — /admin/chats
 *  - مدیر کافی‌نت (role:coffeenet_manager): فقط گفتگوی سفارش‌های کافی‌نت جاری — /coffeenet/{net}/chats
 *
 * ارسال پیام: مدیر کل و مدیر کافی‌نت هم می‌توانند در گفتگو پیام بفرستند.
 */
class OrderChatController extends Controller
{
    protected array $lastMessageMap = [];

    public function __construct(protected ChatService $chat) {}

    /* ================== ادمین کل ================== */

    /** GET /admin/chats — فهرست گفتگوها */
    public function adminIndex(Request $request): View
    {
        return $this->renderIndex($request, null, 'پنل مدیریت کل', 'back.layouts.panel');
    }

    /** GET /admin/chats/data — فهرست AJAX */
    public function adminData(Request $request): JsonResponse
    {
        return $this->dataResponse($request, null);
    }

    /** GET /admin/orders/{order}/chat — صفحهٔ گفتگو */
    public function adminShow(Request $request, Order $order): View
    {
        return $this->renderShow($request, $order, null, 'back.layouts.panel', 'پنل مدیریت کل');
    }

    /** GET /admin/orders/{order}/chat/data — پولینگ پیام‌ها */
    public function adminMessages(Request $request, Order $order): JsonResponse
    {
        return $this->messagesResponse($request, $order);
    }

    /** POST /admin/orders/{order}/chat/send — ارسال پیام */
    public function adminSend(Request $request, Order $order): JsonResponse
    {
        return $this->sendResponse($request, $order);
    }

    /* ================== مدیر کافی‌نت ================== */

    /** GET /coffeenet/{coffeenet}/chats — فهرست گفتگوهای کافی‌نت جاری */
    public function coffeenetIndex(Request $request, Coffeenet $coffeenet): View
    {
        $this->assertSameCoffeenet($request, $coffeenet);

        return $this->renderIndex($request, $coffeenet, 'پنل کافی‌نت', 'back.coffeenet.layouts.panel');
    }

    /** GET /coffeenet/{coffeenet}/chats/data — فهرست AJAX */
    public function coffeenetData(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        $this->assertSameCoffeenet($request, $coffeenet);

        return $this->dataResponse($request, $coffeenet);
    }

    /** GET /coffeenet/{coffeenet}/orders/{order}/chat — صفحهٔ گفتگو */
    public function coffeenetShow(Request $request, Coffeenet $coffeenet, Order $order): View
    {
        $this->assertSameCoffeenet($request, $coffeenet);
        $this->assertOrderOfCoffeenet($order, $coffeenet);

        return $this->renderShow($request, $order, $coffeenet, 'back.coffeenet.layouts.panel', 'پنل کافی‌نت');
    }

    /** GET /coffeenet/{coffeenet}/orders/{order}/chat/data — پولینگ پیام‌ها */
    public function coffeenetMessages(Request $request, Coffeenet $coffeenet, Order $order): JsonResponse
    {
        $this->assertSameCoffeenet($request, $coffeenet);
        $this->assertOrderOfCoffeenet($order, $coffeenet);

        return $this->messagesResponse($request, $order);
    }

    /** POST /coffeenet/{coffeenet}/orders/{order}/chat/send — ارسال پیام */
    public function coffeenetSend(Request $request, Coffeenet $coffeenet, Order $order): JsonResponse
    {
        $this->assertSameCoffeenet($request, $coffeenet);
        $this->assertOrderOfCoffeenet($order, $coffeenet);

        return $this->sendResponse($request, $order);
    }

    /* ================== منطق مشترک ================== */

    protected function assertSameCoffeenet(Request $request, Coffeenet $coffeenet): void
    {
        $session = $request->attributes->get('current_coffeenet');
        abort_unless($session instanceof Coffeenet && (int) $session->id === (int) $coffeenet->id, 403,
            'کافی‌نت مسیر با جلسه فعلی شما مطابقت ندارد.');
    }

    protected function assertOrderOfCoffeenet(Order $order, Coffeenet $coffeenet): void
    {
        abort_unless((int) $order->coffeenet_id === (int) $coffeenet->id, 404, 'سفارش یافت نشد.');
    }

    protected function renderIndex(Request $request, ?Coffeenet $coffeenet, string $panelLabel, string $layout): View
    {
        $base = $coffeenet ? "/coffeenet/{$coffeenet->id}/chats" : '/admin/chats';

        return view('back.operator.chat.index', array_merge([
            'chatLayout' => $layout,
            'chatPanelLabel' => $panelLabel,
            'chatBase' => $base,
        ], $coffeenet
            ? ['coffeenet' => $coffeenet, 'user' => $request->user()]
            : ['coffeenet' => new Coffeenet(['name' => 'همهٔ کافی‌نت‌ها']), 'user' => $request->user()]));
    }

    protected function dataResponse(Request $request, ?Coffeenet $coffeenet): JsonResponse
    {
        $query = Order::query()
            ->whereHas('conversation')
            ->when($coffeenet, fn ($q) => $q->where('coffeenet_id', $coffeenet->id))
            ->with([
                'service' => fn ($q) => $q->select(['id', 'name']),
                'customer' => fn ($q) => $q->select(['id', 'name', 'family']),
                'operator' => fn ($q) => $q->select(['id', 'name', 'family']),
                'conversation:id,order_id',
            ]);

        $filter = (string) $request->query('filter', 'active');
        if ($filter === 'active') {
            $query->whereIn('status', OrderStatus::chattableValues());
        } elseif ($filter === 'done') {
            $query->whereIn('status', [OrderStatus::Delivered->value, OrderStatus::Completed->value]);
        } else {
            $filter = 'all';
        }

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%")->orWhere('family', 'like', "%{$q}%"));
            });
        }

        // آخرین پیامِ هر گفتگو در یک کوئری
        $rows = $query->get();
        $this->lastMessageMap = [];
        $convIds = $rows->pluck('conversation.id')->filter()->values()->all();
        if ($convIds) {
            $this->lastMessageMap = Message::query()
                ->selectRaw('conversation_id, MAX(id) as mid')
                ->whereIn('conversation_id', $convIds)
                ->groupBy('conversation_id')
                ->pluck('mid', 'conversation_id')
                ->all();
        }

        $rows = $rows->sortByDesc(fn (Order $order) => (int) ($this->lastMessageMap[$order->conversation?->id] ?? 0))->values();

        $unseen = $this->unseenByOrder($coffeenet?->id);

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

    protected function renderShow(Request $request, Order $order, ?Coffeenet $coffeenet, string $layout, string $panelLabel): View
    {
        abort_unless($this->chat->canView($order), 404, 'گفتگویی برای این سفارش وجود ندارد.');

        $prefix = $coffeenet ? "/coffeenet/{$coffeenet->id}" : '/admin';
        $base = $coffeenet ? "/coffeenet/{$coffeenet->id}/chats" : '/admin/chats';

        // عملیات‌های سریع وضعیت برای مدیر کل / مدیر کافی‌نت (همهٔ گذارهای مجاز)
        $statusActions = $this->staffStatusActions($order);

        return view('back.operator.chat.show', array_merge([
            'order' => $order->load([
                'service' => fn ($q) => $q->select(['id', 'name']),
                'customer' => fn ($q) => $q->select(['id', 'name', 'family']),
                'operator' => fn ($q) => $q->select(['id', 'name', 'family']),
            ]),
            'canUpdateStatus' => true, // تغییر وضعیت از پنل مدیریتی هم فعال است
            'statusActions' => $statusActions,
            'chatMeta' => $this->chat->chatMeta($order),
            'chatLayout' => $layout,
            'chatPanelLabel' => $panelLabel,
            'chatUrls' => [
                'data' => "{$prefix}/orders/{$order->id}/chat/data",
                'send' => "{$prefix}/orders/{$order->id}/chat/send",
                'status' => "{$prefix}/orders/{$order->id}/status",
                'list' => $base,
            ],
        ], $coffeenet
            ? ['coffeenet' => $coffeenet, 'user' => $request->user()]
            : ['user' => $request->user()]));
    }

    /**
     * نقشهٔ دکمه‌های تغییر وضعیت برای مدیر کل / مدیر کافی‌نت.
     * بر اساس گذارهای مجاز ماشین وضعیت + قواعد پرداخت.
     *
     * @return array<int, array{0:string,1:string,2:bool}> [status, label, نیاز به دلیل]
     */
    protected function staffStatusActions(Order $order): array
    {
        $actions = [];
        $isPaid = (bool) $order->paid_at;

        foreach ($order->status->allowedTransitions() as $to) {
            // گذارهای غیرمرتبط با جریان کاری را نمایش نمی‌دهیم
            $label = match ($to) {
                OrderStatus::InProgress => $isPaid ? 'شروع/ادامه کار' : null,
                OrderStatus::NeedsInfo => 'نیازمند اطلاعات',
                OrderStatus::Delivered => $isPaid ? 'تحویل شد' : null,
                OrderStatus::Completed => 'تکمیل نهایی',
                OrderStatus::Cancelled => 'عدم امکان انجام',
                OrderStatus::Paid => null, // پرداخت فقط از سمت مشتری
                OrderStatus::Refunded => 'بازگشت وجه',
                default => null,
            };

            if ($label !== null) {
                $needsReason = $to === OrderStatus::Cancelled || $to === OrderStatus::Refunded;
                $actions[] = [$to->value, $label, $needsReason];
            }
        }

        return $actions;
    }

    protected function messagesResponse(Request $request, Order $order): JsonResponse
    {
        $conversation = $order->conversation()->first();

        if (! $conversation) {
            return response()->json($this->chat->payload($order, [], $request->user()->id));
        }

        $afterId = (int) $request->query('after_id', 0);
        $messages = $this->chat->messages($conversation, $afterId ?: null);

        // دیدن گفتگو از سمت پشتیبانی = دیده‌شدن پیام‌های مشتری
        $this->chat->markSeen($conversation, 'operator');

        return response()->json($this->chat->payload($order, $messages, $request->user()->id));
    }

    protected function sendResponse(Request $request, Order $order): JsonResponse
    {
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

            $message->loadMissing('sender:id,name,family');

            return response()->json([
                'message' => 'ارسال شد.',
                'data' => $this->chat->serializeMessage($message, $request->user()->id, $order),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'خطا در ارسال پیام.'], 500);
        }
    }

    protected function rowPayload(Order $order, array $unseen): array
    {
        $last = null;
        $lastMsgId = $order->conversation ? ($this->lastMessageMap[$order->conversation->id] ?? null) : null;
        if ($lastMsgId) {
            $last = Message::query()->find($lastMsgId);
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
            'operator_name' => $order->operator ? trim(($order->operator->name ?? '').' '.($order->operator->family ?? '')) : null,
            'status' => [
                'value' => $order->status->value,
                'label' => $order->status->label(),
                'color' => $order->status->color(),
            ],
            'last_preview' => mb_substr($preview, 0, 90),
            'last_type' => $previewType,
            'last_time_fa' => $last?->created_at ? jdate($last->created_at)->format('H:i') : '—',
            'unseen' => (int) ($unseen[$order->id] ?? 0),
        ];
    }

    /** شمارش پیام‌های خوانده‌نشدهٔ مشتری، به تفکیک سفارش */
    protected function unseenByOrder(?int $coffeenetId): array
    {
        $query = Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('orders', 'orders.id', '=', 'conversations.order_id')
            ->whereNull('messages.seen_at')
            ->whereColumn('messages.sender_id', 'orders.customer_id')
            ->when($coffeenetId, fn ($q) => $q->where('orders.coffeenet_id', $coffeenetId));

        return $query->groupBy('orders.id')
            ->selectRaw('orders.id as oid, count(*) as c')
            ->pluck('c', 'oid')
            ->map(fn ($c) => (int) $c)
            ->all();
    }
}
