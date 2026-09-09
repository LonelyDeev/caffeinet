<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Http;

/**
 * درایور کاوه‌نگار (فاز ۱۰ + پترن بازخوردی ۶-۶) — REST API رسمی.
 * مستندات: https://kavenegar.com/rest.html
 *
 * send()          → ارسال متنی (sms/send.json)
 * sendPattern()   → ارسال پترنی (verify/lookup.json) — کد پترن + توکن‌های نامدار
 */
class KavenegarDriver implements SmsDriver
{
    public function __construct(
        protected string $apiKey,
        protected string $sender = '',
        protected string $endpoint = 'https://api.kavenegar.com',
    ) {}

    public function name(): string
    {
        return 'kavenegar';
    }

    /** آیا این درایور ارسال پترنی را پشتیبانی می‌کند؟ */
    public function supportsPatterns(): bool
    {
        return true;
    }

    /** کاوه‌نگار متن آزاد را هم می‌پذیرد — در خطای پترن، افت به متن مجاز است */
    public function allowsPlainFallback(): bool
    {
        return true;
    }

    public function send(string $mobile, string $message): array
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('کلید API کاوه‌نگار در تنظیمات ثبت نشده است.');
        }

        $response = Http::asForm()
            ->timeout(15)
            ->connectTimeout(8)
            ->post(trim($this->endpoint, '/').'/v1/'.rawurlencode($this->apiKey).'/sms/send.json', array_filter([
                'receptor' => $mobile,
                'message' => $message,
                'sender' => $this->sender ?: null,
            ]));

        if (! $response->successful()) {
            throw new \RuntimeException('پاسخ HTTP '.$response->status().' از کاوه‌نگار دریافت شد.');
        }

        $payload = $response->json();

        // ساختار پاسخ: { Return: { Status, Message }, Entries: [...] }
        $status = data_get($payload, 'Return.Status');

        if ((int) $status !== 200) {
            throw new \RuntimeException('خطای کاوه‌نگار ('.$status.'): '.(string) data_get($payload, 'Return.Message'));
        }

        return [
            'provider_status' => $status,
            'message_id' => data_get($payload, 'Entries.0.MessageId'),
        ];
    }

    /**
     * ارسال پترنی (Verify Lookup) — توکن‌ها باید در پنل کاوه‌نگار با همین نام‌ها تعریف شده باشند.
     *
     * متغیرها «نام‌دار» دریافت می‌شوند (قرارداد مشترک درایورها) و به ترتیب
     * روی token10/20/30 نگاشت می‌شوند (سقف ۳ توکن در کاوه‌نگار).
     *
     * @param  array<string, string|int|float>  $tokens متغیرهای نامدار قالب
     */
    public function sendPattern(string $mobile, string $patternCode, array $tokens = []): array
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('کلید API کاوه‌نگار در تنظیمات ثبت نشده است.');
        }

        $params = [
            'receptor' => $mobile,
            'token' => $patternCode,
        ];

        // توکن‌های نامدار: token10, token20, ... (سقف ۳ توکن در کاوه‌نگار)
        $named = ['token10' => null, 'token20' => null, 'token30' => null];
        $values = array_values($tokens);

        foreach (array_keys($named) as $i => $key) {
            if (isset($values[$i])) {
                $params[$key] = (string) $values[$i];
            }
        }

        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');

        $response = Http::asForm()
            ->timeout(15)
            ->connectTimeout(8)
            ->post(trim($this->endpoint, '/').'/v1/'.rawurlencode($this->apiKey).'/verify/lookup.json', $params);

        if (! $response->successful()) {
            throw new \RuntimeException('پاسخ HTTP '.$response->status().' از کاوه‌نگار (verify) دریافت شد.');
        }

        $payload = $response->json();
        $status = data_get($payload, 'Return.Status');

        if ((int) $status !== 200) {
            throw new \RuntimeException('خطای پترن کاوه‌نگار ('.$status.'): '.(string) data_get($payload, 'Return.Message'));
        }

        return [
            'provider_status' => $status,
            'message_id' => data_get($payload, 'Entries.0.MessageId'),
            'pattern' => $patternCode,
        ];
    }
}
