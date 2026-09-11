<?php

/**
 * هلپرهای سراسری «کافی‌نت آنلاین»
 */

if (! function_exists('media_url')) {
    /**
     * URL عمومی رسانه‌های دیسک public — از روت /media/{path} (فاز ۲۲).
     * به‌جای /storage (وابسته به symlink) که روی بعضی هاست‌ها 403 می‌دهد.
     * هر سگمنت جداگانه encode می‌شود تا نام‌فایل‌های خاص هم امن بمانند.
     */
    function media_url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        return url('/media/'.implode('/', array_map('rawurlencode', explode('/', $path))));
    }
}

if (! function_exists('fa_digits')) {
    /** تبدیل ارقام لاتین به فارسی */
    function fa_digits(string|int|float|null $value): string
    {
        return strtr((string) $value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
            ',' => '٬',
        ]);
    }
}

if (! function_exists('fa_money')) {
    /**
     * قالب‌بندی مبلغ به تومان با ارقام فارسی.
     *
     * @param  float|int|string  $amount  مبلغ (تومان)
     * @param  bool  $withUnit  درج واحد «تومان»
     */
    function fa_money(float|int|string|null $amount, bool $withUnit = true): string
    {
        $n = (float) $amount;

        $formatted = number_format($n, 0, '.', ',');
        $formatted = fa_digits($formatted);

        return $withUnit ? $formatted.' تومان' : $formatted;
    }
}

if (! function_exists('fa_number')) {
    /** عدد با جداکننده هزارگان فارسی */
    function fa_number(int|float|null $value): string
    {
        return fa_digits(number_format((float) $value, 0, '.', ','));
    }
}

if (! function_exists('en_digits')) {
    /** تبدیل ارقام فارسی/عربی به لاتین (ورودی‌های کاربر) */
    function en_digits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}

if (! function_exists('fa_date') && class_exists(\Morilog\Jalali\Jalalian::class)) {
    /** تاریخ شمسی خوانا از تاریخ میلادی */
    function fa_date(mixed $date, string $format = 'Y/m/d H:i'): ?string
    {
        try {
            $carbon = $date instanceof \Carbon\CarbonInterface
                ? $date
                : (\Carbon\Carbon::parse($date) ?: null);

            return $carbon
                ? \Morilog\Jalali\Jalalian::fromCarbon($carbon)->format($format)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

if (! function_exists('jalali_to_carbon')) {
    /**
     * تبدیل تاریخ شمسی کاربر (مثل «۱۴۰۵/۰۶/۱۲») به Carbon میلادی.
     *
     * @param  string  $date  تاریخ شمسی با ارقام فارسی یا لاتین
     * @param  string  $time  ساعت به‌صورت HH:MM (پیش‌فرض پایان روز)
     */
    function jalali_to_carbon(?string $date, string $time = '23:59'): ?\Carbon\Carbon
    {
        $value = trim(en_digits((string) $date));
        if ($value === '') {
            return null;
        }

        foreach (['Y/m/d', 'Y-m-d', 'Y.m.d'] as $format) {
            try {
                return \Morilog\Jalali\Jalalian::fromFormat($format, $value)
                    ->toCarbon()
                    ->setTimeFromTimeString($time);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}

if (! function_exists('fa_day_name')) {
    /** نام فارسی روز از شم Carbon dayOfWeek (0=یکشنبه … 6=شنبه) */
    function fa_day_name(int $dayOfWeek): string
    {
        return [
            0 => 'یکشنبه',
            1 => 'دوشنبه',
            2 => 'سه‌شنبه',
            3 => 'چهارشنبه',
            4 => 'پنج‌شنبه',
            5 => 'جمعه',
            6 => 'شنبه',
        ][$dayOfWeek] ?? '—';
    }
}

if (! function_exists('jalali_or_iso_to_carbon')) {
    /**
     * ترکیبی: تاریخ شمسی «۱۴۰۵/۰۶/۱۲» (با ساعت اختیاری جدا با فاصله)
     * یا فرمت ISO/datetime-local «2026-09-10T14:30» → Carbon.
     */
    function jalali_or_iso_to_carbon(?string $value, string $defaultTime = '00:00'): ?\Carbon\Carbon
    {
        $value = trim(en_digits((string) $value));
        if ($value === '') {
            return null;
        }

        // فرمت datetime-local یا ISO
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/', $value, $m)) {
            try {
                return \Carbon\Carbon::createFromFormat('Y-m-d H:i', $m[1].' '.$m[2]);
            } catch (\Throwable) {
                return null;
            }
        }

        // شمسی + ساعت اختیاری
        $parts = preg_split('/\s+/', trim($value));
        $date = (string) ($parts[0] ?? '');
        $time = (string) ($parts[1] ?? $defaultTime);
        if (! preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time = $defaultTime;
        }

        return jalali_to_carbon($date, $time);
    }
}
