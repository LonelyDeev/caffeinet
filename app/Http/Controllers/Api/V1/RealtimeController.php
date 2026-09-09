<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Realtime\PusherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Realtime پوشر (فاز ۱۳) — پیکربندی کلاینت اپ مشتری.
 *
 * اپ مشتری صفحاتش بدون نشست رندر می‌شوند (احراز با توکن)، پس
 * پیکربندی پوشر از این اندپوینت خوانده می‌شود:
 *   GET /api/v1/realtime/config
 *     → {enabled, key, cluster, channel} — channel مخصوص کاربر جاری
 */
class RealtimeController extends Controller
{
    public function config(Request $request, PusherService $pusher): JsonResponse
    {
        $cfg = $pusher->clientConfig($request->user());

        return response()->json([
            'enabled' => $cfg['enabled'],
            'key' => $cfg['key'],
            'cluster' => $cfg['cluster'],
            'channel' => $cfg['channel'],
            'event' => 'notif.new',
        ]);
    }
}
