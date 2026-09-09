<?php

namespace App\Console\Commands;

use App\Support\SecureFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * فاز ۱۱ — بک‌فیل رمزنگاری فایل‌های خصوصی موجود.
 *
 * همهٔ فایل‌های orders/، chat/ و tickets/ که هنوز رمزنگاری‌نشده‌اند را
 * درجا (in place) رمزنگاری می‌کند. دستور idempotent است و فایل‌های
 * رمزنگاری‌شده را دوباره لمس نمی‌کند.
 *
 * php artisan files:encrypt            → اجرا
 * php artisan files:encrypt --dry-run  → فقط شمارش
 * php artisan files:encrypt --decrypt  → برگشت به حالت خام (فقط برای مهاجرت سرور)
 */
class EncryptFiles extends Command
{
    protected $signature = 'files:encrypt {--dry-run : فقط گزارش، بدون تغییر} {--decrypt : رمزگشایی درجا به‌جای رمزنگاری}';

    protected $description = 'رمزنگاری درجای فایل‌های خصوصی موجود (مدارک/گفتگو/تیکت)';

    public function handle(): int
    {
        $disk = Storage::disk(SecureFile::DISK);
        $dry = (bool) $this->option('dry-run');
        $reverse = (bool) $this->option('decrypt');

        $this->info('دیسک: '.SecureFile::DISK.' — مسیرها: '.implode('، ', SecureFile::SECURE_PATHS));

        $stats = ['encrypted' => 0, 'plain' => 0, 'skipped' => 0, 'errors' => 0, 'bytes' => 0];

        foreach (SecureFile::SECURE_PATHS as $folder) {
            foreach ($disk->allFiles($folder) as $path) {
                $raw = $disk->get($path);

                if ($raw === null || $raw === '') {
                    $stats['skipped']++;

                    continue;
                }

                if (SecureFile::isEncrypted($raw)) {
                    if (! $reverse) {
                        $stats['encrypted']++;

                        continue;
                    }

                    if (! $dry) {
                        $disk->put($path, SecureFile::decrypt($raw));
                    }

                    $stats['plain']++;
                    $stats['bytes'] += strlen($raw);

                    continue;
                }

                if ($reverse) {
                    $stats['plain']++;

                    continue;
                }

                if (! $dry) {
                    $disk->put($path, SecureFile::encrypt($raw));
                }

                $stats['encrypted']++;
                $stats['bytes'] += strlen($raw);
            }
        }

        $mode = $reverse ? 'رمزگشایی' : 'رمزنگاری';
        $this->table(['گزینه', 'مقدار'], [
            ['وضعیت', $dry ? 'آزمایشی (dry-run)' : 'اجرا'],
            ['عملیات', $mode],
            [($reverse ? 'خام باقی‌مانده' : 'رمزنگاری‌شدهٔ نهایی'), fa_digits($stats['encrypted']).' فایل'],
            [$reverse ? 'رمزنگاری‌شده باقی‌مانده' : 'خام باقی‌مانده', fa_digits($stats['plain']).' فایل'],
            ['ردشده (خالی)', fa_digits($stats['skipped']).' فایل'],
            ['حجم پردازش‌شده', fa_digits(round($stats['bytes'] / 1024)).' کیلوبایت'],
        ]);

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
