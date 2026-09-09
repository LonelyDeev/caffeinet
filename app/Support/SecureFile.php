<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * رمزنگاری فایل‌ها روی دیسک (فاز ۱۱ — امنیت و لانچ).
 *
 * همهٔ فایل‌های خصوصی سامانه (مدارک سفارش، پیوست گفتگو، پیوست تیکت)
 * با AES-256-GCM رمزنگاری و سپس ذخیره می‌شوند؛ در نتیجه دسترسی مستقیم
 * به دیسک سرور (یا سرقت بکاپِ رمزنگاری‌نشده) محتوای فایل را فاش نمی‌کند.
 * فقط لینک‌های موقتِ امضاشده از مسیر اپلیکیشن عبور می‌کنند و آن‌جا
 * رمزگشایی و سرو می‌شود.
 *
 * قالب روی دیسک:
 *   CNENC1:<base64( IV(12B) ‖ TAG(16B) ‖ ciphertext )>
 *
 * کلید:
 *   FILE_ENCRYPTION_KEY (base64/hex ۳۲ بایت) وگرنه sha256(APP_KEY).
 *   → بدون هیچ تغییر env همیشه یک کلید پایدار وجود دارد؛ برای تفکیک
 *     کلید فایل از کلید نشست، در پروداکشن FILE_ENCRYPTION_KEY مستقل بدهید.
 *
 * سازگاری با دادهٔ قدیمی: فایل‌های رمزنگاری‌نشده (فاقد پیشوند) هنگام
 * خواندن بدون تغییر برگردانده می‌شوند تا بک‌فیل files:encrypt تدریجی امن باشد.
 */
class SecureFile
{
    /** پیشوند قالب رمزنگاری‌شده */
    public const HEADER = 'CNENC1:';

    /** طول IV برای GCM */
    private const IV_LEN = 12;

    /** طول تگ احراز GCM */
    private const TAG_LEN = 16;

    /** دیسک پیش‌فرض فایل‌های خصوصی */
    public const DISK = 'local';

    /** مسیرهای خصوصیِ مشمول رمزنگاری (نسبت به ریشهٔ دیسک) */
    public const SECURE_PATHS = ['orders', 'chat', 'tickets'];

    private static ?string $key = null;

    /* ------------------------------------------------------------------ */
    /*  API اصلی                                                          */
    /* ------------------------------------------------------------------ */

    /** ذخیرهٔ محتوای رمزنگاری‌شده روی دیسک مشخص. */
    public static function put(Filesystem|string $disk, string $path, string $contents): bool
    {
        $disk = is_string($disk) ? Storage::disk($disk) : $disk;

        return $disk->put($path, self::encrypt($contents));
    }

    /**
     * خواندن و رمزگشایی. فایل رمزنگاری‌نشده (قدیمی) بدون تغییر برگردانده
     * می‌شود تا مهاجرت تدریجی ممکن باشد. در خرابی تگ → RuntimeException.
     */
    public static function get(Filesystem|string $disk, string $path): ?string
    {
        $disk = is_string($disk) ? Storage::disk($disk) : $disk;

        $raw = $disk->get($path);

        if ($raw === null) {
            return null;
        }

        if (! self::isEncrypted($raw)) {
            return $raw;
        }

        return self::decrypt($raw);
    }

    /** آیا بایت‌های خوانده‌شده رمزنگاری‌شدهٔ این سامانه‌اند؟ */
    public static function isEncrypted(string $raw): bool
    {
        return str_starts_with($raw, self::HEADER);
    }

    /** پاسخ دانلود/نمایش درون‌برنامه‌ای از محتوای رمزگشایی‌شده. */
    public static function response(
        Filesystem|string $disk,
        string $path,
        string $name,
        string $mime = 'application/octet-stream',
        bool $inline = false,
    ): \Symfony\Component\HttpFoundation\Response {
        $contents = self::get($disk, $path);

        if ($contents === null) {
            abort(404, 'فایل یافت نشد.');
        }

        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'file';
        $utf8 = str_replace('"', '', $name);

        return response($contents, 200, [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Content-Length' => strlen($contents),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment')
                .'; filename="'.$ascii.'"'
                ."; filename*=UTF-8''".rawurlencode($utf8),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  رمزنگاری خام                                                      */
    /* ------------------------------------------------------------------ */

    /** رمزنگاری محتوا به قالب CNENC1. */
    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(self::IV_LEN);
        $tag = '';

        $cipher = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN,
        );

        if ($cipher === false) {
            throw new \RuntimeException('رمزنگاری فایل ناموفق بود.');
        }

        return self::HEADER.base64_encode($iv.$tag.$cipher);
    }

    /** رمزگشایی قالب CNENC1 (بدون بررسی پیشوند — از decrypt لوکس استفاده کنید). */
    public static function decrypt(string $blob): string
    {
        $payload = base64_decode(substr($blob, strlen(self::HEADER)), true);

        if ($payload === false || strlen($payload) <= self::IV_LEN + self::TAG_LEN) {
            throw new \RuntimeException('فایل رمزنگاری‌شده خراب است.');
        }

        $iv = substr($payload, 0, self::IV_LEN);
        $tag = substr($payload, self::IV_LEN, self::TAG_LEN);
        $cipher = substr($payload, self::IV_LEN + self::TAG_LEN);

        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plain === false) {
            throw new \RuntimeException('رمزگشایی فایل ناموفق بود (کلید یا تگ نامعتبر).');
        }

        return $plain;
    }

    /* ------------------------------------------------------------------ */
    /*  کلید                                                              */
    /* ------------------------------------------------------------------ */

    /** کلید ۳۲ بایتی رمزنگاری (کش در طول درخواست). */
    public static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $env = trim((string) env('FILE_ENCRYPTION_KEY'));

        if ($env !== '') {
            $key = self::parseKey($env);

            if ($key === null || strlen($key) !== 32) {
                throw new \RuntimeException('FILE_ENCRYPTION_KEY باید base64 یا hex دقیقاً ۳۲ بایت باشد.');
            }

            return self::$key = $key;
        }

        // فallback امن: کلید پایدارِ مشتق از APP_KEY (حتی با پیشوند base64:)
        return self::$key = hash('sha256', (string) config('app.key'), true);
    }

    private static function parseKey(string $value): ?string
    {
        // hex ۶۴ نویسه
        if (preg_match('/^[0-9a-fA-F]{64}$/', $value)) {
            return hex2bin($value);
        }

        $decoded = base64_decode($value, true);

        return $decoded === false ? null : $decoded;
    }

    /** بازنشانی کلید کش‌شده (تست). */
    public static function resetKeyCache(): void
    {
        self::$key = null;
    }
}
