<?php

/**
 * هلپرهای سراسری «کافی‌نت آنلاین»
 */

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
