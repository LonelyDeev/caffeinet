<?php

namespace App\Services\Push;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pusher Beams — سرویس نوتیف دستگاه پوشر (v26).
 * ------------------------------------------------------------------
 * پوشر علاوه بر Realtime (زمانی که صفحه باز است)، محصول «Beams» را
 * برای نوتیف دستگاه (برنامهٔ بسته/گوشی/ویندوز) دارد؛ این سرویس با
 * REST API آن کار می‌کند:
 *
 *  POST https://{instance_id}.pushnotifications.pusher.com
 *       /publish_api/v1/instances/{instance_id}/publishes/interests
 *  Authorization: Bearer {primary_key}
 *  Body: { interests: ["user-{id}"], web: { notification: {...} } }
 *
 * کلاینت با SDK وب Beams (vendor محلی) دستگاه را ثبت و interest
 * «user-{id}» را پیوست می‌کند؛ پیام‌ها از همان sw.js نمایش می‌یابند.
 *
 * پیکربندی: notification.push.beams.instance_id / primary_key
 */
class PusherBeamsService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    /* ================================================================== */
    /* پیکربندی                                                            */
    /* ================================================================== */

    public function isActive(): bool
    {
        return (string) $this->settings->get('notification.push.provider', 'off') === 'pusher';
    }

    /** آیا اعتبارنامهٔ Beams کامل است؟ */
    public function ready(): bool
    {
        return $this->instanceId() !== null && $this->primaryKey() !== null;
    }

    public function instanceId(): ?string
    {
        $id = trim((string) $this->settings->get('notification.push.beams.instance_id', ''));

        return $id !== '' ? $id : null;
    }

    public function primaryKey(): ?string
    {
        $key = trim((string) $this->settings->get('notification.push.beams.primary_key', ''));

        return $key !== '' ? $key : null;
    }

    /* ================================================================== */
    /* ارسال                                                               */
    /* ================================================================== */

    /**
     * انتشار پیام به interest کاربر (همهٔ دستگاه‌های ثبت‌شده‌اش).
     *
     * @return array{sent:int, failed:int}
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        $summary = ['sent' => 0, 'failed' => 0];

        try {
            if (! $this->isActive() || ! $this->ready()) {
                return $summary;
            }

            $url = $data['url'] ?? null;

            $notification = [
                'title' => mb_substr($title, 0, 100),
                'body' => mb_substr($body, 0, 250),
                'icon' => url('/icons/icon-192.png'),
            ];

            // deep_link مطلق برای کلیک (sw.js آن را می‌خواند)
            if ($url) {
                $notification['deep_link'] = url($url);
            }

            // Beams فقط Bearer می‌پذیرد (Basic auth → 400 «no Bearer token found»)
            $response = Http::withToken($this->primaryKey())
                ->acceptJson()
                ->timeout(12)
                ->post($this->publishUrl(), [
                    'interests' => [$this->interestFor($user)],
                    'web' => ['notification' => $notification],
                ]);

            if ($response->status() === 200) {
                // انتشار موفق (اگر دستگاهی برای interest نباشد، پوشر آن را بی‌صدا رد می‌کند)
                $summary['sent'] = 1;
                $user->pushTokens()->where('provider', 'pusher')->update(['last_used_at' => now()]);
            } else {
                $summary['failed'] = 1;

                \Illuminate\Support\Facades\Log::warning('Pusher Beams publish failed', [
                    'status' => $response->status(),
                    'response' => mb_substr((string) $response->body(), 0, 400),
                ]);
            }
        } catch (Throwable $e) {
            $summary['failed'] = 1;
            \Illuminate\Support\Facades\Log::warning('Pusher Beams error: '.$e->getMessage());
        }

        return $summary;
    }

    /** interest اختصاصی کاربر */
    public function interestFor(User $user): string
    {
        return 'user-'.$user->id;
    }

    private function publishUrl(): string
    {
        $instance = $this->instanceId();

        return "https://{$instance}.pushnotifications.pusher.com/publish_api/v1/instances/{$instance}/publishes/interests";
    }

    /* ================================================================== */
    /* تست                                                                 */
    /* ================================================================== */

    /** پیام آزمایشی به دستگاه‌های کاربر جاری (دکمهٔ تست در تنظیمات) */
    public function sendTest(User $user): array
    {
        $count = $user->pushTokens()->where('provider', 'pusher')->count();

        if ($count === 0) {
            return ['ok' => false, 'message' => 'هیچ دستگاهی با پوشر ثبت نشده است؛ ابتدا از زنگ اعلان پنل، «نوتیف دستگاه» را فعال کنید.'];
        }

        $result = $this->sendToUser(
            $user,
            'تست نوتیف دستگاه (پوشر Beams) — کافی‌نت آنلاین',
            'اگر این پیام را روی سیستم‌عامل می‌بینید، اتصال Pusher Beams با موفقیت کار می‌کند. ✅',
            ['url' => '/admin/settings#notifications', 'event' => 'push.test', 'tag' => 'cn-test'],
        );

        $ok = $result['sent'] > 0;

        return [
            'ok' => $ok,
            'message' => $ok
                ? 'پیام آزمایشی از پوشر Beams به interest دستگاه‌های شما ارسال شد.'
                : 'انتشار ناموفق بود (اعتبارنامهٔ Beams یا اتصال را بررسی کنید).',
        ];
    }
}
