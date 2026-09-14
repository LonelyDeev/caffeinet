<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * v38 — نمایشگر لاگ سیستمی لاراول (نمای اختصاصی، بدون پکیج بیرونی).
 *
 * فایل‌های storage/logs/*.log خوانده، تجزیه و «دسته‌بندی‌شده» نمایش
 * داده می‌شوند: فیلتر سطح (emergency تا debug)، جستجو، صفحه‌بندی،
 * جزئیات کامل هر ورودی (از جمله stack trace) و عملیات مدیریتی:
 *  • خالی‌کردن فایل (truncate — فایل می‌ماند)
 *  • حذف کامل فایل (unlink)
 *
 * امنیت: فقط نام فایل‌های واقعاً موجود در storage/logs پذیرفته
 * می‌شود (basename + whitelist) — امکان پیمایش مسیر وجود ندارد.
 * هر دو عملیات مخرب در AuditLogger ثبت می‌شوند.
 */
class SystemLogsController extends Controller
{
    /** بیشینهٔ حجم خوانده‌شده از انتهای هر فایل (مگابایت) */
    protected const MAX_READ_MB = 8;

    /** سطح‌های Monolog + برچسب فارسی و رنگ بج */
    public const LEVELS = [
        'emergency' => ['اضطراری', 'rose'],
        'alert' => ['هشدار فوری', 'rose'],
        'critical' => ['بحرانی', 'orange'],
        'error' => ['خطا', 'red'],
        'warning' => ['اخطار', 'amber'],
        'notice' => ['توجه', 'sky'],
        'info' => ['اطلاع', 'emerald'],
        'debug' => ['دیباگ', 'stone'],
    ];

    /* ================================================================== */
    /* صفحهٔ اصلی                                                          */
    /* ================================================================== */

    public function index(Request $request): View
    {
        $files = $this->fileList();
        $selected = $this->resolveFile((string) $request->query('file', ''), $files);

        // آمار سطح‌ها + خلاصهٔ فایل انتخابی (برای کارت بالای صفحه)
        $summary = null;
        $levelStats = [];

        if ($selected !== null) {
            $entries = $this->parseFile($selected['path'], $meta);
            $levelStats = $this->levelStats($entries);
            $summary = [
                'name' => $selected['name'],
                'size_fa' => fa_number($selected['size'] / 1024),
                'modified_fa' => fa_date($selected['modified'], 'Y/m/d H:i:s'),
                'count' => count($entries),
                'count_fa' => fa_number(count($entries)),
                'truncated' => ($meta['truncated'] ?? false),
                'total_size' => $selected['size'],
            ];
        }

        return view('back.admin.system-logs.index', [
            'files' => $files,
            'selected' => $selected,
            'summary' => $summary,
            'levelStats' => $levelStats,
            'levelMeta' => self::LEVELS,
        ]);
    }

    /* ================================================================== */
    /* دادهٔ ورودی‌ها (AJAX — فیلتر سطح + جستجو + صفحه‌بندی)                */
    /* ================================================================== */

    public function data(Request $request): JsonResponse
    {
        $files = $this->fileList();
        $selected = $this->resolveFile((string) $request->query('file', ''), $files);

        if ($selected === null) {
            return response()->json(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0, 'file' => null]);
        }

        $level = strtolower(trim((string) $request->query('level', '')));
        if ($level !== '' && ! array_key_exists($level, self::LEVELS)) {
            $level = '';
        }

        $q = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $entries = $this->parseFile($selected['path'], $meta);

        // جدیدترین اول (خوانش طبیعی قدیمی→جدید است)
        $entries = array_reverse($entries);

        // فیلتر سطح
        if ($level !== '') {
            $entries = array_values(array_filter($entries, fn ($e) => $e['level'] === $level));
        }

        // جستجو در متن + stack
        if ($q !== '') {
            $entries = array_values(array_filter($entries, function ($e) use ($q) {
                return mb_stripos($e['message'], $q) !== false
                    || ($e['trace'] !== '' && mb_stripos($e['trace'], $q) !== false);
            }));
        }

        $total = count($entries);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) $request->query('page', 1)), $lastPage);
        $slice = array_slice($entries, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => array_map(fn ($e) => $this->formatEntry($e), array_values($slice)),
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
            'per_page' => $perPage,
            'file' => $selected['name'],
            'truncated' => (bool) ($meta['truncated'] ?? false),
        ]);
    }

    /* ================================================================== */
    /* عملیات مدیریتی                                                      */
    /* ================================================================== */

    /** خالی‌کردن فایل لاگ (truncate — خود فایل حفظ می‌شود) */
    public function clear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'string', 'max:255'],
        ], ['file.required' => 'نام فایل لاگ ارسال نشده است.']);

        $files = $this->fileList();
        $selected = $this->resolveFile($data['file'], $files);

        if ($selected === null) {
            return response()->json(['message' => 'فایل لاگ یافت نشد.'], 404);
        }

        try {
            file_put_contents($selected['path'], '');

            AuditLogger::log('settings.logs_cleared', null,
                ['file' => $selected['name'], 'size' => $selected['size']],
                ['file' => $selected['name'], 'size' => 0],
                'خالی‌کردن فایل لاگ «'.$selected['name'].'» ('.fa_number($selected['size'] / 1024).' کیلوبایت)');

            return response()->json([
                'message' => 'فایل «'.$selected['name'].'» خالی شد؛ لاگ‌های جدید از همین فایل ادامه می‌یابند.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'خالی‌کردن فایل ممکن نشد: '.$e->getMessage()], 500);
        }
    }

    /** حذف کامل فایل لاگ */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'string', 'max:255'],
        ], ['file.required' => 'نام فایل لاگ ارسال نشده است.']);

        $files = $this->fileList();
        $selected = $this->resolveFile($data['file'], $files);

        if ($selected === null) {
            return response()->json(['message' => 'فایل لاگ یافت نشد.'], 404);
        }

        try {
            @unlink($selected['path']);

            AuditLogger::log('settings.logs_deleted', null,
                ['file' => $selected['name'], 'size' => $selected['size']],
                ['file' => $selected['name']],
                'حذف کامل فایل لاگ «'.$selected['name'].'» ('.fa_number($selected['size'] / 1024).' کیلوبایت)');

            return response()->json([
                'message' => 'فایل «'.$selected['name'].'» حذف شد؛ در صورت نیاز لاراول دوباره می‌سازدش.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'حذف فایل ممکن نشد: '.$e->getMessage()], 500);
        }
    }

    /* ================================================================== */
    /* ابزار داخلی                                                         */
    /* ================================================================== */

    /**
     * فهرست فایل‌های لاگ موجود (نام، حجم، زمان آخرین نوشت) —
     * فقط *.log داخل storage/logs؛ بدون پیمایش مسیر.
     *
     * @return array<int, array{name:string, path:string, size:int, modified:int, modified_carbon:\Illuminate\Support\Carbon}>
     */
    protected function fileList(): array
    {
        $dir = storage_path('logs');

        if (! is_dir($dir)) {
            return [];
        }

        $files = [];

        foreach (glob($dir.'/*.log') ?: [] as $path) {
            $name = basename($path);

            // فقط نام ساده (بدون مسیر) — دفاع عمیق در برابر payload عجیب
            if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
                continue;
            }

            $files[] = [
                'name' => $name,
                'path' => $path,
                'size' => (int) @filesize($path),
                'modified' => (int) @filemtime($path),
                'modified_carbon' => \Illuminate\Support\Carbon::createFromTimestamp((int) @filemtime($path)),
            ];
        }

        // تازه‌ترین اول
        usort($files, fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $files;
    }

    /**
     * انتخاب فایل معتبر از query — فقط اگر در فهرست واقعی موجود باشد.
     *
     * نام خالی → پیش‌فرض (تازه‌ترین)؛ نام نامعتبر/ناموجود → null
     * (بدون fallback — تا اشتباه یا payload عجیب هرگز به فایل دیگری
     * اعمال نشود).
     *
     * @param  array  $files  خروجی fileList()
     */
    protected function resolveFile(string $name, array $files): ?array
    {
        // نام ارائه‌شده اما ناسالم (مسیر/نال) → هیچ فایلی
        if ($name !== '' && (str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0"))) {
            return null;
        }

        if ($name === '') {
            return $files[0] ?? null; // پیش‌فرض: تازه‌ترین
        }

        foreach ($files as $f) {
            if ($f['name'] === $name) {
                return $f;
            }
        }

        return null; // ناموجود — نه fallback به فایل دیگر
    }

    /**
     * تجزیهٔ فایل لاگ لاراول به ورودی‌های مجزا.
     *
     * فرمت خط سرآغاز Monolog:
     *   [2026-09-14 13:22:06] production.ERROR: پیام ... {context}
     * خط‌های بعدی تا سرآغاز بعدی = ادامهٔ پیام / stack trace.
     *
     * @param  array|null  $meta  [out] truncated => آیا فقط انتهای فایل خوانده شد
     * @return array<int, array{ts:string, env:string, level:string, message:string, trace:string}>
     */
    protected function parseFile(string $path, ?array &$meta = null): array
    {
        $meta = ['truncated' => false];
        $entries = [];

        try {
            $size = (int) @filesize($path);

            if ($size === 0) {
                return $entries;
            }

            $maxBytes = self::MAX_READ_MB * 1024 * 1024;

            $handle = fopen($path, 'rb');

            if ($handle === false) {
                return $entries;
            }

            if ($size > $maxBytes) {
                fseek($handle, -$maxBytes, SEEK_END);
                stream_get_line($handle, 8192, "\n"); // پرش از خط نیمه‌کارهٔ ابتدای پنجره
                $meta['truncated'] = true;
            }

            $content = stream_get_contents($handle) ?: '';
            fclose($handle);
        } catch (Throwable) {
            return $entries;
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        $current = null;
        $header = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+([a-zA-Z0-9_.-]+)?\.([A-Za-z]+):\s?(.*)$/';

        foreach ($lines as $line) {
            if (preg_match($header, $line, $m) === 1) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $level = strtolower($m[3]);

                $current = [
                    'ts' => $m[1],
                    'env' => $m[2] ?: '—',
                    'level' => array_key_exists($level, self::LEVELS) ? $level : strtolower($level),
                    'message' => $m[4],
                    'trace' => '',
                ];

                continue;
            }

            // خط تابع به ورودی جاری (پیام چندخطی یا stack trace)
            if ($current !== null) {
                $current['trace'] .= ($current['trace'] === '' ? '' : "\n").$line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        // پاک‌سازی انتهای stack و بج‌گذاری سطح ناشناخته
        foreach ($entries as &$e) {
            $e['trace'] = rtrim($e['trace']);

            if (! array_key_exists($e['level'], self::LEVELS)) {
                $e['level'] = 'debug';
            }
        }
        unset($e);

        return $entries;
    }

    /** شمار ورودی‌ها به تفکیک سطح */
    protected function levelStats(array $entries): array
    {
        $stats = array_fill_keys(array_keys(self::LEVELS), 0);

        foreach ($entries as $e) {
            if (isset($stats[$e['level']])) {
                $stats[$e['level']]++;
            }
        }

        return array_filter($stats, fn ($c) => $c > 0);
    }

    /** شکل نهایی هر ورودی برای JSON (تاریخ شمسی + رنگ بج) */
    protected function formatEntry(array $e): array
    {
        $carbon = \Illuminate\Support\Carbon::parse($e['ts']);

        return [
            'ts' => $e['ts'],
            'date_fa' => fa_date($carbon, 'Y/m/d'),
            'time_fa' => fa_date($carbon, 'H:i:s'),
            'env' => $e['env'],
            'level' => $e['level'],
            'level_fa' => self::LEVELS[$e['level']][0] ?? $e['level'],
            'tone' => self::LEVELS[$e['level']][1] ?? 'stone',
            'message' => mb_substr($e['message'], 0, 400),
            'trace' => $e['trace'] !== '' ? mb_substr($e['trace'], 0, 12000) : null,
        ];
    }
}
