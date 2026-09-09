<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * زنگ اعلان پنل‌ها (فاز ۱۰) — یک کنترلر مشترک برای ۴ پیشوند:
 * /admin/notifications/* · /organization/notifications/* ·
 * /coffeenet/{id}/notifications/* · /operator/notifications/*
 *
 * GET  …/notifications/badge — شمار خوانده‌نشده (پولینگ زنگ)
 * GET  …/notifications/data — آخرین اعلان‌ها (دراپ‌داون)
 * POST …/notifications/read — خوانده‌شده ({id} یا همه)
 */
class NotificationsController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function badge(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->notifications->badge($request->user()),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $rows = $this->notifications->latest($request->user(), 10);

        return response()->json([
            'count' => $this->notifications->badge($request->user()),
            'data' => $rows->map(fn ($n) => $this->notifications->format($n))->values(),
        ]);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'uuid'],
        ]);

        $count = $this->notifications->markRead($request->user(), $data['id'] ?? null);

        return response()->json([
            'message' => $count > 0 ? 'خوانده‌شده علامت‌گذاری شد.' : 'اعلان خوانده‌نشده‌ای نبود.',
            'count' => $count,
        ]);
    }
}
