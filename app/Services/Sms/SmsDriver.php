<?php

namespace App\Services\Sms;

/**
 * قرارداد درایورهای پیامک — پرووایدر از تنظیمات پنل انتخاب می‌شود.
 */
interface SmsDriver
{
    /** نام درایور */
    public function name(): string;

    /**
     * ارسال پیامک — در صورت خطا Exception پرتاب می‌شود.
     *
     * @return array payload پاسخ پرووایدر
     */
    public function send(string $mobile, string $message): array;

    public function sendPattern(string $mobile,string $pattern_code, array $variables): array;

    /**
     * آیا این درایور ارسال پترنی (Pattern/Verify) را پشتیبانی می‌کند؟
     * (درایور لاگ برای توسعه پترن ندارد)
     */
    public function supportsPatterns(): bool;

    /**
     * اگر ارسال پترنی با خطا مواجه شود، آیا «افتادن به ارسال متنی ساده»
     * مجاز است؟ (v10 — سرویس‌دهنده‌های ایرانی متن آزاد را نمی‌پذیرند)
     *
     * فراز/ایران‌پیامک: false — پترن‌محور خالص، خطا = ثبت خطا (بدون متن آزاد)
     * کاوه‌نگار: true — متن آزاد هنوز پذیرفته می‌شود
     * لاگ (توسعه): true — مسیر توسعه نباید قفل شود
     */
    public function allowsPlainFallback(): bool;
}
