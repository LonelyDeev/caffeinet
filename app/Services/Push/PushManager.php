<?php

namespace App\Services\Push;

use App\Models\PushToken;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\WebPushCrypto;
use Throwable;

/**
 * مدیر نوتیف دستگاه — انتخاب و ارسال بر اساس سرویس فعال (v26).
 * ------------------------------------------------------------------
 * چهار حالت (تنظیمات → اعلان‌ها → سرویس نوتیف دستگاه):
 *
 *  • off       خاموش
 *  • default   وب‌پوش داخلی (بدون سرویس بیرونی — WebPushService)
 *  • pusher    Pusher Beams (PusherBeamsService)
 *  • firebase  Firebase Cloud Messaging (FcmPushService)
 *
 * همهٔ جریان‌های اصلی از این کلاس استفاده می‌کنند (نه سرویس‌های
 * اختصاصی)؛ هر متد fail-safe است و جریان اصلی را نمی‌شکند.
 */
class PushManager
{
    public const PROVIDERS = ['off', 'default', 'pusher', 'firebase'];

    /** نام فارسی هر سرویس (برای پیام‌ها) */
    public const PROVIDER_LABELS = [
        'off' => 'خاموش',
        'default' => 'پیش‌فرض (وب‌پوش داخلی)',
        'pusher' => 'Pusher Beams',
        'firebase' => 'Firebase (FCM)',
    ];

    public function __construct(
        protected SettingsService $settings,
        protected WebPushService $webpush,
        protected PusherBeamsService $beams,
        protected FcmPushService $fcm,
    ) {}

    /* ================================================================== */
    /* پیکربندی                                                            */
    /* ================================================================== */

    public function provider(): string
    {
        $p = (string) $this->settings->get('notification.push.provider', 'off');

        return in_array($p, self::PROVIDERS, true) ? $p : 'off';
    }

    public function providerLabel(): string
    {
        return self::PROVIDER_LABELS[$this->provider()] ?? 'خاموش';
    }

    /** آیا سرویس فعال و آمادهٔ ارسال است؟ */
    public function enabled(): bool
    {
        return match ($this->provider()) {
            'default' => $this->webpush->isActive() && $this->webpush->hasKeys(),
            'pusher' => $this->beams->isActive() && $this->beams->ready(),
            'firebase' => $this->fcm->enabled(),
            default => false,
        };
    }

    /**
     * پیکربندی عمومی کلاینت — data-push-config در همهٔ لایه‌ها.
     * کلاینت بر اساس provider مسیر فعال‌سازی را انتخاب می‌کند.
     */
    public function clientConfig(?User $user): array
    {
        $provider = $this->provider();

        $cfg = [
            'provider' => $provider,
            'enabled' => false,
            'hasDevice' => false,
        ];

        // شناسهٔ کاربر جاری (v35) — برای تطبیق هویت وقتی SW پیام پوش را به
        // صفحهٔ باز تحویل می‌دهد (برنامه باز → فقط اعلان درون‌برنامه‌ای)
        $cfg['userId'] = ($user && $user->exists) ? (int) $user->id : null;

        // فایربیس — چهار فیلد عمومی لازم است
        if ($provider === 'firebase') {
            $cfg += [
                'senderId' => trim((string) $this->settings->get('notification.push.firebase.sender_id', '')),
                'apiKey' => trim((string) $this->settings->get('notification.push.firebase.api_key', '')),
                'projectId' => $this->fcm->projectId(),
                'appId' => trim((string) $this->settings->get('notification.push.firebase.app_id', '')),
            ];

            $cfg['enabled'] = $cfg['senderId'] !== ''
                && $cfg['apiKey'] !== ''
                && $cfg['projectId'] !== null && $cfg['projectId'] !== ''
                && $cfg['appId'] !== '';
        }

        // پیش‌فرض — کلید عمومی VAPID لازم است
        if ($provider === 'default') {
            $vapid = $this->webpush->publicKey();
            $cfg['vapidKey'] = $vapid;
            $cfg['enabled'] = $vapid !== null;
        }

        // پوشر Beams — instance id لازم است
        if ($provider === 'pusher') {
            $instance = $this->beams->instanceId();
            $cfg['beamsInstanceId'] = $instance;
            $cfg['userId'] = ($user && $user->exists) ? (int) $user->id : null;
            $cfg['enabled'] = $instance !== null;
        }

        if ($user && $user->exists) {
            // نام سرویس در جدول توکن‌ها: webpush | pusher | firebase
            $tokenProvider = match ($provider) {
                'default' => 'webpush',
                'pusher' => 'pusher',
                'firebase' => 'firebase',
                default => null,
            };

            $cfg['hasDevice'] = $tokenProvider !== null && PushToken::query()
                ->where('user_id', $user->id)
                ->where('provider', $tokenProvider)
                ->exists();
        }

        return $cfg;
    }

    /** تولید/تضمین کلیدهای VAPID سرویس پیش‌فرض (idempotent) */
    public function ensureWebpushKeys(): bool
    {
        try {
            if ($this->webpush->hasKeys()) {
                return true;
            }

            $keys = WebPushCrypto::generateVapidKeys();

            $this->settings->set('notification.push.webpush.public_key', $keys['public']);
            $this->settings->set('notification.push.webpush.private_key', $keys['private']);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /* ================================================================== */
    /* ارسال                                                               */
    /* ================================================================== */

    /**
     * ارسال پوش به کاربران «آفلاین» — درخواست صریح مالک:
     * نوتیف دستگاه فقط وقتی کاربر آنلاین نیست یا برنامه‌اش بسته است.
     *
     * v36: نتیجهٔ هر گیرنده به‌صورت per-user برگردانده می‌شود تا
     * NotificationService بتواند ردیف اعلان را «تلاش‌شده» علامت بزند؛
     * گیرندهٔ آنلاین (پوش ارسال نشد) توسط فرمان
     * notifications:flush-pending دوباره چک می‌شود — اگر برنامه‌اش
     * بسته باشد، پوش همان‌جا (حداکثر ~۱۰ ثانیه بعد از آفلاین‌شدن) می‌رود.
     *
     * @param  iterable<User>|User|null  $users
     * @return array{sent:int, failed:int, skipped:int, results:array<int, array{status:string, sent:int, failed:int}>}
     */
    public function notifyOfflineUsers(mixed $users, string $title, string $body, array $data = []): array
    {
        $summary = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'results' => []];

        try {
            $list = $users instanceof User ? collect([$users]) : collect($users);

            foreach ($list as $user) {
                if (! $user instanceof User || ! $user->exists) {
                    continue;
                }

                $result = $this->pushIfOffline($user, $title, $body, $data);

                $summary['results'][$user->id] = $result;
                $summary['sent'] += $result['sent'];
                $summary['failed'] += $result['failed'];

                if ($result['status'] === 'skipped-online') {
                    $summary['skipped']++;
                }
            }
        } catch (Throwable) {
            // پوش هرگز جریان اصلی را نمی‌شکند
        }

        return $summary;
    }

    /**
     * وضعیت‌های ممکن pushIfOffline:
     *  • disabled      سرویس پوش خاموش/غیرفعال است
     *  • skipped-online گیرنده همین الان آنلاین است (آستانهٔ تنظیمات) — پوش نمی‌رود
     *  • sent          ارسال شد (حداقل یک دستگاه)
     *  • failed        تلاش شد ولی همهٔ دستگاه‌ها خطا دادند
     *  • no-device     تلاش شد ولی گیرنده توکن دستگاه ثبت‌شده ندارد
     */
    public function pushIfOffline(User $user, string $title, string $body, array $data = []): array
    {
        $result = ['status' => 'disabled', 'sent' => 0, 'failed' => 0];

        try {
            if (! $this->enabled()) {
                return $result;
            }

            // فقط کاربران آفلاین (یا برنامه بسته) پوش می‌گیرند —
            // آستانه همان آستانهٔ «آنلاین/آفلاین» تنظیمات است (یک منبع حقیقت)
            if ($user->isOnline()) {
                $result['status'] = 'skipped-online';

                return $result;
            }

            $result['status'] = 'no-device';

            $send = $this->sendToUser($user, $title, $body, $data);

            $result['sent'] = (int) ($send['sent'] ?? 0);
            $result['failed'] = (int) ($send['failed'] ?? 0);

            if ($result['sent'] > 0) {
                $result['status'] = 'sent';
            } elseif ($result['failed'] > 0) {
                $result['status'] = 'failed';
            }
        } catch (Throwable) {
            // پوش هرگز جریان اصلی را نمی‌شکند
        }

        return $result;
    }

    /**
     * ارسال به همهٔ دستگاه‌های یک کاربر با سرویس فعال (بدون چک آفلاین).
     *
     * v35: شناسهٔ گیرنده (uid) همیشه داخل دادهٔ پیام قرار می‌گیرد تا
     * Service Worker بتواند تشخیص دهد پیام مال کدام کاربر است — اگر
     * صفحهٔ باز همان کاربر باشد، نوتیف سیستمی نمایش داده نمی‌شود و
     * اعلان درون‌برنامه‌ای کافی است.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        if (! isset($data['uid'])) {
            $data['uid'] = (string) $user->id;
        }

        return match ($this->provider()) {
            'default' => $this->webpush->sendToUser($user, $title, $body, $data),
            'pusher' => $this->beams->sendToUser($user, $title, $body, $data),
            'firebase' => $this->fcm->sendToUser($user, $title, $body, $data),
            default => ['sent' => 0, 'failed' => 0],
        };
    }

    /** پیام آزمایشی به دستگاه‌های کاربر (دکمهٔ تست در تنظیمات) */
    public function sendTest(User $user): array
    {
        $provider = $this->provider();

        if ($provider === 'off') {
            return ['ok' => false, 'message' => 'سرویس نوتیف دستگاه خاموش است؛ ابتدا یکی از سه سرویس را فعال کنید.'];
        }

        $result = match ($provider) {
            'default' => $this->webpush->sendTest($user),
            'pusher' => $this->beams->sendTest($user),
            'firebase' => $this->fcm->sendTest($user),
            default => ['ok' => false, 'message' => 'سرویس نامعتبر است.'],
        };

        if (! $result['ok'] && $provider === 'default') {
            $result['message'] = 'سرویس پیش‌فرض: '.$result['message'];
        }

        return $result;
    }

    /* ================================================================== */
    /* ابزار                                                               */
    /* ================================================================== */

    /**
     * آستانهٔ «آفلاین» (ثانیه) — مشترک بین همهٔ سرویس‌ها.
     * v36: منبع حقیقت یکی است — helpers::offline_threshold_seconds()
     * (با کف ۴۵ ثانیه)؛ همان مقدار در UI تنظیمات، جزئیات کاربران،
     * داشبورد و پوش استفاده می‌شود.
     */
    public function offlineSeconds(): int
    {
        return offline_threshold_seconds();
    }

    /* ================================================================== */
    /* دسترسی‌های عمومی (برای UI تنظیمات)                                */
    /* ================================================================== */

    public function webpush(): WebPushService
    {
        return $this->webpush;
    }

    public function beams(): PusherBeamsService
    {
        return $this->beams;
    }

    public function fcm(): FcmPushService
    {
        return $this->fcm;
    }
}
