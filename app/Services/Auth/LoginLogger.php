<?php

namespace App\Services\Auth;

use App\Models\LoginLog;
use App\Models\User;

/**
 * ثبت رخدادهای ورود/خروج کاربران (درخواست بازخوردی — پروفایل مشتری).
 *
 * LoginLogger::log($user, 'login', 'customer')
 * هرگز جریان اصلی را نمی‌شکند (fail-safe).
 */
class LoginLogger
{
    public static function log(User $user, string $event, string $guard = 'customer'): void
    {
        try {
            LoginLog::create([
                'user_id' => $user->id,
                'guard' => $guard,
                'event' => in_array($event, ['login', 'logout', 'forced_logout'], true) ? $event : 'login',
                'ip' => request()?->ip(),
                'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // لاگ ورود هرگز نباید ورود کاربر را متوقف کند
        }
    }
}
