<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حضور کاربران (v25) — به‌روزرسانی last_seen_at برای تشخیص آنلاین بودن.
 *
 * پنل‌ها هر ۳۰ ثانیه و اپ مشتری هر ۲۵ ثانیه poll می‌کنند؛ این میدل‌ور روی
 * گروه‌های web و api می‌نشیند و فقط وقتی مقدار قدیمی‌تر از ۶۰ ثانیه باشد
 * UPDATE می‌زند (نوشتن اضافی روی دیتابیس ندارد).
 */
class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = $request->user();

            if ($user
                && ($user->last_seen_at === null
                    || $user->last_seen_at->lt(now()->subSeconds(60)))) {
                $user->forceFill(['last_seen_at' => now()])->saveQuietly();
            }
        } catch (\Throwable) {
            // حضور هرگز نباید درخواست را متوقف کند
        }

        return $next($request);
    }
}
