<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * هدرهای امنیتی + CSP (فاز ۱۱ — Hardening).
 *
 * - nosniff / frame-options / referrer / permissions-policy روی همهٔ پاسخ‌ها
 * - CSP سخت‌گیرانه: فقط اسکریپت‌های خودِ پروژه (تم ضد-FOUC از فایل خارجی
 *   assets/js/theme-boot.js لود می‌شود — v10: هش sha256ِ قبلی HEX بود و
 *   CSP هش را Base64 انتظار دارد؛ اسکریپت درون‌خطی تم بی‌صدا بلاک می‌شد
 *   و ذخیرهٔ تم با رفرش از بین می‌رفت)؛ استایل خود + گوگل‌فونت (Vazirmatn)؛
 *   تصویر/رسانه از self و blob (ضبط/پخش چت)؛ فرم و ناوبری فقط self
 * - دانلود فایل‌ها Content-Disposition inline را CSP نمی‌شکند (object-src none)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(), payment=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // CSP فقط برای پاسخ‌های HTML (JSON/API نیازی ندارد)
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            // فاز ۱۳: با فعال بودن Realtime پوشر، اتصال WS/HTTPS به پوشر مجاز است
            $connectSrc = "'self'";

            try {
                if (app(\App\Services\Realtime\PusherService::class)->enabled()) {
                    $connectSrc .= ' wss://*.pusher.com https://*.pusher.com';
                }
            } catch (\Throwable) {
                // تنظیمات در دسترس نیست — همان self
            }

            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: blob:",
                "media-src 'self' blob:",
                "connect-src {$connectSrc}",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
            ]);

            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
