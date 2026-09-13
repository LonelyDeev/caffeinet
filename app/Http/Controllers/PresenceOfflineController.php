<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * v37 — بیکن «برنامه بسته شد» (حضور آنی).
 *
 * push-client.js روی pagehide/visibilitychange-hidden این اندپوینت را با
 * sendBeacon صدا می‌زند تا کاربر «همان لحظه» آفلاین ثبت شود (به‌جای
 * انتظار برای آستانهٔ ۴۵ ثانیه‌ای).
 *
 * احراز هویت (به‌ترتیب):
 *  ۱) توکن Bearer در هدر (fetch keepalive) یا در بدنهٔ JSON `token`
 *     (sendBeacon نمی‌تواند هدر ست کند) — اپ مشتری.
 *  ۲) نشست وب (کوکی سشن خودکار همراه بیکن می‌آید) — پنل‌ها.
 *
 * مسیر از CSRF معاف است چون:
 *  • مسیر API-مانند است و توکن/سشن خودِ کاربر را می‌خواهد؛
 *  • بدترین حالت سوءاستفاده، آفلاین‌کردنِ وضعیتِ نمایشی خودِ همان کاربر است.
 * اثر جانبی ندارد — فقط last_seen_at را به گذشته برمی‌گرداند تا isOnline()
 * همان لحظه false شود؛ اولین درخواست بعدی (UpdateLastSeen) دوباره آنلاینش می‌کند.
 */
class PresenceOfflineController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = $this->resolveUser($request);

            if (! $user) {
                // بی‌صدا رد — بیکن بدون کاربر هیچ معنایی ندارد
                return response()->json(['ok' => false, 'message' => 'unauthenticated'], 401);
            }

            // آستانه + ۱۰ ثانیه به گذشته → قطعاً «آفلاین» و کمی تلورانس
            $user->forceFill([
                'last_seen_at' => now()->subSeconds(offline_threshold_seconds() + 10),
            ])->saveQuietly();

            return response()->json(['ok' => true]);
        } catch (Throwable) {
            // بیکن هرگز نباید خطا بدهد
            return response()->json(['ok' => false], 200);
        }
    }

    /** کاربر از توکن (هدر/بدنه) یا نشست وب */
    protected function resolveUser(Request $request): ?User
    {
        try {
            // ۱) هدر Authorization: Bearer …
            $token = $request->bearerToken();

            // ۲) توکن داخل بدنه (sendBeacon نمی‌تواند هدر ست کند)
            if (! $token && $request->input('token')) {
                $token = (string) $request->input('token');
            }

            if ($token) {
                $accessToken = PersonalAccessToken::findToken($token);

                $user = $accessToken?->tokenable;

                if ($user instanceof User && $user->exists) {
                    return $user;
                }
            }

            // ۳) نشست وب (پنل‌ها — کوکی سشن همراه بیکن است)
            $sessionUser = $request->user('web');

            return ($sessionUser instanceof User && $sessionUser->exists) ? $sessionUser : null;
        } catch (Throwable) {
            return null;
        }
    }
}
