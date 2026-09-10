<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Announcements\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * فاز ۱۵ — اطلاعیه‌های اپ مشتری (نمایش مودال + علامت خوانده‌شدن).
 */
class AnnouncementsController extends Controller
{
    /** GET /api/v1/announcements — اطلاعیه‌های دیده‌نشده برای کاربر جاری */
    public function index(Request $request, AnnouncementService $announcements): JsonResponse
    {
        $items = $announcements->pendingFor($request->user())
            ->map(fn (Announcement $a) => $a->toPayload());

        return response()->json([
            'data' => $items,
        ]);
    }

    /** POST /api/v1/announcements/{announcement}/read */
    public function read(Request $request, Announcement $announcement, AnnouncementService $announcements): JsonResponse
    {
        $announcements->markRead($announcement, $request->user());

        return response()->json([
            'message' => 'اطلاعیه به‌عنوان دیده‌شده ثبت شد.',
        ]);
    }
}
