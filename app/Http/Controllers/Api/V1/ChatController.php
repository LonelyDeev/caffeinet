<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * گفتگوی سفارش مشتری (فاز ۷ — چت تلگرام‌گونه).
 *
 * GET  /api/v1/orders/{order}/messages?after_id= — پولینگ افزایشی
 * POST /api/v1/orders/{order}/messages — ارسال متن (JSON) یا فایل (multipart)
 */
class ChatController extends Controller
{
    public function __construct(protected ChatService $chat) {}

    public function index(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        // سربرگ گفتگو (اطلاعات اپراتور/کافی‌نت متصل)
        $order->loadMissing(['operator:id,name,family', 'coffeenet:id,name']);

        $conversation = $order->conversation()->first();

        if (! $conversation) {
            return response()->json($this->chat->payload($order, [], $request->user()->id));
        }

        $afterId = (int) $request->query('after_id', 0);
        $messages = $this->chat->messages($conversation, $afterId ?: null);

        // پیام‌های اپراتور/سیستمی که همین حالا دیده شدند
        $this->chat->markSeen($conversation, 'customer');

        $payload = $this->chat->payload($order, $messages, $request->user()->id);

        // جمع ناخوانده (پیام‌های سمت اپراتور) — برای نشانگر داخل اپ
        $payload['unseen'] = collect($messages)
            ->where('seen_at', null)
            ->filter(fn ($m) => $m->sender_id && (int) $m->sender_id !== (int) $order->customer_id)
            ->count();

        return response()->json($payload);
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        try {
            $message = $this->chat->send(
                $order,
                $request->user(),
                (string) $request->input('type', 'text'),
                $request->input('content'),
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

    /** سفارشِ خودت یا ۴۰۴ (عدم افشای وجود) */
    protected function authorizeOwner(Request $request, Order $order): void
    {
        if ((int) $order->customer_id !== (int) $request->user()->id) {
            abort(404, 'سفارش یافت نشد.');
        }
    }
}
