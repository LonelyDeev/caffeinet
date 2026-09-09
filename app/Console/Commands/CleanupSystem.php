<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * فاز ۱۱ — پاکسازی دوره‌ای سامانه.
 *
 * php artisan system:cleanup
 *
 * دامنه (با نگهداشتِ قابل تنظیم از پنل /admin/system):
 *  - کدهای OTP منقضی
 *  - اعلان‌های خوانده‌شدهٔ قدیمی و خوانده‌نشدهٔ خیلی قدیمی
 *  - لاگ پیامک‌ها، لاگ فعالیت
 *  - روتیشن laravel.log (حجم > ۱۰MB → بایگانی، نگهداشت ۵ نسخه)
 *
 * نتیجهٔ هر اجرا در settings (system.cleanup.last) ثبت می‌شود تا
 * صفحهٔ «وضعیت سیستم» آخرین گزارش را زنده نشان دهد.
 */
class CleanupSystem extends Command
{
    protected $signature = 'system:cleanup {--days= : بازنویسی موقت نگهداشت (روز)}';

    protected $description = 'پاکسازی دوره‌ای داده‌های موقت و لاگ‌های قدیمی';

    public function handle(SettingsService $settings): int
    {
        $days = (int) ($this->option('days') ?: 0);

        $retention = [
            'otp' => 1,                                   // OTP منقضی: ۱ روز
            'notifications_read' => $this->opt($settings, $days, 'system.cleanup.notifications_read', 30),
            'notifications_unread' => $this->opt($settings, $days, 'system.cleanup.notifications_unread', 90),
            'sms_logs' => $this->opt($settings, $days, 'system.cleanup.sms_logs', 90),
            'audit_logs' => $this->opt($settings, $days, 'system.cleanup.audit_logs', 365),
        ];

        $report = [
            'otp_codes' => DB::table('otp_codes')->where('expires_at', '<', now()->subDays($retention['otp']))->delete(),
            'notifications_read' => DB::table('notifications')
                ->whereNotNull('read_at')->where('read_at', '<', now()->subDays($retention['notifications_read']))->delete(),
            'notifications_unread' => DB::table('notifications')
                ->whereNull('read_at')->where('created_at', '<', now()->subDays($retention['notifications_unread']))->delete(),
            'sms_logs' => DB::table('sms_logs')->where('created_at', '<', now()->subDays($retention['sms_logs']))->delete(),
            'audit_logs' => DB::table('audit_logs')->where('created_at', '<', now()->subDays($retention['audit_logs']))->delete(),
        ];

        $report['log_archived'] = $this->rotateLog();

        // ثبت گزارش آخرین اجرا + لاگ فعالیت
        $payload = [
            'ran_at' => now()->toIso8601String(),
            'removed' => $report,
            'retention' => $retention,
            'via' => $this->option('days') ? 'manual-days' : (app()->runningInConsole() && ! $this->option('days') ? 'schedule/manual' : 'api'),
        ];

        $settings->set('system.cleanup.last', json_encode($payload, JSON_UNESCAPED_UNICODE));
        $settings->flush();

        AuditLogger::log('system.cleanup', null, null, [
            'removed' => array_sum($report),
        ], 'پاکسازی دوره‌ای سامانه اجرا شد');

        $this->table(['قلم', 'تعداد حذف/عمل'], [
            ['کد OTP منقضی', fa_digits($report['otp_codes'])],
            ['اعلان خوانده‌شدهٔ قدیمی', fa_digits($report['notifications_read'])],
            ['اعلان خوانده‌نشدهٔ قدیمی', fa_digits($report['notifications_unread'])],
            ['لاگ پیامک قدیمی', fa_digits($report['sms_logs'])],
            ['لاگ فعالیت قدیمی', fa_digits($report['audit_logs'])],
            ['بایگانی لاگ لاراول', $report['log_archived'] ?: 'لازم نشد'],
        ]);

        $this->info('گزارش اجرا در تنظیمات (system.cleanup.last) ذخیره شد.');

        return self::SUCCESS;
    }

    /** مقدار نگهداشت: فلگ موقت > تنظیم پنل > پیش‌فرض */
    private function opt(SettingsService $settings, int $flag, string $key, int $default): int
    {
        if ($flag > 0) {
            return $flag;
        }

        return max(1, (int) $settings->get($key, $default));
    }

    /** روتیشن حجمی لاگ: بالای ۱۰MB بایگانی و نگهداشت ۵ نسخهٔ آخر. */
    private function rotateLog(int $maxMb = 10, int $keep = 5): int
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path) || filesize($path) < $maxMb * 1024 * 1024) {
            return 0;
        }

        $archive = storage_path('logs/laravel-'.now()->format('Ymd-His').'.log');

        // با rename اتمیک: نویسندهٔ فعال ادامه می‌دهد و فایل جدید می‌سازد
        @rename($path, $archive);

        if (! is_file($archive)) {
            return 0;
        }

        $archives = collect(glob(storage_path('logs/laravel-*.log')) ?? [])
            ->sortDesc()
            ->values();

        $archives->slice($keep)->each(fn ($f) => @unlink($f));

        // فایل فعال را فوراً بازسازی می‌کنیم تا نویسنده‌های باز سالم بمانند
        @touch($path);

        return 1;
    }
}
