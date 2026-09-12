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
 * php artisan system:cleanup [--days=N] [--scope=sms_logs|audit_logs|notifications|otp|logs|all]
 *
 * دامنه (با نگهداشتِ قابل تنظیم از پنل /admin/system):
 *  - کدهای OTP منقضی
 *  - اعلان‌های خوانده‌شدهٔ قدیمی و خوانده‌نشدهٔ خیلی قدیمی
 *  - لاگ پیامک‌ها، لاگ فعالیت
 *  - روتیشن laravel.log (حجم > ۱۰MB → بایگانی، نگهداشت ۵ نسخه)
 *
 * v29: --scope اجرا را به یک قلم محدود می‌کند (دکمهٔ «پاکسازی قدیمی‌ها»
 * روی صفحات لاگ پیامک / لاگ فعالیت همین مسیر را با scope صدا می‌زنند).
 * حذف همیشه بر اساس تاریخ از قدیمی‌ترین انجام می‌شود.
 *
 * نتیجهٔ هر اجرا در settings (system.cleanup.last) ثبت می‌شود تا
 * صفحهٔ «وضعیت سیستم» آخرین گزارش را زنده نشان دهد.
 */
class CleanupSystem extends Command
{
    protected $signature = 'system:cleanup {--days= : بازنویسی موقت نگهداشت (روز)} {--scope= : محدودکردن اجرا به یک قلم (otp|notifications|sms_logs|audit_logs|logs|all)}';

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

        // v29 — scope: کدام قلم‌ها اجرا شوند (پیش‌فرض: همه)
        $scope = strtolower(trim((string) $this->option('scope') ?: 'all'));
        $map = [
            'otp' => ['otp_codes'],
            'notifications' => ['notifications_read', 'notifications_unread'],
            'sms_logs' => ['sms_logs'],
            'audit_logs' => ['audit_logs'],
            'logs' => ['log_archived'],
        ];
        $targets = $map[$scope] ?? null;
        if ($targets === null) {
            $this->error("scope نامعتبر است: {$scope} (مجاز: otp|notifications|sms_logs|audit_logs|logs|all)");

            return self::INVALID;
        }
        $active = $scope === 'all'
            ? ['otp_codes', 'notifications_read', 'notifications_unread', 'sms_logs', 'audit_logs', 'log_archived']
            : $targets;

        $report = [
            'otp_codes' => 0,
            'notifications_read' => 0,
            'notifications_unread' => 0,
            'sms_logs' => 0,
            'audit_logs' => 0,
            'log_archived' => 0,
        ];

        // هر قلم فقط داخل scope اجرا می‌شود؛ بقیه صفر می‌مانند
        if (in_array('otp_codes', $active, true)) {
            $report['otp_codes'] = DB::table('otp_codes')->where('expires_at', '<', now()->subDays($retention['otp']))->delete();
        }
        if (in_array('notifications_read', $active, true)) {
            $report['notifications_read'] = DB::table('notifications')
                ->whereNotNull('read_at')->where('read_at', '<', now()->subDays($retention['notifications_read']))->delete();
        }
        if (in_array('notifications_unread', $active, true)) {
            $report['notifications_unread'] = DB::table('notifications')
                ->whereNull('read_at')->where('created_at', '<', now()->subDays($retention['notifications_unread']))->delete();
        }
        if (in_array('sms_logs', $active, true)) {
            $report['sms_logs'] = DB::table('sms_logs')->where('created_at', '<', now()->subDays($retention['sms_logs']))->delete();
        }
        if (in_array('audit_logs', $active, true)) {
            $report['audit_logs'] = DB::table('audit_logs')->where('created_at', '<', now()->subDays($retention['audit_logs']))->delete();
        }

        $report['log_archived'] = in_array('log_archived', $active, true)
            ? $this->rotateLog()
            : 0;

        // ثبت گزارش آخرین اجرا + لاگ فعالیت
        $payload = [
            'ran_at' => now()->toIso8601String(),
            'removed' => $report,
            'retention' => $retention,
            'scope' => $scope,
            'via' => $this->option('days') ? 'manual-days' : (app()->runningInConsole() && ! $this->option('days') ? 'schedule/manual' : 'api'),
        ];

        $settings->set('system.cleanup.last', json_encode($payload, JSON_UNESCAPED_UNICODE));
        $settings->flush();

        AuditLogger::log('system.cleanup', null, null, [
            'removed' => array_sum($report),
            'scope' => $scope,
        ], 'پاکسازی دوره‌ای سامانه اجرا شد'.($scope !== 'all' ? ' (فقط: '.$scope.')' : ''));

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
