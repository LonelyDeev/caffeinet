<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * تشخیص VPN فعال (v19) — GET /api/v1/vpn-status (عمومی).
 *
 * منطق: کاربران هدف سامانه ایرانی‌اند؛ اگر آی‌پی عمومی کاربر خارج از
 * ایران باشد، به احتمال بسیار بالا VPN روشن است و پنل‌ها (اپ مشتری،
 * ادمین، سازمان، کافی‌نت، اپراتور) مودال «برای بهره‌بری سریع‌تر،
 * لطفاً VPN را خاموش کنید» نمایش می‌دهند.
 *
 * • آی‌پی خصوصی/محلی (توسعه) → checked=false (بدون مودال)
 * • قطعی سرویس جیو (تایم‌اوت ۴ ثانیه) → checked=false — هرگز به‌خاطر
 *   خطای سرویس بیرونی، هشدار اشتباه نمایش داده نمی‌شود
 * • نتیجهٔ جیو ۱۵ دقیقه به‌ازای هر آی‌پی کش می‌شود
 * • پشت Cloudflare/Nginx آی‌پی واقعی از هدرها خوانده می‌شود
 * • در حالت debug با پارامتر ?ip= آی‌پی دلخواه قابل تست است (E2E)
 */
class VpnStatusController extends Controller
{
    /** کشور میزبان و کاربران هدف */
    private const HOME_COUNTRY = 'IR';

    /** کشورهای پرتکرار خروجی VPN — نام فارسی برای نمایش در مودال */
    private const COUNTRIES_FA = [
        'IR' => 'ایران', 'NL' => 'هلند', 'DE' => 'آلمان', 'US' => 'آمریکا', 'TR' => 'ترکیه',
        'AE' => 'امارات', 'GB' => 'انگلستان', 'FR' => 'فرانسه', 'CA' => 'کانادا',
        'SE' => 'سوئد', 'CH' => 'سوئیس', 'FI' => 'فنلاند', 'LU' => 'لوکزامبورگ',
        'AT' => 'اتریش', 'ES' => 'اسپانیا', 'IT' => 'ایتالیا', 'IE' => 'ایرلند',
        'SG' => 'سنگاپور', 'JP' => 'ژاپن', 'HK' => 'هنگ‌کنگ', 'RU' => 'روسیه',
        'AU' => 'استرالیا', 'IN' => 'هند', 'UA' => 'اوکراین', 'PL' => 'لهستان',
        'RO' => 'رومانی', 'MD' => 'مولداوی', 'AR' => 'آرژانتین', 'BR' => 'برزیل',
    ];

    /** GET /api/v1/vpn-status */
    public function status(Request $request): JsonResponse
    {
        $ip = $this->clientIp($request);

        // فقط در حالت توسعه: تست با آی‌پی دلخواه (?ip=8.8.8.8)
        if (config('app.debug')) {
            $override = trim((string) $request->query('ip', ''));
            if (filter_var($override, FILTER_VALIDATE_IP)) {
                $ip = $override;
            }
        }

        // آی‌پی محلی/خصوصی → محیط توسعه؛ بدون بررسی
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return response()->json([
                'data' => [
                    'vpn' => false,
                    'checked' => false,
                    'reason' => 'private_network',
                    'ip' => $ip,
                    'message' => 'اتصال محلی — بررسی VPN انجام نشد.',
                ],
            ]);
        }

        $geo = $this->resolveGeo($ip);

        if ($geo === null) {
            // سرویس جیو در دسترس نبود — سکوت بهتر از هشدار اشتباه است
            return response()->json([
                'data' => [
                    'vpn' => false,
                    'checked' => false,
                    'reason' => 'geo_unavailable',
                    'ip' => $ip,
                    'message' => 'بررسی موقعیت اتصال هم‌اکنون در دسترس نیست.',
                ],
            ]);
        }

        $vpn = $geo['code'] !== self::HOME_COUNTRY;
        $countryName = self::COUNTRIES_FA[$geo['code']] ?? $geo['name'];

        return response()->json([
            'data' => [
                'vpn' => $vpn,
                'checked' => true,
                'ip' => $ip,
                'country' => $geo['code'],
                'country_name' => $countryName,
                'message' => $vpn
                    ? 'اتصال شما از '.$countryName.' برقرار می‌شود؛ برای بهره‌بری سریع‌تر، لطفاً VPN را خاموش کنید.'
                    : 'اتصال شما داخلی است؛ نیازی به خاموش‌کردن VPN نیست.',
            ],
        ]);
    }

    /** آی‌پی واقعی کاربر (پشت Cloudflare/Nginx/پروکسی)
     *  v40 — کاندیداهای خصوصی/رزرو شده از هدرها رد می‌شوند (نمی‌توانند آی‌پی
     *  واقعی کاربر باشند و فقط نویز پروکسی داخلی هستند). */
    private function clientIp(Request $request): string
    {
        foreach (['CF-Connecting-IP', 'X-Real-IP', 'True-Client-IP'] as $header) {
            $candidate = trim((string) $request->header($header));
            if ($this->isPublicIp($candidate)) {
                return $candidate;
            }
        }

        foreach (explode(',', (string) $request->header('X-Forwarded-For')) as $candidate) {
            $candidate = trim($candidate);
            if ($this->isPublicIp($candidate)) {
                return $candidate;
            }
        }

        return (string) $request->ip();
    }

    /** آی‌پی عمومی معتبر است؟ (خصوصی/رزرو/نامعتبر → false) */
    private function isPublicIp(?string $ip): bool
    {
        if (! $ip || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * موقعیت جغرافیایی آی‌پی (v40 — سه سرویس به‌صورت موازی).
     *
     * ضعف قبلی: زنجیرهٔ سری (ip-api سپس ipwho.is) اگر سرویس اول کند/قطع
     * بود، پاسخ دیر می‌رسید یا geo_unavailable می‌شد و مودال نمایش داده
     * نمی‌شد («تشخیص VPN کار نمی‌کند»). اکنون هر سه سرویس هم‌زمان صدا
     * زده می‌شوند و اولین پاسخ معتبر برنده است — حتی اگر یکی از سرویس‌ها
     * از سرور ایرانی در دسترس نباشد.
     *
     * نتیجهٔ موفق ۱۵ دقیقه و شکست کامل ۹۰ ثانیه کش می‌شود.
     *
     * @return array{code: string, name: string}|null
     */
    private function resolveGeo(string $ip): ?array
    {
        $hit = Cache::get('vpn_geo_'.$ip);
        if (is_array($hit)) {
            return $hit;
        }

        // شکست کامل اخیر؟ (کش منفی کوتاه تا هر بازدید دوباره سه سرویس را نکوبد)
        if (Cache::get('vpn_geo_fail_'.$ip)) {
            return null;
        }

        $timeout = 4;

        /*
         * هر سه سرویس هم‌زمان (pool) — اولین پاسخ معتبر برنده:
         *  ۱) get.geojs.io      — HTTPS، بدون محدودیت کلید، سریع
         *  ۲) ip-api.com        — رایگان (HTTP سرور‌به‌سرور)، دقیق
         *  ۳) ipwho.is          — HTTPS جایگزین
         * (ipapi.co کنار گذاشته شد — پشت چالش Cloudflare برای درخواست سروری)
         */
        try {
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($ip, $timeout) {
                $pool->as('geojs')->timeout($timeout)->connectTimeout(3)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->get('https://get.geojs.io/v1/ip/country/'.$ip.'.json');

                $pool->as('ipapi')->timeout($timeout)->connectTimeout(3)
                    ->get('http://ip-api.com/json/'.$ip, ['fields' => 'status,country,countryCode']);

                return $pool->as('ipwho')->timeout($timeout)->connectTimeout(3)
                    ->get('https://ipwho.is/'.$ip);
            });
        } catch (\Throwable) {
            $responses = [];
        }

        $results = $responses ?: [];

        // ۱) geojs.io — پاسخ آبجکت تکی یا آرایهٔ آبجکت (چند آی‌پی) است
        try {
            $body = $results['geojs']?->json();
            $row = null;
            if (is_array($body)) {
                $first = $body;
                if (array_is_list($body)) {
                    $first = reset($body); // اولین آیتم آرایه
                }
                $row = is_array($first) ? $first : null;
            }
            if ($results['geojs']?->ok() && $row && ! empty($row['country_code'])) {
                return $this->cacheGeo($ip, (string) $row['country_code'], (string) ($row['name'] ?? ''));
            }
        } catch (\Throwable) {
            // سرویس بعدی
        }

        // ۲) ip-api.com
        try {
            $body = (array) $results['ipapi']?->json();
            if ($results['ipapi']?->ok() && ($body['status'] ?? '') === 'success' && ! empty($body['countryCode'])) {
                return $this->cacheGeo($ip, (string) $body['countryCode'], (string) ($body['country'] ?? ''));
            }
        } catch (\Throwable) {
            // سرویس بعدی
        }

        // ۳) ipwho.is
        try {
            $body = (array) $results['ipwho']?->json();
            if ($results['ipwho']?->ok() && ($body['success'] ?? false) && ! empty($body['country_code'])) {
                return $this->cacheGeo($ip, (string) $body['country_code'], (string) ($body['country'] ?? ''));
            }
        } catch (\Throwable) {
            // سکوت
        }

        // هر سه ناموفق — کش منفی کوتاه تا درخواست‌های بعدی فوراً رد نشوند بلکه بعد از ۹۰ ثانیه دوباره تلاش شود
        Cache::put('vpn_geo_fail_'.$ip, now()->timestamp, now()->addSeconds(90));

        return null;
    }

    /** @return array{code: string, name: string} */
    private function cacheGeo(string $ip, string $code, string $name): array
    {
        $payload = ['code' => strtoupper($code), 'name' => $name];
        Cache::put('vpn_geo_'.$ip, $payload, now()->addMinutes(15));

        return $payload;
    }
}
