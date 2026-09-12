<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\SecureFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * فاز ۱۱ — وضعیت سیستم و نگهداشت.
 *
 * نمای یکپارچهٔ سلامت/امنیت + اجرای دستی پاکسازی و رمزنگاری بک‌فیل
 * + تنظیم نگهداشت داده‌ها. فقط مدیر کل.
 */
class SystemController extends Controller
{
    public function __construct(protected SettingsService $settings) {}

    /** GET /admin/system */
    public function index()
    {
        return view('back.admin.system.index', [
            'scheduler' => $this->schedulerInfo(),
            'files' => $this->filesInfo(),
            'security' => $this->securityInfo(),
            'retention' => [
                'notifications_read' => (int) $this->settings->get('system.cleanup.notifications_read', 30),
                'notifications_unread' => (int) $this->settings->get('system.cleanup.notifications_unread', 90),
                'sms_logs' => (int) $this->settings->get('system.cleanup.sms_logs', 90),
                'audit_logs' => (int) $this->settings->get('system.cleanup.audit_logs', 365),
            ],
            'lastCleanup' => $this->lastCleanup(),
            'resources' => $this->resourcesInfo(),
        ]);
    }

    /** POST /admin/system/cleanup — اجرای دستی پاکسازی دوره‌ای (v29: scope اختیاری) */
    public function runCleanup(Request $request): JsonResponse
    {
        $scope = strtolower(trim((string) $request->input('scope', 'all')));
        $allowed = ['all', 'otp', 'notifications', 'sms_logs', 'audit_logs', 'logs'];

        if (! in_array($scope, $allowed, true)) {
            return response()->json(['message' => 'دامنهٔ پاکسازی نامعتبر است.'], 422);
        }

        $days = (int) $request->input('days', 0);
        $args = $days > 0 ? ['--days' => $days] : [];

        if ($scope !== 'all') {
            $args['--scope'] = $scope;
        }

        Artisan::call('system:cleanup', $args);

        $last = $this->lastCleanup();
        $removed = $last['removed'][$scope] ?? null;

        AuditLogger::log('system.cleanup.manual', null, null, $args ?: null,
            'اجرای دستی پاکسازی از پنل'.($scope !== 'all' ? ' (فقط: '.$scope.')' : ''));

        $message = 'پاکسازی اجرا شد.';
        if ($scope !== 'all' && $removed !== null) {
            $message = 'پاکسازی اجرا شد؛ '.fa_number((int) $removed).' ردیف قدیمی حذف شد.';
        }

        return response()->json([
            'message' => $message,
            'report' => $last,
        ]);
    }

    /**
     * POST /admin/system/retention — ذخیرهٔ نگهداشت‌ها (v29: partial).
     * فقط فیلدهای ارسال‌شده ذخیره می‌شوند تا صفحات لاگ بتوانند تک‌فیلدی ذخیره کنند.
     */
    public function saveRetention(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notifications_read' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'notifications_unread' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'sms_logs' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'audit_logs' => ['sometimes', 'integer', 'min:7', 'max:3650'],
        ], [
            '*.sometimes' => 'مقدار ارسال‌شده معتبر نیست.',
            '*.integer' => 'عدد صحیح وارد کنید.',
            '*.min' => 'مقدار کمتر از حد مجاز است.',
            '*.max' => 'مقدار بیشتر از حد مجاز (۱۰ سال) است.',
        ]);

        if (empty($data)) {
            return response()->json(['message' => 'هیچ فیلدی برای ذخیره ارسال نشد.'], 422);
        }

        $pairs = [];
        foreach ($data as $field => $value) {
            $pairs['system.cleanup.'.$field] = (int) $value;
        }

        $this->settings->updateMany($pairs);

        AuditLogger::log('system.retention.updated', null, null, $data, 'بروزرسانی نگهداشت داده‌ها ('.fa_number(count($pairs)).' مورد)');

        $labels = [
            'notifications_read' => 'اعلان خوانده‌شده',
            'notifications_unread' => 'اعلان خوانده‌نشده',
            'sms_logs' => 'لاگ پیامک',
            'audit_logs' => 'لاگ فعالیت',
        ];
        $names = array_map(fn ($f) => $labels[$f] ?? $f, array_keys($data));

        return response()->json([
            'message' => 'نگهداشت '.implode('، ', $names).' ذخیره شد.',
        ]);
    }

    /** POST /admin/system/encrypt — رمزنگاری فایل‌های خام باقی‌مانده */
    public function encryptFiles(): JsonResponse
    {
        $exit = Artisan::call('files:encrypt');

        AuditLogger::log('system.files.encrypt', null, null, ['exit' => $exit], 'اجرای رمزنگاری فایل‌های خصوصی از پنل');

        return response()->json([
            'message' => $exit === 0 ? 'رمزنگاری فایل‌ها تکمیل شد.' : 'خطا در رمزنگاری — لاگ را ببینید.',
            'files' => $this->filesInfo(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** برنامهٔ زمان‌بندی ثبت‌شده — از schedule:list (کش ۶۰ ثانیه). */
    private function schedulerInfo(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('admin.system.schedule', now()->addMinute(), function () {
            Artisan::call('schedule:list', ['--json' => true]);

            $rows = json_decode((string) Artisan::output(), true) ?: [];

            return collect($rows)->map(function ($row) {
                // «php artisan system:cleanup» → system:cleanup
                $command = trim((string) preg_replace('/^php\s+artisan\s+/', '', $row['command'] ?? ''));

                return [
                    'command' => $command,
                    'expression' => $row['expression'] ?? '',
                    'description' => $row['description'] ?? $command,
                    'next' => $row['next_due_date_human'] ?? null,
                ];
            })->values()->all();
        });
    }

    /** وضعیت رمزنگاری فایل‌های خصوصی. */
    private function filesInfo(): array
    {
        $disk = Storage::disk(SecureFile::DISK);

        $encrypted = 0;
        $plain = 0;
        $bytes = 0;

        foreach (SecureFile::SECURE_PATHS as $folder) {
            foreach ($disk->allFiles($folder) as $path) {
                $bytes += $disk->size($path);

                // فقط ۸ بایت اول خوانده می‌شود (بدون بارگذاری کل فایل)
                $stream = $disk->readStream($path);
                $head = fread($stream, 8);
                fclose($stream);

                str_starts_with($head, 'CNENC1:') ? $encrypted++ : $plain++;
            }
        }

        return [
            'encrypted' => $encrypted,
            'plain' => $plain,
            'total' => $encrypted + $plain,
            'bytes' => $bytes,
            'disk' => SecureFile::DISK,
        ];
    }

    /** چک‌لیست امنیت پروداکشن. */
    private function securityInfo(): array
    {
        $checks = [
            ['label' => 'APP_DEBUG خاموش', 'ok' => ! config('app.debug'), 'hint' => 'در پروداکشن باید false باشد.'],
            ['label' => 'APP_ENV پروداکشن', 'ok' => app()->isProduction(), 'hint' => 'در سرور اصلی باید production باشد.'],
            ['label' => 'کلید اپلیکیشن (APP_KEY)', 'ok' => (bool) config('app.key') && ! str_contains((string) config('app.key'), 'base64:SomeRandom'), 'hint' => 'کلید رمزنگاری نشست/کوکی.'],
            ['label' => 'کلید مستقل رمزنگاری فایل', 'ok' => trim((string) env('FILE_ENCRYPTION_KEY')) !== '', 'hint' => 'FILE_ENCRYPTION_KEY اختیاری؛ بدون آن کلیدِ مشتق از APP_KEY استفاده می‌شود.'],
            ['label' => 'اتصال امن HTTPS', 'ok' => request()->isSecure(), 'hint' => 'روی پروداکشن با SSL/TERMINATE پروکسی.'],
            ['label' => 'هدرهای امنیتی + CSP', 'ok' => true, 'hint' => 'SecurityHeaders روی همهٔ پاسخ‌ها فعال است.'],
            ['label' => 'محدودیت نرخ OTP', 'ok' => true, 'hint' => '۲/دقیقه هر شماره + ۱۰/ساعت هر IP.'],
            ['label' => 'محدودیت نرخ API', 'ok' => true, 'hint' => '۱۲۰ درخواست در دقیقه هر کاربر احرازشده.'],
            ['label' => 'قفل تلاش ورود پنل‌ها', 'ok' => true, 'hint' => '۵ تلاش ناموفق → ۶۰ ثانیه تعلیق (۴ پنل).'],
        ];

        return $checks;
    }

    /** آخرین گزارش پاکسازی. */
    private function lastCleanup(): ?array
    {
        $raw = $this->settings->get('system.cleanup.last');

        if (! $raw) {
            return null;
        }

        $data = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($data) ? $data : null;
    }

    /** مصرف منابع (db/لاگ/دیسک خصوصی). */
    private function resourcesInfo(): array
    {
        $dbPath = database_path('database.sqlite');
        $log = storage_path('logs/laravel.log');

        $archives = collect(glob(storage_path('logs/laravel-*.log')) ?? [])->count();

        return [
            'database' => is_file($dbPath) ? (int) @filesize($dbPath) : 0,
            'log' => is_file($log) ? (int) @filesize($log) : 0,
            'log_archives' => $archives,
        ];
    }
}
