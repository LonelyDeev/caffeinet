<?php

namespace App\Services\Sms;

use App\Models\SmsLog;
use App\Services\Settings\SettingsService;
use App\Services\Sms\Drivers\FraasmsDriver;
use App\Services\Sms\Drivers\KavenegarDriver;
use App\Services\Sms\Drivers\LogDriver;
use Throwable;

/**
 * مدیریت ارسال پیامک — پرووایدر از تنظیمات پنل انتخاب می‌شود (log | fraasms | kavenegar).
 * همه ارسال‌ها در sms_logs ثبت می‌شوند.
 *
 * v10 — «پیش‌فرض پترن»:
 * سرویس‌دهنده‌های ایرانی پیامک با متن آزاد را دیگر قبول نمی‌کنند؛
 * استراتژی ارسال:
 *   ۱. اگر قالب pattern_code داشته باشد و درایور پترن پشتیبانی کند → ارسال پترنی
 *   ۲. خطای پترن:
 *      - درایور پترن‌محور خالص (فراز) → ثبت خطا؛ «هرگز» متن آزاد ارسال نمی‌شود
 *      - درایور دوگانه (کاوه‌نگار/لاگ) → افت به ارسال متنی (fallback)
 *   ۳. قالب بدون پترن → درایور پترن‌محور خالص = خطای واضح در sms_logs؛
 *      درایور دوگانه = ارسال متنی
 */
class SmsManager
{
    public function __construct(
        protected SettingsService $settings,
        protected SmsTemplateService $templates,
    ) {}

    public function driver(): SmsDriver
    {
        $provider = (string) $this->settings->get('sms.provider', 'log');

        return match ($provider) {
            'fraasms' => new FraasmsDriver(
                apiKey: (string) $this->settings->get('sms.fraasms.api_key', ''),
                sender: (string) $this->settings->get('sms.fraasms.sender', ''),
                endpoint: (string) $this->settings->get('sms.fraasms.endpoint', FraasmsDriver::DEFAULT_PATTERN_ENDPOINT),
            ),
            'kavenegar' => new KavenegarDriver(
                apiKey: (string) $this->settings->get('sms.kavenegar.api_key', ''),
                sender: (string) $this->settings->get('sms.kavenegar.sender', ''),
                endpoint: (string) $this->settings->get('sms.kavenegar.endpoint', 'https://api.kavenegar.com'),
            ),
            default => new LogDriver(),
        };
    }

    /** لیست پرووایدرهای قابل انتخاب در پنل */
    public static function providers(): array
    {
        return [
            'log' => 'بدون ارسال واقعی — فقط لاک (محیط توسعه)',
            'kavenegar' => 'کاوه‌نگار (Kavenegar)',
            'fraasms' => 'فراز اس‌ام‌اس (ایران‌پیامک — پترن‌محور)',
        ];
    }

    /**
     * ارسال پیامک متنی و ثبت لاگ (مسیر پایه — برای درایورهای دوگانه).
     *
     * @return array{ok: bool, status: string, error?: string}
     */
    public function send(string $mobile, string $message, ?string $templateKey = null): array
    {
        $driver = $this->driver();

        try {
            $response = $driver->send($mobile, $message);

            SmsLog::create([
                'mobile' => $mobile,
                'template_key' => $templateKey,
                'message' => $message,
                'provider' => $driver->name(),
                'status' => 'sent',
                'response' => $response,
                'created_at' => now(),
            ]);

            return ['ok' => true, 'status' => 'sent', 'mode' => 'plain'];
        } catch (Throwable $e) {
            SmsLog::create([
                'mobile' => $mobile,
                'template_key' => $templateKey,
                'message' => $message,
                'provider' => $driver->name(),
                'status' => 'failed',
                'response' => ['error' => $e->getMessage()],
                'created_at' => now(),
            ]);

            return ['ok' => false, 'status' => 'failed', 'error' => $e->getMessage(), 'mode' => 'plain'];
        }
    }

    /**
     * ارسال قالبی — «پیش‌فرض پترن» (v10).
     *
     * جریان:
     *   قالب + pattern_code + درایور پترن‌دار → sendPattern
     *   خطای پترن → فقط اگر درایور متن آزاد را می‌پذیرد (allowsPlainFallback)
     *               به ارسال متنی می‌افتد؛ در غیر این صورت خطا ثبت می‌شود.
     *   قالب بدون pattern_code → فراز: خطای راهنما (ثبت پترن لازم است)؛
     *               کاوه‌نگار/لاگ: ارسال متنی رندرشده.
     *   قالب غیرفعال/ناموجود → $fallback (اگر بود) به‌صورت متنی.
     *
     * @param  array<string, string|int|float>  $vars متغیرهای قالب
     * @return array{ok: bool, status: string, mode?: string, error?: string}
     */
    public function sendTemplate(string $mobile, string $templateKey, array $vars = [], ?string $fallback = null): array
    {
        $template = $this->templates->find($templateKey);

        if (! $template) {
            if ($fallback === null) {
                return ['ok' => false, 'status' => 'skipped', 'error' => 'قالب غیرفعال یا ناموجود'];
            }

            return $this->send($mobile, $fallback, $templateKey);
        }

        $body = $this->templates->compose($templateKey, $vars, $fallback);

        if ($body === null) {
            return ['ok' => false, 'status' => 'skipped', 'error' => 'قالب غیرفعال یا متن خالی'];
        }

        $driver = $this->driver();
        $pattern = trim((string) ($template->pattern_code ?? ''));
        $supportsPattern = $driver->supportsPatterns() && method_exists($driver, 'sendPattern');

        // ─── ۱) مسیر پیش‌فرض: ارسال پترنی ───
        if ($pattern !== '' && $supportsPattern) {
            $tokens = $this->patternAttributes($template, $vars);

            try {
                $response = $driver->sendPattern($mobile, $pattern, $tokens);

                SmsLog::create([
                    'mobile' => $mobile,
                    'template_key' => $templateKey,
                    'message' => $body,
                    'provider' => $driver->name(),
                    'status' => 'sent',
                    'response' => $response + ['mode' => 'pattern', 'pattern' => $pattern],
                    'created_at' => now(),
                ]);

                return ['ok' => true, 'status' => 'sent', 'mode' => 'pattern'];
            } catch (Throwable $e) {
                // درایور پترن‌محور خالص (فراز): خطا = توقف؛ متن آزاد ارسال نمی‌شود
                if (! $driver->allowsPlainFallback()) {
                    SmsLog::create([
                        'mobile' => $mobile,
                        'template_key' => $templateKey,
                        'message' => $body,
                        'provider' => $driver->name(),
                        'status' => 'failed',
                        'response' => [
                            'mode' => 'pattern',
                            'pattern' => $pattern,
                            'error' => $e->getMessage(),
                            'note' => 'درایور پترن‌محور — ارسال متنی مجاز نیست',
                        ],
                        'created_at' => now(),
                    ]);

                    return ['ok' => false, 'status' => 'failed', 'mode' => 'pattern', 'error' => $e->getMessage()];
                }

                // درایور دوگانه (کاوه‌نگار/لاگ): خطای پترن ثبت می‌شود، سپس افت به متن
                SmsLog::create([
                    'mobile' => $mobile,
                    'template_key' => $templateKey,
                    'message' => $body,
                    'provider' => $driver->name(),
                    'status' => 'failed',
                    'response' => [
                        'mode' => 'pattern',
                        'pattern' => $pattern,
                        'error' => $e->getMessage(),
                        'note' => 'خطای پترن — افت به ارسال متنی',
                    ],
                    'created_at' => now(),
                ]);
            }
        }

        // ─── ۲) بدون پترن در درایور پترن‌محور خالص → خطای راهنما (متن آزاد قبول نیست) ───
        if ($pattern === '' && ! $driver->allowsPlainFallback() && $driver->supportsPatterns()) {
            SmsLog::create([
                'mobile' => $mobile,
                'template_key' => $templateKey,
                'message' => $body,
                'provider' => $driver->name(),
                'status' => 'failed',
                'response' => [
                    'mode' => 'pattern',
                    'error' => 'کد پترن برای قالب «'.$templateKey.'» ثبت نشده است؛ پرووایدر فعال پیامک متنی نمی‌پذیرد.',
                    'note' => 'کد پترن را در مرکز پیامک (پنل مدیریت) ثبت کنید',
                ],
                'created_at' => now(),
            ]);

            return [
                'ok' => false,
                'status' => 'failed',
                'mode' => 'pattern',
                'error' => 'کد پترن قالب «'.$templateKey.'» ثبت نشده است؛ در مرکز پیامک ثبت کنید.',
            ];
        }

        // ─── ۳) ارسال متنی (کاوه‌نگار/لاگ یا افت از خطای پترن) ───
        return $this->send($mobile, $body, $templateKey);
    }

    /**
     * متغیرهای نام‌دار پترن — مقادیر vars به ترتیب تعریف متغیرها در قالب
     * (نام متغیرها باید با متغیرهای پترن پنل پیامک یکی باشد).
     *
     * @param  array<string, string|int|float>  $vars
     * @return array<string, string>
     */
    protected function patternAttributes(\App\Models\SmsTemplate $template, array $vars): array
    {
        // ترتیب نام‌ها از رشتهٔ variables قالب استخراج می‌شود ({name} ...)
        preg_match_all('/\{[a-zA-Z0-9_.]+\}/u', (string) $template->variables, $matches);

        $attributes = [];
        foreach ($matches[0] ?? [] as $placeholder) {
            $name = trim($placeholder, '{}');
            $attributes[$name] = (string) ($vars[$name] ?? '');
        }

        // اگر چیزی استخراج نشد، خودِ vars به‌صورت نام‌دار
        if ($attributes === []) {
            $attributes = array_map(fn ($v) => (string) $v, $vars);
        }

        return array_filter($attributes, fn ($v) => $v !== '');
    }
}
