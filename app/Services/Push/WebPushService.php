<?php

namespace App\Services\Push;

use App\Models\PushToken;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\WebPushCrypto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Web Push داخلی — «حالت پیش‌فرض» نوتیف دستگاه (v26).
 * ------------------------------------------------------------------
 * بدون هیچ سرویس بیرونی (نه گوگل، نه پوشر): مرورگر اشتراک Push
 * می‌سازد (pushManager.subscribe با کلید VAPID سامانه) و سرور
 * خودش پیام رمزنگاری‌شده (RFC 8291) + هدر VAPID (RFC 8292) را
 * مستقیم به endpoint سرویس پوش مرورگر (FCM/Mozilla/Apple) می‌فرستد.
 *
 *  • کلیدهای VAPID: notification.push.webpush.public_key/private_key
 *    (خودکار تولید می‌شوند — PushManager::ensureWebpushKeys)
 *  • اشتراک‌ها: push_tokens با provider='webpush'
 *    (token = endpoint، p256dh/auth = کلیدهای اشتراک)
 *
 * رمزنگاری خالص PHP در App\Support\WebPushCrypto — بدون وابستگی.
 * همهٔ متدهای «فراخوانی از جریان اصلی» fail-safe هستند.
 */
class WebPushService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    /* ================================================================== */
    /* پیکربندی                                                            */
    /* ================================================================== */

    /** آیا این سرویس پرووایدر فعال است؟ */
    public function isActive(): bool
    {
        return (string) $this->settings->get('notification.push.provider', 'off') === 'default';
    }

    /** آیا کلیدهای VAPID آمادهٔ ارسال هستند؟ */
    public function hasKeys(): bool
    {
        $pub = trim((string) $this->settings->get('notification.push.webpush.public_key', ''));
        $priv = trim((string) $this->settings->get('notification.push.webpush.private_key', ''));

        return $pub !== '' && $priv !== '';
    }

    /** کلید عمومی VAPID (برای pushManager.subscribe کلاینت) */
    public function publicKey(): ?string
    {
        $pub = trim((string) $this->settings->get('notification.push.webpush.public_key', ''));

        return $pub !== '' ? $pub : null;
    }

    /** آدرس تماس (subject) هدر VAPID — پیش‌فرض mailto پشتیبان سامانه */
    public function subject(): string
    {
        $subject = trim((string) $this->settings->get('notification.push.webpush.subject', ''));

        if ($subject !== '' && filter_var($subject, FILTER_VALIDATE_EMAIL)) {
            return 'mailto:'.$subject;
        }

        return 'mailto:'.trim((string) config('mail.from.address', 'admin@caffeinet.ir'));
    }

    /* ================================================================== */
    /* ارسال                                                               */
    /* ================================================================== */

    /**
     * ارسال به همهٔ اشتراک‌های «وب‌پوش داخلی» یک کاربر.
     *
     * @return array{sent:int, failed:int}
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        $summary = ['sent' => 0, 'failed' => 0];

        try {
            if (! $this->isActive() || ! $this->hasKeys()) {
                return $summary;
            }

            $tokens = $user->pushTokens()
                ->where('provider', 'webpush')
                ->get();

            foreach ($tokens as $token) {
                $result = $this->sendToSubscription($token, $title, $body, $data);

                if ($result === 'sent') {
                    $summary['sent']++;
                    $token->forceFill(['last_used_at' => now()])->save();
                } elseif ($result === 'invalid') {
                    // اشتراک منقضی/حذف‌شده
                    $token->delete();
                    $summary['failed']++;
                } else {
                    $summary['failed']++;
                }
            }
        } catch (Throwable) {
            // fail-safe
        }

        return $summary;
    }

    /**
     * ارسال یک پیام به یک اشتراک (endpoint + p256dh + auth).
     *
     * @return string  sent | invalid | error
     */
    public function sendToSubscription(PushToken $token, string $title, string $body, array $data = []): string
    {
        try {
            $payload = [
                'notification' => [
                    'title' => mb_substr($title, 0, 100),
                    'body' => mb_substr($body, 0, 250),
                ],
                'data' => collect($data)
                    ->filter(fn ($v) => $v !== null)
                    ->map(fn ($v) => (string) $v)
                    ->all(),
            ];

            $url = $payload['data']['url'] ?? null;

            if ($url) {
                $payload['data']['url'] = URL::to($url);
            }

            $encrypted = WebPushCrypto::encrypt(
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (string) $token->p256dh,
                (string) $token->auth,
            );

            $authorization = WebPushCrypto::vapidAuthorization(
                $token->token, // endpoint
                (string) $this->publicKey(),
                (string) trim((string) $this->settings->get('notification.push.webpush.private_key', '')),
                $this->subject(),
            );

            $response = Http::withHeaders([
                'TTL' => '86400',
                'Urgency' => 'normal',
                'Content-Encoding' => 'aes128gcm',
                'Content-Type' => 'application/octet-stream',
                'Authorization' => $authorization,
            ])
                ->withBody($encrypted, 'application/octet-stream')
                ->timeout(12)
                ->post($token->token);

            if ($response->status() === 201 || $response->status() === 200) {
                return 'sent';
            }

            // 404/410 = اشتراک منقضی
            if (in_array($response->status(), [404, 410], true)) {
                return 'invalid';
            }

            \Illuminate\Support\Facades\Log::warning('WebPush send failed', [
                'status' => $response->status(),
                'response' => mb_substr((string) $response->body(), 0, 400),
            ]);

            return 'error';
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WebPush error: '.$e->getMessage());

            return 'error';
        }
    }

    /* ================================================================== */
    /* تست                                                                 */
    /* ================================================================== */

    /** پیام آزمایشی به اشتراک‌های وب‌پوش کاربر جاری (دکمهٔ تست در تنظیمات) */
    public function sendTest(User $user): array
    {
        $count = $user->pushTokens()->where('provider', 'webpush')->count();

        if ($count === 0) {
            return ['ok' => false, 'message' => 'هیچ دستگاهی با سرویس پیش‌فرض ثبت نشده است؛ ابتدا از زنگ اعلان پنل، «نوتیف دستگاه» را فعال کنید.'];
        }

        $result = $this->sendToUser(
            $user,
            'تست نوتیف دستگاه (پیش‌فرض) — کافی‌نت آنلاین',
            'اگر این پیام را روی سیستم‌عامل می‌بینید، سرویس وب‌پوش داخلی بدون هیچ سرویس بیرونی کار می‌کند. ✅',
            ['url' => '/admin/settings#notifications', 'event' => 'push.test', 'tag' => 'cn-test'],
        );

        $ok = $result['sent'] > 0;

        return [
            'ok' => $ok,
            'message' => $ok
                ? 'پیام آزمایشی به '.fa_number($result['sent']).' دستگاه ارسال شد.'
                : 'ارسال به '.fa_number($count).' اشتراک ناموفق بود (جزئیات در لاگ سیستم).',
        ];
    }
}
