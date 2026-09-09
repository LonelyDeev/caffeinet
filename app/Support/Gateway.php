<?php

namespace App\Support;

/**
 * لایه سازگاری با گیت‌وی پیش‌نمایش (Sandbox).
 *
 * در محیط پیش‌نمایش، درخواست‌ها از طریق یک گیت‌وی (Caddy) عبور می‌کنند و
 * برای رسیدن به پورت این اپلیکیشن باید پارامتر «XTransformPort» در query-string
 * حفظ شود. این کلاس فقط زمانی فعال می‌شود که آن پارامتر در درخواست ورودی
 * وجود داشته باشد؛ در محیط پروداکشن هیچ اثری ندارد و می‌توانید کل این
 * فایل و ارجاعات آن را حذف کنید.
 */
class Gateway
{
    /** نام پارامتر query که گیت‌وی برای مسیریابی استفاده می‌کند */
    public const QUERY_PARAM = 'XTransformPort';

    /**
     * درخواست جاری حاوی پارامتر گیت‌وی است؟
     */
    public static function active(): bool
    {
        return (bool) static::port();
    }

    /**
     * مقدار پورت گیت‌وی (اگر موجود باشد)
     */
    public static function port(): ?string
    {
        $port = request()?->query(static::QUERY_PARAM);

        return is_string($port) && $port !== '' ? $port : null;
    }

    /**
     * افزودن پارامتر گیت‌وی به یک URL (در صورت فعال بودن).
     * اگر URL از قبل پارامتر را داشته باشد، دست نمی‌زند.
     */
    public static function append(string $url): string
    {
        if (! static::active()) {
            return $url;
        }

        if (str_contains($url, static::QUERY_PARAM.'=')) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        // URL هایی مثل «/» یا «/foo» به «/?XTransformPort=8000» تبدیل می‌شوند
        $url = rtrim($url, '?');

        return $url.$separator.static::QUERY_PARAM.'='.rawurlencode((string) static::port());
    }
}
