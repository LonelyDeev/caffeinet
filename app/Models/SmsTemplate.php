<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * قالب پیامک سیستم (فاز ۱۰ + بازطراحی بازخوردی ۶-۶).
 *
 * body متغیرهای {name} دارد که هنگام ارسال با مقادیر واقعی جایگزین می‌شوند.
 * pattern_code کد پترن ثبت‌شده در پنل پرووایدر است (Kavenegar VerifyLookup / ippanel) —
 * اگر پر باشد و پرووایدر پترن پشتیبانی کند، ارسال پترنی انجام می‌شود؛
 * در غیر این صورت متن رندرشدهٔ body ارسال می‌گردد.
 * اگر قالب غیرفعال باشد یا وجود نداشته باشد، فراخواننده از متن پیش‌فرض کد استفاده می‌کند.
 */
class SmsTemplate extends Model
{
    /** دسته‌های قابل نمایش در مرکز پیامک */
    public const CATEGORIES = [
        'auth' => 'ورود و احراز هویت',
        'order' => 'حرکات سفارش',
        'wallet' => 'کیف پول و مالی',
        'support' => 'پشتیبانی و تیکت',
        'other' => 'سایر',
    ];

    protected $fillable = [
        'key', 'title', 'body', 'variables', 'is_active', 'category', 'pattern_code',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
