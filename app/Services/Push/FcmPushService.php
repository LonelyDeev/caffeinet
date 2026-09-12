<?php

namespace App\Services\Push;

use App\Models\PushToken;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Web Push دستگاه (v25) — Firebase Cloud Messaging (HTTP v1).
 * ------------------------------------------------------------------
 * برای وقتی که «برنامه بسته است» یا کاربر آنلاین نیست: پیام به
 * توکن‌های FCM ثبت‌شدهٔ کاربر فرستاده می‌شود و Service Worker
 * (sw.js) آن را به‌صورت نوتیف سیستم‌عامل (اندروید/ویندوز/iOS-PWA)
 * نمایش می‌دهد.
 *
 * پیکربندی (پنل تنظیمات → اعلان‌ها):
 *  • notification.push.provider = off | firebase
 *  • notification.push.firebase.project_id / sender_id / api_key / app_id
 *  • فایل Service Account JSON روی دیسک private
 *
 * احراز: JWT RS256 با کلید خصوصی Service Account → access token
 * گوگل (کش ۵۰ دقیقه‌ای) → POST /v1/projects/{id}/messages:send
 *
 * همهٔ متدهای «فراخوانی از جریان اصلی» fail-safe هستند.
 */
class FcmPushService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    /* ================================================================== */
    /* پیکربندی                                                            */
    /* ================================================================== */

    public function provider(): string
    {
        $p = (string) $this->settings->get('notification.push.provider', 'off');

        return in_array($p, ['off', 'firebase'], true) ? $p : 'off';
    }

    /** آیا ارسال پوش فعال و اعتبارنامهٔ فایربیس بارگذاری شده است؟ */
    public function enabled(): bool
    {
        if ($this->provider() !== 'firebase') {
            return false;
        }

        return $this->credentials() !== null;
    }

    /**
     * محتوای Service Account JSON (اگر معتبر موجود باشد).
     *
     * @return array{project_id:string, client_email:string, private_key:string}|null
     */
    public function credentials(): ?array
    {
        $path = trim((string) $this->settings->get('notification.push.firebase.credentials', ''));

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        try {
            $json = json_decode((string) Storage::disk('local')->get($path), true);

            if (! is_array($json)
                || empty($json['project_id'])
                || empty($json['client_email'])
                || empty($json['private_key'])) {
                return null;
            }

            return $json;
        } catch (Throwable) {
            return null;
        }
    }

    /** Project ID مؤثر (اول از فایل اعتبارنامه، بعد از تنظیمات) */
    public function projectId(): ?string
    {
        $creds = $this->credentials();

        if ($creds) {
            return (string) $creds['project_id'];
        }

        $id = trim((string) $this->settings->get('notification.push.firebase.project_id', ''));

        return $id !== '' ? $id : null;
    }

    /**
     * پیکربندی عمومی کلاینت (فیلدهای عمومی Firebase Web) — برای
     * data-push-config در لایه‌ها. فقط وقتی enabled است که فیلدها کامل باشند.
     */
    public function clientConfig(?User $user): array
    {
        $provider = $this->provider();

        $public = [
            'enabled'    => false,
            'provider'   => $provider,
            'senderId'   => trim((string) $this->settings->get('notification.push.firebase.sender_id', '')),
            'apiKey'     => trim((string) $this->settings->get('notification.push.firebase.api_key', '')),
            'projectId'  => $this->projectId(),
            'appId'      => trim((string) $this->settings->get('notification.push.firebase.app_id', '')),
            'hasDevice'  => false,
        ];

        // کلاینت فقط وقتی اقدام می‌کند که هر ۴ فیلد عمومی موجود باشد
        $public['enabled'] = $provider === 'firebase'
            && $public['senderId'] !== ''
            && $public['apiKey'] !== ''
            && $public['projectId'] !== ''
            && $public['appId'] !== '';

        if ($user && $user->exists) {
            $public['hasDevice'] = PushToken::query()
                ->where('user_id', $user->id)
                ->exists();
        }

        return $public;
    }

    /* ================================================================== */
    /* ارسال                                                               */
    /* ================================================================== */

    /**
     * ارسال پوش به کاربرانِ «آفلاین» — درخواست صریح مالک:
     * نوتیف دستگاه فقط وقتی کاربر آنلاین نیست یا برنامه‌اش بسته است.
     *
     * @param  iterable<User>|User|null  $users
     * @return array{sent:int, failed:int, skipped:int}  خلاصهٔ fail-safe
     */
    public function notifyOfflineUsers(mixed $users, string $title, string $body, array $data = []): array
    {
        $summary = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        try {
            $list = $users instanceof User ? collect([$users]) : collect($users);

            foreach ($list as $user) {
                if (! $user instanceof User || ! $user->exists) {
                    continue;
                }

                // فقط کاربران آفلاین (یا برنامه بسته) پوش می‌گیرند
                if ($user->isOnline()) {
                    $summary['skipped']++;
                    continue;
                }

                $result = $this->sendToUser($user, $title, $body, $data);
                $summary['sent'] += $result['sent'];
                $summary['failed'] += $result['failed'];
            }
        } catch (Throwable) {
            // پوش هرگز جریان اصلی را نمی‌شکند
        }

        return $summary;
    }

    /** ارسال به همهٔ توکن‌های یک کاربر (بدون چک آفلاین بودن) */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        $summary = ['sent' => 0, 'failed' => 0];

        try {
            if (! $this->enabled()) {
                return $summary;
            }

            $tokens = $user->pushTokens()->get();

            foreach ($tokens as $token) {
                $ok = $this->sendMessage($token->token, $title, $body, $data);

                if ($ok === 'sent') {
                    $summary['sent']++;
                    $token->forceFill(['last_used_at' => now()])->save();
                } elseif ($ok === 'invalid') {
                    // توکن منقضی/حذف‌شده (حذف اپ یا پاک‌کردن کش مرورگر)
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
     * ارسال یک پیام FCM HTTP v1.
     *
     * @return string  sent | invalid | error
     */
    public function sendMessage(string $token, string $title, string $body, array $data = []): string
    {
        try {
            $projectId = $this->projectId();

            if (! $projectId) {
                return 'error';
            }

            $accessToken = $this->accessToken();

            if (! $accessToken) {
                return 'error';
            }

            // data باید map رشته‌ای باشد
            $dataMap = collect($data)
                ->filter(fn ($v) => $v !== null)
                ->map(fn ($v) => (string) $v)
                ->all();

            $message = [
                'token' => $token,
                'notification' => [
                    'title' => mb_substr($title, 0, 100),
                    'body' => mb_substr($body, 0, 250),
                ],
                'data' => $dataMap, // {url, event, ...} — sw.js این‌ها را می‌خواند
                'android' => [
                    'priority' => 'high',
                ],
            ];

            $url = $data['url'] ?? null;

            if ($url) {
                $message['webpush'] = [
                    'fcm_options' => ['link' => URL::to($url)],
                    'notification' => [
                        'icon' => URL::to('/icons/panels/app-192.png'),
                        'badge' => URL::to('/icons/panels/app-96.png'),
                    ],
                ];
            }

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(12)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => $message,
                ]);

            if ($response->status() === 200) {
                return 'sent';
            }

            // 404 = UNREGISTERED / 410 = توکن حذف‌شده
            if (in_array($response->status(), [404, 410], true)) {
                return 'invalid';
            }

            \Illuminate\Support\Facades\Log::warning('FCM send failed', [
                'status' => $response->status(),
                'response' => mb_substr((string) $response->body(), 0, 400),
            ]);

            return 'error';
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('FCM send error: '.$e->getMessage());

            return 'error';
        }
    }

    /* ================================================================== */
    /* OAuth2 (JWT RS256 → access token)                                   */
    /* ================================================================== */

    /** access token گوگل (کش ۵۰ دقیقه) — null روی هر خطا */
    public function accessToken(): ?string
    {
        $creds = $this->credentials();

        if (! $creds) {
            return null;
        }

        $cacheKey = 'fcm.access_token.'.md5((string) $creds['client_email']);

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $now = time();
            $exp = $now + 3600;

            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $exp,
            ], JSON_UNESCAPED_SLASHES));

            $signatureInput = $header.'.'.$claims;

            $key = openssl_pkey_get_private((string) $creds['private_key']);

            if (! $key) {
                return null;
            }

            $signature = '';

            if (! openssl_sign($signatureInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $jwt = $signatureInput.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()
                ->timeout(12)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if (! $response->successful()) {
                \Illuminate\Support\Facades\Log::warning('FCM OAuth token exchange failed', [
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 300),
                ]);

                return null;
            }

            $token = (string) $response->json('access_token');
            $expiresIn = (int) $response->json('expires_in', 3600);

            if ($token === '') {
                return null;
            }

            // ۱۰ دقیقه حاشیهٔ امنیت
            Cache::put($cacheKey, $token, max(60, $expiresIn - 600));

            return $token;
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('FCM OAuth error: '.$e->getMessage());

            return null;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /* ================================================================== */
    /* تست                                                                 */
    /* ================================================================== */

    /** پیام آزمایشی به دستگاه‌های کاربر جاری (دکمهٔ تست در تنظیمات) */
    public function sendTest(User $user): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'message' => 'پوش فایربیس فعال نیست یا Service Account بارگذاری نشده است.'];
        }

        $count = $user->pushTokens()->count();

        if ($count === 0) {
            return ['ok' => false, 'message' => 'هیچ دستگاهی برای حساب شما ثبت نشده است؛ ابتدا از زنگ اعلان پنل، «نوتیف دستگاه» را فعال کنید.'];
        }

        $result = $this->sendToUser(
            $user,
            'تست نوتیف دستگاه — کافی‌نت آنلاین',
            'اگر این پیام را روی سیستم‌عامل می‌بینید، اتصال Firebase با موفقیت کار می‌کند. ✅',
            ['url' => '/admin/settings#notifications', 'event' => 'push.test', 'tag' => 'cn-test'],
        );

        $ok = $result['sent'] > 0;

        return [
            'ok' => $ok,
            'message' => $ok
                ? 'پیام آزمایشی به '.fa_number($result['sent']).' دستگاه ارسال شد.'
                : 'ارسال به '.fa_number($count).' توکن ناموفق بود (جزئیات در لاگ سیستم).',
        ];
    }
}
