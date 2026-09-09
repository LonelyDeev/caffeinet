<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Http;

/**
 * درایور «ملی‌پیامک» (Melipayamak) — مطابق درایور مرجع پروژه (MelipayamakSms).
 * مقدار sms.provider=melipayamak در تنظیمات.
 *
 * درایور مرجع از پکیج SOAP (MelipayamakApi->sms('soap')->sendByBaseNumber) استفاده می‌کرد؛
 * این پیاده‌سازی همان فراخوانی را روی REST رسمی ملی‌پیامک انجام می‌دهد
 * (بدون نیاز به پکیج/افزونه SOAP):
 *
 *   POST https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber
 *   Form: username, password, from, to, text, isFlash=false, bodyId
 *
 * «شناسه متن ثابت» (bodyId) همان کد پترن قالب در «مرکز پیامک» است و
 * متن = متغیرهای قالب که «به ترتیب» با «;» به هم می‌چسبند
 * (ارسال با شماره سرویس پایه مشترک — SendByBaseNumber).
 *
 * پاسخ: { Value: "recId;code", RetStatus: 1, StrRetStatus: "Ok" } — RetStatus=1 یعنی موفق.
 *
 * تنظیمات موردنیاز (از پنل مدیریت → تنظیمات → پیامک):
 *   sms.melipayamak.username  → نام کاربری پنل
 *   sms.melipayamak.password  → رمز عبور پنل
 *   sms.melipayamak.from      → شماره خط فرستنده (اختیاری)
 *   sms.melipayamak.endpoint  → آدرس REST (پیش‌فرض: https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber)
 */
class MelipayamakDriver implements SmsDriver
{
    /** آدرس پیش‌فرض ارسال با شماره سرویس پایه (متن ثابت / bodyId) */
    public const DEFAULT_ENDPOINT = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';

    public function __construct(
        protected string $username,
        protected string $password,
        protected string $sender = '',
        protected string $endpoint = self::DEFAULT_ENDPOINT,
    ) {}

    public function name(): string
    {
        return 'melipayamak';
    }

    /** ملی‌پیامک ارسال پترنی (متن ثابت / bodyId) را پشتیبانی می‌کند */
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
     * ارسال پترنی (سرویس پایه مشترک) — مطابق درایور مرجع:
     * bodyId=شناسه متن ثابت، text=مقادیر متغیرها «به ترتیب» با «;» الحاق می‌شوند.
     *
     * @param  string                                    $bodyId    شناسه متن ثابت (کد پترن قالب)
     * @param  array<string, string|int|float|nullable>  $inputData متغیرهای قالب (ترتیب مهم است)
     * @return array payload پاسخ پرووایدر
     */
    public function sendPattern(string $mobile, string $bodyId, array $inputData = []): array
    {
        if (trim($this->username) === '' || trim($this->password) === '') {
            throw new \RuntimeException('نام کاربری/رمز پنل ملی‌پیامک در تنظیمات ثبت نشده است.');
        }

        if (trim($bodyId) === '') {
            throw new \RuntimeException('شناسه متن ثابت (bodyId) برای ارسال ملی‌پیامک ثبت نشده است.');
        }

        $text = implode(';', array_map(fn ($v) => (string) $v, array_filter($inputData, fn ($v) => $v !== null && $v !== '')));

        $response = Http::asForm()
            ->timeout(20)
            ->connectTimeout(10)
            ->post(trim($this->endpoint) ?: self::DEFAULT_ENDPOINT, [
                'username' => $this->username,
                'password' => $this->password,
                'from' => $this->sender,
                'to' => $mobile,
                'text' => $text,
                'isFlash' => 'false',
                'bodyId' => $bodyId,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('پاسخ HTTP '.$response->status().' از ملی‌پیامک دریافت شد: '.mb_substr((string) $response->getBody(), 0, 300));
        }

        $payload = $response->json() ?? ['raw' => $response->body()];

        $retStatus = data_get($payload, 'RetStatus');

        if ($retStatus !== null && (int) $retStatus !== 1) {
            throw new \RuntimeException('خطای ملی‌پیامک ('.$retStatus.'): '.(string) (data_get($payload, 'StrRetStatus') ?? data_get($payload, 'Message') ?? 'خطای نامشخص'));
        }

        return $payload;
    }

    /**
     * ارسال متنی ساده — ملی‌پیامک در این پروژه پترن‌محور است؛
     * این متد فقط برای کامل بودن قرارداد موجود است و همیشه خطا می‌دهد.
     */
    public function send(string $mobile, string $message): array
    {
        throw new \RuntimeException('پرووایدر ملی‌پیامک پترن‌محور است — ارسال متن آزاد پشتیبانی نمی‌شود.');
    }
}
