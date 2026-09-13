<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حضور کاربران (v25 → v37) — به‌روزرسانی last_seen_at برای تشخیص آنلاین بودن.
 *
 * پنل‌ها و اپ مشتری هر ~۲۵-۳۰ ثانیه poll می‌کنند؛ این میدل‌ور روی
 * گروه‌های web و api می‌نشیند و فقط وقتی مقدار قدیمی‌تر از ۱۰ ثانیه باشد
 * UPDATE می‌زند (نوشتن اضافی روی دیتابیس ندارد).
 *
 * v37: فاصلهٔ نوشتن از ۲۰ به ۱۰ ثانیه کاهش یافت تا تشخیص آفلاینِ برنامهٔ
 * بسته سریع‌تر شود. علاوه بر آن، بیکن pagehide (POST /presence-offline
 * از push-client.js) بستن برنامه را «همان لحظه» آفلاین ثبت می‌کند؛
 * این میدل‌ور پشتیبانِ همان لحظه‌هاست.
 */
class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = $request->user();

            if ($user
                && ($user->last_seen_at === null
                    || $user->last_seen_at->lt(now()->subSeconds(10)))) {
                $user->forceFill(['last_seen_at' => now()])->saveQuietly();
            }
        } catch (\Throwable) {
            // حضور هرگز نباید درخواست را متوقف کند
        }

        return $next($request);
    }
}
