<?php

namespace App\Services\Realtime;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * سرویس Realtime پوشر (فاز ۱۳) — در کنار سیستم پولینگ.
 *
 * بدون وابستگی SDK: مستقیم روی REST API رسمی Pusher با امضای
 * HMAC-SHA256 (auth_key / auth_timestamp / auth_version / body_md5) کار می‌کند.
 *
 * معماری «بیدارباش» (Wake-up):
 *  - رویداد Pusher فقط «اعلان وقوع» است؛ دادهٔ واقعی همیشه از API خود سیستم
 *    خوانده می‌شود (تک منبع حقیقت = دیتابیس).
 *  - فرانت با دریافت رویداد، همان لحظه پول را اجرا می‌کند و بازهٔ پولینگ
 *    را بزرگ می‌کند → هم آنی بودن، هم کاهش فشار دیتابیس (MySQL).
 *
 * امنیت بدون private-channel auth (ترفند):
 *  - نام کانال‌ها از هش SHA1 دامنه + شناسه + APP_KEY ساخته می‌شود؛
 *    نام کانال عمومی است اما غیرقابل حدس (۲۰ کاراکتر هگز).
 *  - کلاینت فقط همان کانالی را می‌شناسد که سرور برایش فرستاده است.
 */
class PusherService
{
    /** پیشوند کانال اعلان‌های کاربر */
    public const NOTIF_PREFIX = 'u';

    /** پیشوند کانال گفتگوی سفارش */
    public const CHAT_PREFIX = 'c';

    public function __construct(
        protected \App\Services\Settings\SettingsService $settings,
    ) {}

    /* ================================================================== */
    /* ۱) وضعیت و پیکربندی                                                 */
    /* ================================================================== */

    /** آیا Pusher فعال و پیکربندی‌شده است؟ */
    public function enabled(): bool
    {
        return (bool) $this->settings->get('realtime.pusher.enabled', false)
            && trim((string) $this->settings->get('realtime.pusher.app_key', '')) !== ''
            && trim((string) $this->settings->get('realtime.pusher.app_secret', '')) !== ''
            && trim((string) $this->settings->get('realtime.pusher.app_id', '')) !== '';
    }

    /** پیکربندی عمومی برای تزریق در صفحه (بدون Secret) */
    public function clientConfig(?User $user = null): array
    {
        $cfg = [
            'enabled' => $this->enabled(),
            'key' => (string) $this->settings->get('realtime.pusher.app_key', ''),
            'cluster' => (string) $this->settings->get('realtime.pusher.cluster', 'mt1'),
            'channel' => null,
        ];

        if ($user && $cfg['enabled']) {
            $cfg['channel'] = $this->userChannel((int) $user->id);
        }

        return $cfg;
    }

    /* ================================================================== */
    /* ۲) نام کانال‌ها (غیرقابل حدس)                                        */
    /* ================================================================== */

    /** کانال اعلان‌های اختصاصی یک کاربر */
    public function userChannel(int $userId): string
    {
        return self::NOTIF_PREFIX.'.'.$this->hash('u', $userId);
    }

    /** کانال گفتگوی یک سفارش (مشتری + اپراتور + کافی‌نت + ادمین) */
    public function chatChannel(int $orderId): string
    {
        return self::CHAT_PREFIX.'.'.$this->hash('c', $orderId);
    }

    /** هش غیرقابل حدس از دامنه + شناسه + APP_KEY */
    protected function hash(string $domain, int $id): string
    {
        return substr(sha1($domain.'|'.$id.'|'.config('app.key')), 0, 20);
    }

    /* ================================================================== */
    /* ۳) ارسال رویداد                                                     */
    /* ================================================================== */

    /**
     * ارسال رویداد به یک یا چند کانال (batch).
     * هرگز استثنا بالا نمی‌برد — Pusher اختیاری است و جریان اصلی نباید متوقف شود.
     *
     * @param  string|array  $channels
     */
    public function trigger(string|array $channels, string $event, array $data = []): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $channels = array_values((array) $channels);

        if (! $channels) {
            return false;
        }

        // Pusher در هر درخواست حداکثر ۱۰۰ کانال
        foreach (array_chunk($channels, 100) as $batch) {
            $this->postEvent($batch, $event, $data);
        }

        return true;
    }

    /** ارسال HTTP امضاشده — خطا فقط لاگ می‌شود */
    protected function postEvent(array $channels, string $event, array $data): bool
    {
        try {
            $appId = trim((string) $this->settings->get('realtime.pusher.app_id', ''));
            $key = trim((string) $this->settings->get('realtime.pusher.app_key', ''));
            $secret = trim((string) $this->settings->get('realtime.pusher.app_secret', ''));
            $cluster = trim((string) $this->settings->get('realtime.pusher.cluster', 'mt1')) ?: 'mt1';

            $path = "/apps/{$appId}/events";
            $body = json_encode([
                'name' => $event,
                'channels' => $channels,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ], JSON_UNESCAPED_UNICODE);

            $params = [
                'auth_key' => $key,
                'auth_timestamp' => (string) time(),
                'auth_version' => '1.0',
                'body_md5' => md5((string) $body),
            ];
            ksort($params);
            $query = http_build_query($params);

            $signature = hash_hmac('sha256', "POST\n{$path}\n{$query}", $secret);

            $host = str_starts_with($cluster, 'api-') ? $cluster : "api-{$cluster}";
            $url = "https://{$host}.pusher.com{$path}?{$query}&auth_signature={$signature}";

            $response = Http::timeout(5)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody((string) $body, 'application/json')
                ->post($url);

            if (! $response->successful()) {
                Log::warning('Pusher trigger failed', [
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 300),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Pusher trigger error: '.$e->getMessage());

            return false;
        }
    }

    /* ================================================================== */
    /* ۴) رویدادهای دامنه                                                  */
    /* ================================================================== */

    /** رویداد پیام جدید چت → بیدارباش پولینگ طرف مقابل */
    public function chatMessage(Order $order, ?int $messageId = null, ?int $senderId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->trigger(
            $this->chatChannel((int) $order->id),
            'message.new',
            [
                'order' => (int) $order->id,
                'id' => $messageId,
                'sender_id' => $senderId,
            ],
        );
    }

    /** رویداد اعلان جدید برای کاربران */
    public function notifyUsers(array $userIds, string $type = 'system'): void
    {
        if (! $this->enabled() || ! $userIds) {
            return;
        }

        $channels = array_map(fn ($id) => $this->userChannel((int) $id), array_unique($userIds));

        $this->trigger($channels, 'notif.new', ['type' => $type]);
    }

    /* ================================================================== */
    /* ۵) تست اتصال (پنل تنظیمات)                                          */
    /* ================================================================== */

    /**
     * تست اعتبارسنجی اعتبارنامه‌های Pusher با فراخوانی GET /channels.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'message' => 'پوشر در تنظیمات فعال نیست یا کلیدها ناقص‌اند.'];
        }

        try {
            $appId = trim((string) $this->settings->get('realtime.pusher.app_id', ''));
            $key = trim((string) $this->settings->get('realtime.pusher.app_key', ''));
            $secret = trim((string) $this->settings->get('realtime.pusher.app_secret', ''));
            $cluster = trim((string) $this->settings->get('realtime.pusher.cluster', 'mt1')) ?: 'mt1';

            $path = "/apps/{$appId}/channels";
            $params = [
                'auth_key' => $key,
                'auth_timestamp' => (string) time(),
                'auth_version' => '1.0',
            ];
            ksort($params);
            $query = http_build_query($params);

            $signature = hash_hmac('sha256', "GET\n{$path}\n{$query}", $secret);

            $host = str_starts_with($cluster, 'api-') ? $cluster : "api-{$cluster}";
            $url = "https://{$host}.pusher.com{$path}?{$query}&auth_signature={$signature}";

            $response = Http::timeout(8)->get($url);

            if ($response->status() === 401 || $response->status() === 403) {
                return ['ok' => false, 'message' => 'اعتبارنامه‌های پوشر رد شد (۴۰۱/۴۰۳) — App Key/Secret/ID را بررسی کنید.'];
            }

            if ($response->status() === 404) {
                return ['ok' => false, 'message' => 'App ID یا Cluster اشتباه است (۴۰۴).'];
            }

            if (! $response->successful()) {
                return ['ok' => false, 'message' => 'پاسخ غیرمنتظره از پوشر (HTTP '.$response->status().').'];
            }

            $channels = (array) (json_decode((string) $response->body(), true)['channels'] ?? []);

            return [
                'ok' => true,
                'message' => 'اتصال به پوشر برقرار است ✓ — '.count($channels).' کانال فعال',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'خطای اتصال: '.$e->getMessage()];
        }
    }

    /** ارسال رویداد آزمایشی روی کانال تست */
    public function sendTestEvent(): array
    {
        $ok = $this->trigger('test', 'test.ping', ['at' => now()->toIso8601String()]);

        return $ok
            ? ['ok' => true, 'message' => 'رویداد آزمایشی با موفقیت ارسال شد ✓']
            : ['ok' => false, 'message' => 'ارسال رویداد ناموفق بود — لاگ را بررسی کنید.'];
    }
}
