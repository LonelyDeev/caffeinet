<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * اعلان‌های درون‌برنامه‌ای مشتری (فاز ۱۰).
 *
 * GET  /api/v1/notifications — لیست صفحه‌بندی‌شده
 * GET  /api/v1/notifications/badge — شمار خوانده‌نشده (پولینگ زنگ)
 * POST /api/v1/notifications/read — علامت‌گذاری خوانده‌شده ({id} یا همه)
 */
class NotificationsController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->notifications->list(
            $request->user(),
            type: (string) $request->query('type') ?: null,
            unreadOnly: $request->boolean('unread'),
            perPage: 15,
        );

        return response()->json($paginator);
    }

    public function badge(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->notifications->badge($request->user()),
            'ts' => now()->timestamp,
        ]);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'uuid'],
        ]);

        $count = $this->notifications->markRead($request->user(), $data['id'] ?? null);

        return response()->json([
            'message' => $count > 0
                ? ($data['id'] ?? null ? 'اعلان خوانده‌شده علامت‌گذاری شد.' : 'همهٔ اعلان‌ها خوانده‌شده علامت‌گذاری شدند.')
                : 'اعلان خوانده‌نشده‌ای نبود.',
            'count' => $count,
        ]);
    }
}
