<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * سرو رسانهٔ عمومی (تصاویر خدمات/اطلاعیه‌ها و ویدیوهای اطلاعیه) — فاز ۲۲
 * ---------------------------------------------------------------------
 * چرا این کنترلر؟ بعضی هاست‌ها/پیاده‌سازی‌ها symlink باقی‌مانده از
 * `php artisan storage:link` را سالم نگه نمی‌دارند (مثلاً zip،
 * `public/storage` را به‌جای symlink به‌صورت «پوشهٔ واقعی» باز می‌کند و
 * آنگاه storage:link با خطای «directory already exists» شکست می‌خورد؛
 * نتیجه: تصاویر آپلودی در `storage/app/public` می‌مانند ولی URL اشتباه
 * به `public/storage` اشاره می‌کند → 403/404).
 *
 * این روت فایل را مستقیم از دیسک public می‌خواند و استریم می‌کند؛
 * یعنی مستقل از symlink روی هر وب‌سروری کار می‌کند.
 *
 * امنیت:
 *  • فقط پیشوندهای مجاز (services/ و announcements/ و sounds/)
 *  • ممنوعیت پیمایش مسیر (.. ، NUL ، scheme)
 *  • لیست سفید پسوند (SVG عمداً مجاز نیست — تصمیم امنیتی مالک)
 *  • بررسی realpath داخل ریشهٔ دیسک (دفاع عمقی)
 *  • throttle + هدر nosniff + ETag/Last-Modified/Range خودکار
 */
class MediaController extends Controller
{
    /** پیشوندهای مجاز داخل دیسک public */
    private const PREFIXES = ['services/', 'announcements/', 'sounds/'];

    /** پسوند → Content-Type (لیست سفید؛ هر چیز دیگر = 404) */
    private const TYPES = [
        'webp' => 'image/webp',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        // v25 — فایل‌های صدای اعلان سفارشی (تنظیمات → اعلان‌ها)
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
    ];

    public function show(string $path, \Illuminate\Http\Request $request): BinaryFileResponse
    {
        $path = str_replace('\\', '/', rawurldecode($path));

        // ۱) پیشوند مجاز + بدون پیمایش مسیر
        $ok = false;
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $ok = true;
                break;
            }
        }
        if (! $ok
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || strpbrk($path, ':?#') !== false) {
            abort(404);
        }

        // ۲) پسوند مجاز؟ (svg مجاز نیست)
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! isset(self::TYPES[$ext])) {
            abort(404);
        }

        // ۳) فایل روی دیسک public موجود است؟
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }

        // ۴) دفاع عمقی: مسیر واقعی باید داخل ریشهٔ دیسک بماند
        $root = realpath($disk->path(''));
        $abs = realpath($disk->path($path));
        if ($root === false || $abs === false || ! str_starts_with($abs, $root.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        // ۵) استریم با کش و پشتیبانی Range (برای ویدیوها)
        $response = response()->file($abs, [
            'Content-Type' => self::TYPES[$ext],
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ])->setAutoEtag();

        // ۶) درخواست شرطی → 304 (ETag/If-Modified-Since)
        $response->isNotModified($request);

        return $response;
    }
}
