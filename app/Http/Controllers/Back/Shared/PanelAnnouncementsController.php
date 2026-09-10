<?php

namespace App\Http\Controllers\Back\Shared;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Announcements\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * فاز ۱۵ — اطلاعیه‌های پنل‌ها (مشترک بین هر ۴ پنل).
 *
 * در هر پنل روت‌های announcements/pending و announcements/read ثبت شده‌اند؛
 * layout همان پنل partial مشترک را لود می‌کند و مودال زیبا می‌سازد.
 */
class PanelAnnouncementsController extends Controller
{
    /** GET {panel}/announcements/pending — اطلاعیه‌های دیده‌نشده کاربر جاری */
    public function pending(Request $request, AnnouncementService $announcements): JsonResponse
    {
        $items = $announcements->pendingFor($request->user())
            ->map(fn (Announcement $a) => $a->toPayload());

        return response()->json([
            'data' => $items,
        ]);
    }

    /** POST {panel}/announcements/{announcement}/read */
    public function read(Request $request, Announcement $announcement, AnnouncementService $announcements): JsonResponse
    {
        $announcements->markRead($announcement, $request->user());

        return response()->json([
            'message' => 'اطلاعیه دیده شد.',
        ]);
    }
}
