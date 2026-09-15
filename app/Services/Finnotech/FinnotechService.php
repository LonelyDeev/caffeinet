<?php

namespace App\Services\Finnotech;

use App\Models\FinnotechLog;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;

/**
 * v40 — سرویس استعلام فینوتک (finnotech.ir).
 *
 * • احراز هویت OAuth2 با رویکرد Client Credential:
 *      POST {base}/dev/v2/oauth2/token
 *      Authorization: Basic base64(clientId:clientSecret)
 *      Body: { grant_type: "client_credentials", nid: "صاحب برنامه", scopes: "..." }
 *
 * • تطبیق کد ملی و موبایل (شاهکار) — facility:shahkar:get:
 *      GET {base}/facility/v2/clients/{clientId}/shahkar/verify
 *          ?trackId=...&mobile=09...&nationalCode=...
 *      پاسخ: { result: { isValid: true }, status: "DONE" }
 *
 * • تطبیق شماره کارت و کد ملی — kyc:card-owner-verification:post:
 *      POST {base}/kyc/v2/clients/{clientId}/cardOwnerVerification?trackId=...
 *      Body: { card: "16رقم", nid: "10رقم" }
 *      پاسخ: { result: { isValid: true }, status: "DONE" }
 *
 * • توکن در کش نگه داشته می‌شود (کمی قبل از انقضا تمدید می‌شود).
 * • همهٔ استعلام‌ها در finnotech_logs ثبت می‌شوند (چه موفق چه ناموفق).
 * • خروجی یک FinnotechResult است؛ هرگز exception بیرون نمی‌زند (fail-soft).
 */
class FinnotechService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    /** آیا سرویس فعال است و اعتبارنامه‌ها پر شده‌اند؟ */
    public function enabled(): bool
    {
        if (! (bool) $this->settings->get('finnotech.enabled', false)) {
            return false;
        }

        return $this->configOk();
    }

    /** آیا اعتبارنامه‌ها کامل هستند؟ (برای پیام خطای دقیق) */
    public function configOk(): bool
    {
        return $this->clientId() !== ''
            && trim((string) $this->settings->get('finnotech.client_secret', '')) !== ''
            && $this->apiNid() !== '';
    }

    /** آیا «بررسی کد ملی در پروفایل مشتری» فعال است؟ */
    public function profileVerificationOn(): bool
    {
        return $this->enabled() && (bool) $this->settings->get('finnotech.verify_profile', true);
    }

    /** آیا «بررسی کارت‌های بانکی» فعال است؟ */
    public function cardVerificationOn(): bool
    {
        return $this->enabled() && (bool) $this->settings->get('finnotech.verify_cards', true);
    }

    /** آدرس پایهٔ API — بازنویسی از env (تست) یا بر اساس محیط تنظیمات */
    public function baseUrl(): string
    {
        $override = trim((string) config('services.finnotech.base_url'));
        if ($override !== '') {
            return rtrim($override, '/');
        }

        $mode = (string) $this->settings->get('finnotech.mode', 'production');

        return $mode === 'sandbox' ? 'https://sandboxapi.finnotech.ir' : 'https://api.finnotech.ir';
    }

    /** محیط فعلی برای پیام‌ها */
    public function modeLabel(): string
    {
        return (string) $this->settings->get('finnotech.mode', 'production') === 'sandbox'
            ? 'sandbox (تستی)'
            : 'production (واقعی)';
    }

    protected function clientId(): string
    {
        return trim((string) $this->settings->get('finnotech.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) $this->settings->get('finnotech.client_secret', ''));
    }

    /** کد ملی صاحب برنامه — لازم برای گرفتن توکن */
    protected function apiNid(): string
    {
        $nid = en_digits(trim((string) $this->settings->get('finnotech.nid', '')));

        return preg_match('/^\d{10}$/', $nid) ? $nid : '';
    }

    /**
     * توکن دسترسی با کش (per-scope) — عمر توکن معمولاً ۸۶۴۰۰۰۰ms ≈ ۲۴ ساعت.
     *
     * @return string|null null = خطا (تنظیمات/شبکه)
     */
    public function accessToken(string $scopes): ?string
    {
        $cacheKey = 'finnotech_token_'.md5($this->clientId().'|'.$scopes);

        $hit = cache()->get($cacheKey);
        if (is_string($hit) && $hit !== '') {
            return $hit;
        }

        try {
            $response = Http::timeout(config('services.finnotech.timeout', 15))
                ->connectTimeout(config('services.finnotech.connect_timeout', 6))
                ->withHeaders([
                    'Authorization' => 'Basic '.base64_encode($this->clientId().':'.$this->clientSecret()),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl().'/dev/v2/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'nid' => $this->apiNid(),
                    'scopes' => $scopes,
                ]);
        } catch (ConnectionException) {
            $this->log('token', null, error: 'اتصال به فینوتک برقرار نشد (شبکه/تایم‌اوت)');

            return null;
        } catch (\Throwable $e) {
            $this->log('token', null, error: mb_substr($e->getMessage(), 0, 180));

            return null;
        }

        $json = (array) $response->json();

        $token = $json['result']['value'] ?? null;
        if ($response->successful() && is_string($token) && $token !== '' && ($json['status'] ?? '') !== 'FAILED') {
            // عمر بر حسب میلی‌ثانیه؛ ۵ دقیقه حاشیهٔ امن برای تمدید
            $lifeMs = (int) ($json['result']['lifeTime'] ?? 86_400_000);
            $ttl = max(60, (int) floor($lifeMs / 1000) - 300);
            cache()->put($cacheKey, $token, now()->addSeconds(min($ttl, 86_400)));

            return $token;
        }

        $this->log('token', null,
            error: (string) ($json['error']['message'] ?? 'گرفتن توکن فینوتک ناموفق بود (HTTP '.$response->status().')'),
            responseCode: (string) ($json['error']['code'] ?? $response->status()));

        return null;
    }

    /**
     * تست اتصال و اعتبارنامه (دکمهٔ «تست اتصال» تنظیمات).
     */
    public function testConnection(): FinnotechResult
    {
        if (! $this->configOk()) {
            return FinnotechResult::error('config', 'شناسه برنامه، رمز برنامه یا کد ملی صاحب برنامه تکمیل نشده است.');
        }

        $token = $this->accessToken('facility:shahkar:get');

        return $token
            ? FinnotechResult::ok(true, 'اتصال به فینوتک برقرار شد و توکن صادر گردید ('.$this->modeLabel().').')
            : FinnotechResult::error('token', 'اتصال یا احراز هویت فینوتک ناموفق بود؛ شناسه/رمز/کد ملی و محیط را بررسی کنید.');
    }

    /**
     * شاهکار: تطبیق کد ملی و شماره موبایل.
     *
     * @param  int|null  $userId برای لاگ (اختیاری)
     */
    public function shahkarVerify(string $mobile, string $nationalId, ?int $userId = null): FinnotechResult
    {
        $mobile = en_digits(trim($mobile));
        $nid = en_digits(trim($nationalId));

        if (! preg_match('/^09\d{9}$/', $mobile)) {
            return FinnotechResult::error('invalid_mobile', 'شماره موبایل معتبر نیست.');
        }
        if (! preg_match('/^\d{10}$/', $nid)) {
            return FinnotechResult::error('invalid_nid', 'کد ملی باید ۱۰ رقم باشد.');
        }

        $token = $this->accessToken('facility:shahkar:get');
        if (! $token) {
            return FinnotechResult::error('token', 'سرویس استعلام فینوتک در دسترس نیست؛ لطفاً بعداً تلاش کنید.');
        }

        $trackId = 'shk-'.substr((string) now()->timestamp, -8).'-'.substr(bin2hex(random_bytes(4)), 0, 6);

        try {
            $response = Http::timeout(config('services.finnotech.timeout', 15))
                ->connectTimeout(config('services.finnotech.connect_timeout', 6))
                ->withToken($token)
                ->acceptJson()
                ->get($this->baseUrl().'/facility/v2/clients/'.$this->clientId().'/shahkar/verify', [
                    'trackId' => $trackId,
                    'mobile' => $mobile,
                    'nationalCode' => $nid,
                ]);
        } catch (ConnectionException) {
            $this->log('shahkar', $userId, $mobile, $nid, null, false, false, $trackId, 'اتصال به فینوتک قطع شد (شبکه/تایم‌اوت)');

            return FinnotechResult::error('network', 'اتصال به سرویس استعلام برقرار نشد؛ لطفاً چند لحظه بعد دوباره تلاش کنید.');
        } catch (\Throwable $e) {
            $this->log('shahkar', $userId, $mobile, $nid, null, false, false, $trackId, mb_substr($e->getMessage(), 0, 180));

            return FinnotechResult::error('network', 'خطای غیرمنتظره در استعلام؛ لطفاً دوباره تلاش کنید.');
        }

        $json = (array) $response->json();
        $isValid = (bool) ($json['result']['isValid'] ?? false);
        $status = (string) ($json['status'] ?? '');
        $responseCode = (string) ($json['responseCode'] ?? (string) $response->status());

        if ($response->successful() && $status !== 'FAILED' && ($json['result'] ?? null) !== null) {
            $this->log('shahkar', $userId, $mobile, $nid, null, $isValid, true, $trackId, null, $responseCode);

            return $isValid
                ? FinnotechResult::ok(true, 'کد ملی و شماره موبایل به یک شخص تعلق دارد.')
                : FinnotechResult::ok(false, 'کد ملی واردشده به نام صاحب این شماره موبایل نیست.');
        }

        $message = (string) ($json['error']['message'] ?? 'استعلام شاهکار ناموفق بود (HTTP '.$response->status().').');
        $this->log('shahkar', $userId, $mobile, $nid, null, false, false, $trackId, $message, $responseCode);

        return FinnotechResult::error('api', 'استعلام هم‌اکنون ممکن نشد؛ لطفاً چند لحظه بعد دوباره تلاش کنید.');
    }

    /**
     * تطبیق شماره کارت و کد ملی — kyc:card-owner-verification:post.
     */
    public function cardOwnerVerify(string $card, string $nationalId, ?int $userId = null): FinnotechResult
    {
        $card = en_digits(preg_replace('/[\s\-]/', '', trim($card)));
        $nid = en_digits(trim($nationalId));

        if (! preg_match('/^\d{16}$/', $card)) {
            return FinnotechResult::error('invalid_card', 'شماره کارت باید ۱۶ رقم باشد.');
        }
        if (! preg_match('/^\d{10}$/', $nid)) {
            return FinnotechResult::error('invalid_nid', 'کد ملی باید ۱۰ رقم باشد.');
        }

        $token = $this->accessToken('kyc:card-owner-verification:post');
        if (! $token) {
            return FinnotechResult::error('token', 'سرویس استعلام فینوتک در دسترس نیست؛ لطفاً بعداً تلاش کنید.');
        }

        $trackId = 'cov-'.substr((string) now()->timestamp, -8).'-'.substr(bin2hex(random_bytes(4)), 0, 6);

        try {
            $response = Http::timeout(config('services.finnotech.timeout', 15))
                ->connectTimeout(config('services.finnotech.connect_timeout', 6))
                ->withToken($token)
                ->acceptJson()
                ->post($this->baseUrl().'/kyc/v2/clients/'.$this->clientId().'/cardOwnerVerification?trackId='.$trackId, [
                    'card' => $card,
                    'nid' => $nid,
                ]);
        } catch (ConnectionException) {
            $this->log('card_owner', $userId, null, $nid, $card, false, false, $trackId, 'اتصال به فینوتک قطع شد (شبکه/تایم‌اوت)');

            return FinnotechResult::error('network', 'اتصال به سرویس استعلام برقرار نشد؛ لطفاً چند لحظه بعد دوباره تلاش کنید.');
        } catch (\Throwable $e) {
            $this->log('card_owner', $userId, null, $nid, $card, false, false, $trackId, mb_substr($e->getMessage(), 0, 180));

            return FinnotechResult::error('network', 'خطای غیرمنتظره در استعلام؛ لطفاً دوباره تلاش کنید.');
        }

        $json = (array) $response->json();
        $isValid = (bool) ($json['result']['isValid'] ?? false);
        $status = (string) ($json['status'] ?? '');
        $responseCode = (string) ($json['responseCode'] ?? (string) $response->status());

        if ($response->successful() && $status !== 'FAILED' && ($json['result'] ?? null) !== null) {
            $this->log('card_owner', $userId, null, $nid, $card, $isValid, true, $trackId, null, $responseCode);

            return $isValid
                ? FinnotechResult::ok(true, 'شماره کارت به نام صاحب این کد ملی است.')
                : FinnotechResult::ok(false, 'شماره کارت به نام صاحب این کد ملی نیست.');
        }

        $message = (string) ($json['error']['message'] ?? 'استعلام کارت ناموفق بود (HTTP '.$response->status().').');
        $this->log('card_owner', $userId, null, $nid, $card, false, false, $trackId, $message, $responseCode);

        return FinnotechResult::error('api', 'استعلام کارت هم‌اکنون ممکن نشد؛ لطفاً چند لحظه بعد دوباره تلاش کنید.');
    }

    /** ثبت استعلام در finnotech_logs */
    protected function log(
        string $type,
        ?int $userId,
        ?string $mobile = null,
        ?string $nid = null,
        ?string $card = null,
        bool $matched = false,
        bool $succeeded = false,
        ?string $trackId = null,
        ?string $error = null,
        ?string $responseCode = null,
    ): void {
        try {
            FinnotechLog::create([
                'user_id' => $userId,
                'type' => $type,
                'mobile' => $mobile,
                'national_id' => $nid,
                'card_number' => $card,
                'matched' => $matched,
                'succeeded' => $succeeded,
                'response_code' => $responseCode !== null ? mb_substr($responseCode, 0, 40) : null,
                'track_id' => $trackId !== null ? mb_substr($trackId, 0, 60) : null,
                'error_message' => $error !== null ? mb_substr($error, 0, 190) : null,
                'ip' => Request::ip(),
            ]);
        } catch (\Throwable) {
            // لاگ‌نویسی هرگز نباید جریان اصلی را بشکند
        }
    }
}
