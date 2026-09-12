<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * توکن Web Push (FCM) یک دستگاه/مرورگر برای کاربر — v25.
 *
 * ثبت توکن از فرانت (پنل‌ها یا اپ مشتری) انجام می‌شود؛ ارسال پیام
 * با FcmPushService. توکن‌های نامعتبر (404/410) خودکار حذف می‌شوند.
 */
class PushToken extends Model
{
    protected $fillable = [
        'user_id', 'token', 'platform', 'user_agent', 'last_used_at',
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
