<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;

/**
 * درایور توسعه (dev) — پیامک را فقط در لاگ ثبت می‌کند.
 * مقدار sms.provider=log در تنظیمات
 */
class LogDriver implements SmsDriver
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $mobile, string $message): array
    {
        return [
            'simulated' => true,
            'note' => 'پیامک در محیط توسعه فقط ثبت می‌شود (درایور log).',
        ];
    }

    /**
     * ارسال پترنی شبیه‌سازی‌شده (توسعه) — ساختار پاسخ مشابه درایور واقعی
     * تا مسیر پترن‌محور بدون کلید API قابل تست باشد.
     *
     * @param  array<string, string|int|float>  $variables
     */
    public function sendPattern(string $mobile, string $pattern_code, array $variables = []): array
    {
        return [
            'simulated' => true,
            'mode' => 'pattern',
            'pattern' => $pattern_code,
            'attributes' => $variables,
            'note' => 'پیامک پترنی در محیط توسعه فقط ثبت می‌شود (درایور log).',
        ];
    }

    public function supportsPatterns(): bool
    {
        return true;
    }

    public function allowsPlainFallback(): bool
    {
        return true;
    }
}
