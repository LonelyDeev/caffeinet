<?php

namespace App\Support;

use App\Models\PushToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * ثبت هماهنگ اشتراک‌های نوتیف دستگاه — v26.1.
 * ------------------------------------------------------------------
 * منطق مشترک «ثبت توکن» بین اپ مشتری (API) و ۴ پنل (Web):
 *  • upsert امن در برابر رِیس (ایندکس یونیک token → retry)
 *  • هره‌سازی توکن‌های مردهٔ همان مرورگر (پلتفرم + UA یکسان)
 *    که بیش از STALE_HOURS به‌روز نشده‌اند
 *  • سقف MAX_PER_PROVIDER توکن برای هر (کاربر × سرویس) —
 *    تا هیچ کلاینت معیوبی جدول push_tokens را پر نکند
 *
 * ریشهٔ باگ: برخی مرورگرهای موبایل applicationServerKey را null
 * برمی‌گردانند و کلاینت قدیمی با هر رفرش یک اشتراک جدید می‌ساخت؛
 * این کلاس اثر هر کلاینت معیوب را نیز سقف‌دار می‌کند.
 */
class PushTokenSync
{
    /** سقف توکن هر کاربر برای هر سرویس (محافظ در برابر کلاینت معیوب) */
    public const MAX_PER_PROVIDER = 15;

    /** توکن همان مرورگر پس از این ساعت (بدون به‌روزرسانی) «مرده» فرض می‌شود */
    public const STALE_HOURS = 24;

    /**
     * ثبت/به‌روزرسانی توکن + هره‌سازی توکن‌های مرده.
     *
     * @param  array{token:string, provider?:string, p256dh?:string|null, auth?:string|null, platform?:string}  $data
     */
    public static function register(User $user, array $data, ?string $userAgent): PushToken
    {
        $provider = (string) ($data['provider'] ?? 'firebase');
        $tokenStr = (string) $data['token'];

        $token = self::upsert($user, $data, $provider, $tokenStr, $userAgent);

        try {
            self::prune($user, $provider, $token);
        } catch (Throwable) {
            // هره‌سازی هرگز ثبت را نمی‌شکند
        }

        return $token;
    }

    /** upsert روی کلید یونیک token — با retry در برابر رِیس هم‌زمانی */
    protected static function upsert(User $user, array $data, string $provider, string $tokenStr, ?string $userAgent): PushToken
    {
        $attributes = [
            'user_id' => (int) $user->id,
            'provider' => $provider,
            'p256dh' => $provider === 'webpush' ? ($data['p256dh'] ?? null) : null,
            'auth' => $provider === 'webpush' ? ($data['auth'] ?? null) : null,
            'platform' => $data['platform'] ?? 'web',
            'user_agent' => mb_substr((string) $userAgent, 0, 500),
            'last_used_at' => now(),
        ];

        try {
            return PushToken::updateOrCreate(['token' => $tokenStr], $attributes);
        } catch (QueryException) {
            // دو درخواست هم‌زمان هر دو INSERT زدند → سطر قبلاً ثبت شده؛ به‌روزرسانی کن
            return PushToken::updateOrCreate(['token' => $tokenStr], $attributes);
        }
    }

    /** حذف توکن‌های مرده/مازاد — سقف‌دار و fail-safe */
    protected static function prune(User $user, string $provider, PushToken $current): void
    {
        // ۱) توکن‌های «همان مرورگر» (پلتفرم + UA یکسان) که به‌روز نشده‌اند:
        //    با رِیسابسکرایب کلاینت‌های قدیمی بی‌دلیل انباشته شده‌اند.
        if ($current->user_agent) {
            PushToken::query()
                ->where('user_id', $user->id)
                ->where('provider', $provider)
                ->where('platform', $current->platform)
                ->where('user_agent', $current->user_agent)
                ->where('id', '!=', $current->id)
                ->where('updated_at', '<', now()->subHours(self::STALE_HOURS))
                ->delete();
        }

        // ۲) سقف‌گذاری: فقط MAX_PER_PROVIDER توکنِ اخیر برای هر (کاربر × سرویس)
        $overflow = PushToken::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->skip(self::MAX_PER_PROVIDER)
            ->take(200)
            ->pluck('id');

        if ($overflow->isNotEmpty()) {
            PushToken::query()->whereIn('id', $overflow)->delete();
        }
    }
}
