<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Http;

/**
 * درایور «ایده‌پردازان» (RestfulSms.com / پیام‌رسان ایده‌پردازان) — مطابق درایور مرجع
 * پروژه (IdehPardazanSms). مقدار sms.provider=idehpardazan در تنظیمات.
 *
 * الگوی API (Ultra Fast Send مستقیم — طبق نمونهٔ پروژه):
 *   POST https://RestfulSms.com/api/UltraFastSend/direct
 *   Body (JSON): { TemplateId, ParameterArray[{Parameter, ParameterValue}], mobile, UserApiKey, SecretKey }
 *
 *   TemplateId       → کد قالب (همان کد پترن قالب در «مرکز پیامک»)
 *   ParameterArray   → متغیرهای «نام‌دار» پترن (Parameter = نام متغیر، ParameterValue = مقدار)
 *   mobile           → شماره گیرنده
 *   UserApiKey/SecretKey → کلیدهای پنل ایده‌پردازان
 *
 * پاسخ: { IsSuccess: true, Message: "...", ... } — خطا: IsSuccess=false + Message
 *
 * تنظیمات موردنیاز (از پنل مدیریت → تنظیمات → پیامک):
 *   sms.idehpardazan.api_key     → UserApiKey پنل
 *   sms.idehpardazan.secret_key  → SecretKey پنل
 *   sms.idehpardazan.endpoint    → آدرس ارسال (پیش‌فرض: https://RestfulSms.com/api/UltraFastSend/direct)
 */
class IdehPardazanDriver implements SmsDriver
{
    /** آدرس پیش‌فرض ارسال قالب سریع (مستقیم با UserApiKey/SecretKey) */
    public const DEFAULT_ENDPOINT = 'https://RestfulSms.com/api/UltraFastSend/direct';

    public function __construct(
        protected string $apiKey,
        protected string $secretKey,
        protected string $endpoint = self::DEFAULT_ENDPOINT,
    ) {}

    public function name(): string
    {
        return 'idehpardazan';
    }

    /** ایده‌پردازان ارسال قالبی (Ultra Fast Send) را پشتیبانی می‌کند */
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
     * ارسال قالبی — مطابق درایور مرجع:
     * TemplateId=کد قالب، ParameterArray=متغیرهای نام‌دار (همان ساختار نمونه).
     *
     * @param  string                                    $templateId کد قالب ایده‌پردازان (TemplateId)
     * @param  array<string, string|int|float|nullable>  $inputData  متغیرهای نام‌دار (کلید = نام متغیر در پترن)
     * @return array payload پاسخ پرووایدر
     */
    public function sendPattern(string $mobile, string $templateId, array $inputData = []): array
    {
        if (trim($this->apiKey) === '' || trim($this->secretKey) === '') {
            throw new \RuntimeException('UserApiKey / SecretKey پنل ایده‌پردازان در تنظیمات ثبت نشده است.');
        }

        if (trim($templateId) === '') {
            throw new \RuntimeException('کد قالب (TemplateId) برای ارسال ایده‌پردازان ثبت نشده است.');
        }

        $parameters = [];

        foreach ($inputData as $name => $value) {
            if ($value === null || (string) $value === '') {
                continue;
            }

            $parameters[] = [
                'Parameter' => (string) $name,
                'ParameterValue' => (string) $value,
            ];
        }

        $response = Http::acceptJson()
            ->timeout(20)
            ->connectTimeout(10)
            ->post(trim($this->endpoint) ?: self::DEFAULT_ENDPOINT, [
                'TemplateId' => $templateId,
                'ParameterArray' => $parameters,
                'mobile' => $mobile,
                'UserApiKey' => $this->apiKey,
                'SecretKey' => $this->secretKey,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('پاسخ HTTP '.$response->status().' از ایده‌پردازان دریافت شد: '.mb_substr((string) $response->getBody(), 0, 300));
        }

        $payload = $response->json() ?? ['raw' => $response->body()];
        $this->assertSuccess($payload);

        return $payload;
    }

    /**
     * ارسال متنی ساده — ایده‌پردازان در این پروژه پترن‌محور است؛
     * این متد فقط برای کامل بودن قرارداد موجود است و همیشه خطا می‌دهد.
     */
    public function send(string $mobile, string $message): array
    {
        throw new \RuntimeException('پرووایدر ایده‌پردازان پترن‌محور است — ارسال متن آزاد پشتیبانی نمی‌شود.');
    }

    /**
     * بررسی خطای منطقی پاسخ: {IsSuccess: bool, Message: string}.
     *
     * @param  array|mixed  $payload
     */
    protected function assertSuccess($payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        $isSuccess = data_get($payload, 'IsSuccess');

        if ($isSuccess !== null && ! (bool) $isSuccess) {
            throw new \RuntimeException('خطای ایده‌پردازان: '.(string) (data_get($payload, 'Message') ?? 'خطای نامشخص پنل'));
        }
    }
}
