<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * ضربان کرون (v36 → v37) — سلامت زمان‌بندی هاست.
 *
 * هر اجرای schedule:run باید این مقدار را تازه کند؛ تنظیمات → عمومی
 * مقدار آن را می‌خواند: تازه‌تر از ۳ دقیقه = سبز، وگرنه قرمز.
 *
 * v37 دو مسیر مستقل برای نوشتن دارد (اگر یکی به هر دلیلی حذف/عقب
 * بیفتد، دیگری کافی است):
 *  ۱) رویداد ScheduledTaskStarting — در «شروع» هر چرخهٔ schedule:run
 *     (قبل از اجرای هر تسک) فایر می‌شود؛ یعنی حتی اگر خودِ تسک ضربان
 *     تعریف نشده باشد، باز مقدار تازه می‌شود.
 *  ۲) تسک زمان‌بندی‌شدهٔ cron-heartbeat در routes/console.php.
 *
 * همچنین هر لمس، یک خط به storage/app/cron-trace.log اضافه می‌کند
 * (با نهایتاً ۵۰ خط آخر اگر بزرگ شد) — برای عیب‌یابی روی هاست.
 */
class CronHeartbeat
{
    public const KEY = 'system.cron.last';

    /** پنجرهٔ سلامت (ثانیه) — کرون هر-دقیقه‌ای با تحمل ۳ دقیقه */
    public const HEALTHY_WINDOW = 180;

    /** نوشتن ضربان + ثبت ردپا (fail-safe — هرگز خطا نمی‌دهد) */
    public static function touch(?string $task = null): void
    {
        try {
            // مستقیم روی مدل — بدون کش و بدون flush (این کلید فقط از
            // SettingsController مستقیم خوانده می‌شود و همیشه تازه است)
            Setting::query()->updateOrCreate(
                ['key' => self::KEY],
                [
                    'group' => 'system',
                    'value' => now()->format('Y-m-d H:i:s'),
                    'cast' => 'string',
                    'label' => 'آخرین ضربان کرون (schedule:run)',
                ],
            );
        } catch (Throwable) {
            // ضربان هرگز اجرای بقیهٔ زمان‌بندی‌ها را متوقف نکند
        }

        self::trace($task ?? 'schedule:run');
    }

    /** ثبت ردپای اجرا در storage/app/cron-trace.log (چرخش خودکار) */
    public static function trace(string $task): void
    {
        try {
            $path = storage_path('app/cron-trace.log');
            $line = now()->format('Y-m-d H:i:s').' | '.$task.PHP_EOL;

            // چرخش: اگر بزرگ شد فقط ۵۰ خط آخر نگه داشته می‌شود
            if (@filesize($path) > 64 * 1024) {
                $lines = @file($path, FILE_IGNORE_NEW_LINES) ?: [];
                $keep = array_slice($lines, -50);

                @file_put_contents($path, implode(PHP_EOL, $keep).PHP_EOL.$line);

                return;
            }

            @file_put_contents($path, $line, FILE_APPEND);
        } catch (Throwable) {
            // ردپا حیاتی نیست
        }
    }

    /**
     * v37 — آخرین ردپاهای ثبت‌شده (برای عیب‌یابی حالت قرمز در تنظیمات).
     * اگر فایل نبود/خالی بود null برگردانده می‌شود → «هنوز اجرایی ثبت
     * نشده» یعنی artisan schedule:run اصلاً روی هاست اجرا نشده است.
     *
     * @return array<int, string>|null
     */
    public static function traceTail(int $lines = 5): ?array
    {
        try {
            $path = storage_path('app/cron-trace.log');

            if (! is_file($path)) {
                return null;
            }

            $all = @file($path, FILE_IGNORE_NEW_LINES) ?: [];

            $tail = array_values(array_filter(array_slice($all, -$lines), fn ($l) => trim((string) $l) !== ''));

            return $tail !== [] ? $tail : null;
        } catch (Throwable) {
            return null;
        }
    }
}
