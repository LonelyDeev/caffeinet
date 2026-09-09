<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Http;

/**
 * درایور «فراز اس‌ام‌اس» (ایران‌پیامک — iranpayamak.com) — مطابق درایور مرجع پروژه.
 * مقدار sms.provider=fraasms در تنظیمات.
 *
 * v10 — پترن‌محور خالص: سرویس‌دهنده پیامک با متن آزاد را قبول نمی‌کند؛
 * SmsManager هرگز متن آزاد به این درایور نمی‌فرستد (allowsPlainFallback=false).
 * متد send() فقط برای کامل بودن قرارداد موجود است.
 *
 * الگوی API (v1):
 *   POST {endpoint}  →  https://api.iranpayamak.com/ws/v1/sms/pattern
 *   Headers:  Accept: application/json | Api-Key: {api_key} | Content-Type: application/json
 *   Body:     { code, attributes{نام=>مقدار}, recipient, line_number, number_format: "english" }
 *
 * تنظیمات موردنیاز (از پنل مدیریت → تنظیمات → پیامک):
 *   sms.fraasms.api_key   → کلید API پنل (هدر Api-Key)
 *   sms.fraasms.sender    → شماره خط فرستنده (line_number)
 *   sms.fraasms.endpoint  → آدرس ارسال پترن (پیش‌فرض: https://api.iranpayamak.com/ws/v1/sms/pattern)
 */
class FraasmsDriver implements SmsDriver
{
    /** آدرس پیش‌فرض ارسال پترن */
    public const DEFAULT_PATTERN_ENDPOINT = 'https://api.iranpayamak.com/ws/v1/sms/pattern';

    /** آدرس ارسال متنی ساده (از اندپوینت پترن استخراج می‌شود) */
    public const DEFAULT_SEND_ENDPOINT = 'https://api.iranpayamak.com/ws/v1/sms/send';

    public function __construct(
        protected string $apiKey,
        protected string $sender,
        protected string $endpoint = self::DEFAULT_PATTERN_ENDPOINT,
    ) {}

    public function name(): string
    {
        return 'fraasms';
    }

    /** آیا این درایور ارسال پترنی را پشتیبانی می‌کند؟ */
    public function supportsPatterns(): bool
    {
        return true;
    }

    /**
     * v10 — فراز/ایران‌پیامک پترن‌محور خالص است:
     * سرویس‌دهنده پیامک با متن آزاد را قبول نمی‌کند؛
     * خطای پترن = ثبت خطا (بدون افتادن به ارسال متنی).
     */
    public function allowsPlainFallback(): bool
    {
        return false;
    }

    /**
     * ارسال پترن‌محور — دقیقاً مطابق درایور مرجع:
     * code=کد پترن، attributes=متغیرهای «نام‌دار» پترن، recipient=موبایل، line_number=خط فرستنده.
     *
     * @param  string                                    $patternCode کد پترن پنل فراز
     * @param  array<string, string|int|float|nullable>  $attributes  متغیرهای نام‌دار (کلید = نام متغیر در پترن)
     * @return array payload پاسخ پرووایدر
     */
    public function sendPattern(string $mobile, string $patternCode, array $attributes = []): array
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('کلید API فراز اس‌ام‌اس (Api-Key) در تنظیمات ثبت نشده است.');
        }

        if (trim($patternCode) === '') {
            throw new \RuntimeException('کد پترن برای ارسال پترنی فراز ثبت نشده است.');
        }

        $body = [
            'code' => $patternCode,
            'attributes' => collect($attributes)
                ->map(fn ($v) => (string) $v)
                ->filter(fn ($v) => $v !== '')
                ->all(),
            'recipient' => $mobile,
            'line_number' => $this->sender,
            'number_format' => 'english',
        ];

        $response = Http::timeout(15)
            ->connectTimeout(8)
            ->withHeaders([
                'Accept' => 'application/json',
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->patternEndpoint(), $body);

        if (! $response->successful()) {
            throw new \RuntimeException('پاسخ HTTP '.$response->status().' از فراز اس‌ام‌اس دریافت شد: '.mb_substr((string) $response->getBody(), 0, 300));
        }

        $payload = $response->json() ?? ['raw' => $response->body()];
        $this->assertSuccess($payload);

        return $payload;
    }

    /**
     * ارسال متنی ساده (بدون پترن) — اندپوینت /ws/v1/sms/send.
     *
     * @return array payload پاسخ پرووایدر
     */
    public function send(string $mobile, string $message): array
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('کلید API فراز اس‌ام‌اس (Api-Key) در تنظیمات ثبت نشده است.');
        }

        $response = Http::timeout(15)
            ->connectTimeout(8)
            ->withHeaders([
                'Accept' => 'application/json',
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->sendEndpoint(), [
                'text' => $message,
                'recipients' => [$mobile],
                'line_number' => $this->sender,
                'number_format' => 'english',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('پاسخ HTTP '.$response->status().' از فراز اس‌ام‌اس دریافت شد: '.mb_substr((string) $response->getBody(), 0, 300));
        }

        $payload = $response->json() ?? ['raw' => $response->body()];
        $this->assertSuccess($payload);

        return $payload;
    }

    /** اندپوینت پترن — تنظیم‌شده یا پیش‌فرض */
    protected function patternEndpoint(): string
    {
        $url = trim($this->endpoint) ?: self::DEFAULT_PATTERN_ENDPOINT;

        return str_ends_with($url, '/sms/pattern') ? $url : self::DEFAULT_PATTERN_ENDPOINT;
    }

    /** اندپوینت ارسال متنی — همیشه مسیر /ws/v1/sms/send روی همان هاست */
    protected function sendEndpoint(): string
    {
        $host = parse_url($this->patternEndpoint(), PHP_URL_HOST) ?: 'api.iranpayamak.com';
        $scheme = parse_url($this->patternEndpoint(), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$host.'/ws/v1/sms/send';
    }

    /**
     * بررسی خطای منطقی پاسخ (ساختار رایج: {code:..., message:...} یا {status:...}).
     * پاسخ موفق معمولاً {code: "IR..._..." , data: {...}} یا مشابه آن است.
     *
     * @param  array|mixed  $payload
     */
    protected function assertSuccess($payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        // برخی نسخه‌ها خطا را با status عددی غیرصفر یا فیلد error برمی‌گردانند
        $error = data_get($payload, 'error') ?: data_get($payload, 'message');
        $status = data_get($payload, 'status');

        if (is_numeric($status) && (int) $status !== 0 && (int) $status !== 200) {
            throw new \RuntimeException('خطای فراز اس‌ام‌اس ('.$status.'): '.(string) $error);
        }

        if (is_string($error) && $error !== '' && array_key_exists('error', $payload)) {
            throw new \RuntimeException('خطای فراز اس‌ام‌اس: '.$error);
        }
    }
}
