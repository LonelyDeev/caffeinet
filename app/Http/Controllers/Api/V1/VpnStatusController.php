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

    /** آی‌پی واقعی کاربر (پشت Cloudflare/Nginx/پروکسی) */
    private function clientIp(Request $request): string
    {
        foreach (['CF-Connecting-IP', 'X-Real-IP', 'True-Client-IP'] as $header) {
            $candidate = trim((string) $request->header($header));
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        foreach (explode(',', (string) $request->header('X-Forwarded-For')) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return (string) $request->ip();
    }

    /**
     * موقعیت جغرافیایی آی‌پی — ip-api.com سپس ipwho.is؛ نتیجه ۱۵ دقیقه کش.
     *
     * @return array{code: string, name: string}|null
     */
    private function resolveGeo(string $ip): ?array
    {
        $hit = Cache::get('vpn_geo_'.$ip);
        if (is_array($hit)) {
            return $hit;
        }

        // ۱) ip-api.com — رایگان و بدون کلید (HTTP سرور‌به‌سرور)
        try {
            $response = Http::timeout(4)->connectTimeout(3)
                ->get('http://ip-api.com/json/'.$ip, ['fields' => 'status,country,countryCode']);
            if ($response->ok()) {
                $json = (array) $response->json();
                if (($json['status'] ?? '') === 'success' && ! empty($json['countryCode'])) {
                    return $this->cacheGeo($ip, (string) $json['countryCode'], (string) ($json['country'] ?? ''));
                }
            }
        } catch (\Throwable) {
            // سرویس بعدی
        }

        // ۲) ipwho.is — جایگزین HTTPS
        try {
            $response = Http::timeout(4)->connectTimeout(3)->get('https://ipwho.is/'.$ip);
            if ($response->ok()) {
                $json = (array) $response->json();
                if (($json['success'] ?? false) && ! empty($json['country_code'])) {
                    return $this->cacheGeo($ip, (string) $json['country_code'], (string) ($json['country'] ?? ''));
                }
            }
        } catch (\Throwable) {
            // سکوت
        }

        return null; // کش نمی‌شود تا دفعهٔ بعد دوباره تلاش شود
    }

    /** @return array{code: string, name: string} */
    private function cacheGeo(string $ip, string $code, string $name): array
    {
        $payload = ['code' => strtoupper($code), 'name' => $name];
        Cache::put('vpn_geo_'.$ip, $payload, now()->addMinutes(15));

        return $payload;
    }
}
