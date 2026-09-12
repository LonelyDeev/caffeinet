<?php

namespace App\Support;

use RuntimeException;

/**
 * Web Push رمزنگاری خالص PHP — v26 (RFC 8291 + RFC 8292).
 * ------------------------------------------------------------------
 * پیاده‌سازی «حالت پیش‌فرض» نوتیف دستگاه: ارسال مستقیم Web Push
 * به سرویس پوش مرورگر (FCM/Mozilla/…) بدون هیچ سرویس بیرونی.
 *
 *  • encrypt():  بارمفید AES-128-GCM با طرح aes128gcm
 *               (HKDF + ECDH P-256) — دقیقاً مطابق RFC 8291 §2 و
 *               پیاده‌سازی مرجع http_ece (Martin Thomson).
 *  • vapidAuthorization(): هدر Authorization «vapid t=…, k=…»
 *               با JWT امضاشده ES256 (RFC 8292).
 *  • generateVapidKeys(): زوج‌کلید VAPID جدید (فرمت استاندارد
 *               base64url خام ۶۵/۳۲ بایتی — همان فرمت node web-push).
 *
 * بدون وابستگی composer — فقط openssl + hash (PHP 7.3+).
 */
class WebPushCrypto
{
    /** پیشوند SPKI برای نقطهٔ uncompressed پارامتر公開 P-256 (۲۶ بایت) */
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** پیشوند SEC1 ECPrivateKey تا قبل از اسکالر خصوصی (۷ بایت) */
    private const SEC1_HEAD = '30770201010420';

    /** دنبالهٔ SEC1: [0] OID prime256v1 + [1] نقطهٔ عمومی (۱۷ بایت) */
    private const SEC1_TAIL = 'a00a06082a8648ce3d030107a144034200';

    /** اندازهٔ رکورد aes128gcm (مثل پیاده‌سازی مرجع) */
    private const RS = 4096;

    /* ================================================================== */
    /* base64url                                                           */
    /* ================================================================== */

    public static function b64urlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $in): ?string
    {
        $pad = strlen($in) % 4;

        if ($pad === 1) {
            return null;
        }

        if ($pad > 0) {
            $in .= str_repeat('=', 4 - $pad);
        }

        $bin = base64_decode(strtr($in, '-_', '+/'), true);

        return $bin === false ? null : $bin;
    }

    /* ================================================================== */
    /* VAPID — تولید زوج‌کلید                                               */
    /* ================================================================== */

    /**
     * زوج‌کلید VAPID تازه (ES256).
     *
     * @return array{public:string, private:string}  base64url: عمومی ۶۵ بایت | خصوصی ۳۲ بایت
     */
    public static function generateVapidKeys(): array
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($key === false) {
            throw new RuntimeException('openssl: ساخت کلید P-256 ناموفق بود');
        }

        $d = openssl_pkey_get_details($key);

        if (empty($d['ec']['x']) || empty($d['ec']['y']) || empty($d['ec']['d'])) {
            throw new RuntimeException('openssl: جزئیات کلید EC نامعتبر است');
        }

        $point = self::point(
            str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT),
            str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT),
        );

        return [
            'public' => self::b64urlEncode($point),
            'private' => self::b64urlEncode(str_pad($d['ec']['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    /* ================================================================== */
    /* رمزنگاری بارمفید — RFC 8291                                          */
    /* ================================================================== */

    /**
     * رمزنگاری بارمفید برای یک اشتراک Web Push (طرح aes128gcm).
     *
     * @param  string  $payload   JSON خام (حداکثر ۴۰۷۸ بایت)
     * @param  string  $p256dh    base64url نقطهٔ عمومی مشترک (۶۵ بایت)
     * @param  string  $auth      base64url راز auth (≥ ۱۶ بایت)
     * @return string  بدنهٔ کامل: salt(16) ‖ rs(4) ‖ idlen(1) ‖ keyid(65) ‖ ciphertext‖tag
     */
    public static function encrypt(string $payload, string $p256dh, string $auth): string
    {
        $uaPoint = self::b64urlDecode($p256dh);

        if ($uaPoint === null || strlen($uaPoint) !== 65 || $uaPoint[0] !== "\x04") {
            throw new RuntimeException('کلید p256dh اشتراک نامعتبر است');
        }

        $authSecret = self::b64urlDecode($auth);

        if ($authSecret === null || strlen($authSecret) < 16) {
            throw new RuntimeException('کلید auth اشتراک نامعتبر است');
        }

        $max = self::RS - 17; // ۱۶ تگ + ۱ جداکنندهٔ 0x02
        if (strlen($payload) > $max) {
            throw new RuntimeException('بارمفید بزرگ‌تر از سقف یک رکورد است');
        }

        // ۱) زوج‌کلید «گذرا» سرور برای همین پیام
        $eph = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $ed = openssl_pkey_get_details($eph);
        $ephPoint = self::point(
            str_pad($ed['ec']['x'], 32, "\0", STR_PAD_LEFT),
            str_pad($ed['ec']['y'], 32, "\0", STR_PAD_LEFT),
        );

        // ۲) راز مشترک ECDH
        $uaPub = self::loadPublicFromPoint($uaPoint);
        $shared = openssl_pkey_derive($uaPub, $eph);

        if ($shared === false || strlen($shared) < 16) {
            throw new RuntimeException('ECDH ناموفق بود');
        }

        // ۳) IKM = HKDF(auth, Z, "WebPush: info\0" || ua_public || as_public, 32)
        //    (RFC 8291 §2.1 — ترتیب: اول کلید کاربر، بعد کلید سرور)
        $ikm = hash_hkdf(
            'sha256',
            $shared,
            32,
            "WebPush: info\0".$uaPoint.$ephPoint,
            $authSecret,
        );

        // ۴) salt تازه این پیام (همان که در سرآیند می‌رود)
        $salt = random_bytes(16);

        // ۵) CEK (16) و NONCE (12)
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // ۶) رکورد یگانه: payload ‖ 0x02 (جداکنندهٔ آخرین رکورد)
        $tag = '';
        $ct = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($ct === false || $tag === '') {
            throw new RuntimeException('AES-GCM ناموفق بود');
        }

        // ۷) بدنهٔ aes128gcm
        return $salt
            .pack('N', self::RS)
            .chr(65)
            .$ephPoint
            .$ct
            .$tag;
    }

    /* ================================================================== */
    /* VAPID — هدر Authorization (RFC 8292)                                */
    /* ================================================================== */

    /**
     * هدر VAPID برای یک endpoint.
     *
     * @param  string  $publicKey   base64url نقطهٔ عمومی VAPID (۶۵ بایت)
     * @param  string  $privateKey  base64url اسکالر خصوصی VAPID (۳۲ بایت)
     */
    public static function vapidAuthorization(
        string $endpoint,
        string $publicKey,
        string $privateKey,
        string $subject,
        int $expiresIn = 43200,
    ): string {
        $aud = self::originOf($endpoint);

        if ($aud === null) {
            throw new RuntimeException('آدرس endpoint پوش نامعتبر است');
        }

        $now = time();

        $header = self::b64urlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64urlEncode(json_encode([
            'aud' => $aud,
            'exp' => $now + $expiresIn,
            'sub' => $subject,
        ], JSON_UNESCAPED_SLASHES));

        $input = $header.'.'.$claims;

        $key = self::loadPrivateFromScalar($publicKey, $privateKey);
        $der = '';
        $ok = openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256);

        if (! $ok || $der === '') {
            throw new RuntimeException('امضای ES256 ناموفق بود');
        }

        $jwt = $input.'.'.self::b64urlEncode(self::ecdsaDerToRaw($der));

        return 'vapid t='.$jwt.', k='.$publicKey;
    }

    /* ================================================================== */
    /* ابزارهای openssl                                                    */
    /* ================================================================== */

    /** نقطهٔ uncompressed ۶۵ بایتی از مختصات x/y */
    private static function point(string $x, string $y): string
    {
        return "\x04".$x.$y;
    }

    /** کلید عمومی openssl از نقطهٔ خام (با زرهٔ PEM) */
    private static function loadPublicFromPoint(string $point)
    {
        $der = hex2bin(self::SPKI_PREFIX).$point;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($der), 64)
            ."-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw new RuntimeException('نقطهٔ عمومی P-256 نامعتبر است');
        }

        return $key;
    }

    /** کلید خصوصی openssl از اسکالر ۳۲ بایتی (SEC1 با نقطهٔ عمومی اختیاری) */
    private static function loadPrivateFromScalar(string $publicKeyB64, string $privateKeyB64)
    {
        $d = self::b64urlDecode($privateKeyB64);

        if ($d === null || strlen($d) !== 32) {
            throw new RuntimeException('کلید خصوصی VAPID نامعتبر است');
        }

        $point = self::b64urlDecode($publicKeyB64);

        if ($point === null || strlen($point) !== 65) {
            throw new RuntimeException('کلید عمومی VAPID نامعتبر است');
        }

        $der = hex2bin(self::SEC1_HEAD).$d.hex2bin(self::SEC1_TAIL).$point;

        $pem = "-----BEGIN EC PRIVATE KEY-----\n"
            .chunk_split(base64_encode($der), 64)
            ."-----END EC PRIVATE KEY-----\n";

        $key = openssl_pkey_get_private($pem);

        if ($key === false) {
            throw new RuntimeException('کلید خصوصی VAPID قابل بارگذاری نیست');
        }

        return $key;
    }

    /** تبدیل امضای DER ECDSA به فرمت خام JWS (r‖s هرکدام ۳۲ بایت) */
    private static function ecdsaDerToRaw(string $der): string
    {
        $pos = 0;

        if (strlen($der) < 8 || ord($der[0]) !== 0x30) {
            throw new RuntimeException('ساختار امضا DER نامعتبر است');
        }

        $pos = 2; // بعد از SEQUENCE + طول

        if (ord($der[$pos]) !== 0x02) {
            throw new RuntimeException('عدد r در امضا پیدا نشد');
        }
        $rLen = ord($der[$pos + 1]);
        $r = ltrim(substr($der, $pos + 2, $rLen), "\0");
        $pos += 2 + $rLen;

        if (ord($der[$pos]) !== 0x02) {
            throw new RuntimeException('عدد s در امضا پیدا نشد');
        }
        $sLen = ord($der[$pos + 1]);
        $s = ltrim(substr($der, $pos + 2, $sLen), "\0");

        if (strlen($r) > 32 || strlen($s) > 32) {
            throw new RuntimeException('اندازهٔ امضای ECDSA خارج از محدوده است');
        }

        return str_pad($r, 32, "\0", STR_PAD_LEFT)
            .str_pad($s, 32, "\0", STR_PAD_LEFT);
    }

    /** origin یک URL endpoint (scheme://host[:port]) */
    private static function originOf(string $endpoint): ?string
    {
        $parts = parse_url($endpoint);

        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
