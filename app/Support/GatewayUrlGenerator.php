<?php

namespace App\Support;

use Illuminate\Routing\UrlGenerator;

/**
 * مولد URL سازگار با گیت‌وی پیش‌نمایش (Sandbox).
 *
 * تمام URLهای تولیدشده (route، url، asset و...) در صورت فعال بودن گیت‌وی
 * پارامتر XTransformPort را حفظ می‌کنند تا ناوبری و فایل‌های استاتیک از
 * مسیر گیت‌وی عبور کنند. در پروداکشن رفتار آن کاملاً عادی است.
 *
 * این کلاس بخشی از زیرساخت پیش‌نمایش است و در پروداکشن قابل حذف است.
 */
class GatewayUrlGenerator extends UrlGenerator
{
    public function to($path, $extra = [], $secure = null)
    {
        return Gateway::append(parent::to($path, $extra, $secure));
    }

    public function route($name, $parameters = [], $absolute = true)
    {
        return Gateway::append(parent::route($name, $parameters, $absolute));
    }

    public function signedRoute($name, $parameters = [], $expiration = null, $absolute = true)
    {
        return Gateway::append(parent::signedRoute($name, $parameters, $expiration, $absolute));
    }

    public function temporarySignedRoute($name, $expiration, $parameters = [], $absolute = true)
    {
        return Gateway::append(parent::temporarySignedRoute($name, $expiration, $parameters, $absolute));
    }

    public function asset($path, $secure = null)
    {
        return Gateway::append(parent::asset($path, $secure));
    }

    public function assetFrom($root, $path, $secure = null)
    {
        return Gateway::append(parent::assetFrom($root, $path, $secure));
    }
}
