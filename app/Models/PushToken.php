<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * توکن نوتیف دستگاه یک کاربر — v25/v26.
 *
 * هر دستگاه/مرورگری که «نوتیف دستگاه» را فعال کند این‌جا ثبت می‌شود:
 *  • provider=firebase → token توکن FCM (FcmPushService)
 *  • provider=webpush  → token همان endpoint + p256dh/auth کلیدهای
 *    اشتراک (WebPushService — سرویس پیش‌فرض داخلی)
 *  • provider=pusher   → token شناسهٔ دستگاه Beams (PusherBeamsService)
 *
 * توکن‌های نامعتبر (404/410) خودکار حذف می‌شوند.
 */
class PushToken extends Model
{
    protected $fillable = [
        'user_id', 'token', 'provider', 'p256dh', 'auth',
        'platform', 'user_agent', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** برچسب فارسی سرویس نوتیف دستگاه */
    public function providerLabel(): string
    {
        return match ($this->provider) {
            'webpush' => 'وب‌پوش داخلی',
            'pusher' => 'پوشر Beams',
            'firebase' => 'فایربیس',
            default => '—',
        };
    }

    /** برچسب فارسی پلتفرم (نمایش در پنل) */
    public function platformLabel(): string
    {
        return match ($this->platform) {
            'android' => 'اندروید',
            'ios' => 'iOS',
            'windows' => 'ویندوز',
            'other' => 'سایر',
            default => 'وب',
        };
    }
}
