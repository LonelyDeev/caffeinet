<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Http;

/**
 * درایور «آی‌پی‌پنل» (ippanel.com) — مطابق درایور مرجع پروژه (IppanelSms).
 * مقدار sms.provider=ippanel در تنظیمات.
 *
 * الگوی API (وب‌سرویس قدیمی پترن پنل):
 *   POST https://ippanel.com/patterns/pattern
 *   Form params: username, password, from, to=["09xxxxxxxxx"], input_data={نام=>مقدار}, pattern_code
 *
 * ارسال «پترن‌محور» است: input_data متغیرهای نام‌دار پترن را می‌گیرد و
 * نام هر متغیر باید با نام تعریف‌شدهٔ پترن در پنل آی‌پی‌پنل یکی باشد.
 *
 * تنظیمات موردنیاز (از پنل مدیریت → تنظیمات → پیامک):
 *   sms.ippanel.username  → نام کاربری پنل
 *   sms.ippanel.password  → رمز عبور پنل
 *   sms.ippanel.from      → شماره خط فرستنده (اختیاری)
 *   sms.ippanel.endpoint  → آدرس ارسال پترن (پیش‌فرض: https://ippanel.com/patterns/pattern)
 */
class IppanelDriver implements SmsDriver
{
    /** آدرس پیش‌فرض ارسال پترن */
    public const DEFAULT_PATTERN_ENDPOINT = 'https://ippanel.com/patterns/pattern';

    public function __construct(
        protected string $username,
        protected string $password,
        protected string $sender = '',
        protected string $endpoint = self::DEFAULT_PATTERN_ENDPOINT,
    ) {}

    public function name(): string
    {
        return 'ippanel';
    }

    /** آی‌پی‌پنل ارسال پترنی را پشتیبانی می‌کند */
    public function supportsPatterns(): bool
    {
        return true;
    }

    /** پترن‌محور: خطای پترن = ثبت خطا (متن آزاد ارسال نمی‌شود) */
    public function allowsPlainFallback(): bool
    {
        return false;
    }

    /**
     * ارسال پترن‌محور — مطابق درایور مرجع:
     * pattern_code=کد پترن، input_data=متغیرهای «نام‌دار»، to=[موبایل].
     *
     * @param  string                                    $patternCode کد پترن پنل آی‌پی‌پنل
     * @param  array<string, string|int|float|nullable>  $inputData   متغیرهای نام‌دار (کلید = نام متغیر در پترن)
     * @return array payload پاسخ پرووایدر
     */
    public function sendPattern(string $mobile, string $patternCode, array $inputData = []): array
    {
        if (trim($this->username) === '' || trim($this->password) === '') {
            throw new \RuntimeException('نام کاربری/رمز پنل آی‌پی‌پنل در تنظیمات ثبت نشده است.');
        }

        if (trim($patternCode) === '') {
            throw new \RuntimeException('کد پترن برای ارسال پترنی آی‌پی‌پنل ثبت نشده است.');
        }

        $response = Http::asForm()
            ->timeout(20)
            ->connectTimeout(10)
            ->post(trim($this->endpoint) ?: self::DEFAULT_PATTERN_ENDPOINT, [
                'username' => $this->username,
                'password' => $this->password,
                'from' => $this->sender,
                'to' => json_encode([$mobile]),
                'input_data' => json_encode(collect($inputData)
                    ->map(fn ($v) => (string) $v)
                    ->filter(fn ($v) => $v !== '')
                    ->all()),
                'pattern_code' => $patternCode,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('پاسخ HTTP '.$response->status().' از آی‌پی‌پنل دریافت شد: '.mb_substr((string) $response->getBody(), 0, 300));
        }

        $payload = $response->json() ?? ['raw' => $response->body()];
        $this->assertSuccess($payload);

        return $payload;
    }

    /**
     * ارسال متنی ساده — آی‌پی‌پنل در این پروژه پترن‌محور است (متن آزاد قابل اتکا نیست)؛
     * این متد فقط برای کامل بودن قرارداد موجود است و همیشه خطا می‌دهد.
     */
    public function send(string $mobile, string $message): array
    {
        throw new \RuntimeException('پرووایدر آی‌پی‌پنل پترن‌محور است — ارسال متن آزاد پشتیبانی نمی‌شود.');
    }

    /**
     * بررسی خطای منطقی پاسخ:
     * ساختار رایج: {status: "IRCB1"|"ERROR", code: 200, message: "...", data: {...}}
     *
     * @param  array|mixed  $payload
     */
    protected function assertSuccess($payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        $status = (string) (data_get($payload, 'status') ?? '');

        if (mb_strtoupper($status) === 'ERROR') {
            throw new \RuntimeException('خطای آی‌پی‌پنل: '.(string) (data_get($payload, 'message') ?? 'خطای نامشخص پنل'));
        }
    }
}
