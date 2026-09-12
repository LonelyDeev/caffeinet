<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * لاگ پیامک‌های ارسال‌شده (v16).
 *
 * GET /admin/sms-logs        — صفحه گزارش
 * GET /admin/sms-logs/data   — AJAX (فیلتر موبایل/قالب/پرووایدر/وضعیت/بازه تاریخ + صفحه‌بندی)
 *
 * هر ارسال (موفق یا ناموفق) توسط SmsManager در جدول sms_logs ثبت می‌شود؛
 * این صفحه همان لاگ را با جزئیات کامل (متن + پاسخ پرووایدر) نمایش می‌دهد.
 */
class SmsLogsController extends Controller
{
    public function index(): View
    {
        return view('back.admin.sms-logs.index', [
            'provider' => app(SmsManager::class)->driver()->name(),
            'providerLabel' => SmsManager::providers()[app(SmsManager::class)->driver()->name()] ?? app(SmsManager::class)->driver()->name(),
            'providers' => self::knownProviders(),
            'retentionDays' => (int) app(\App\Services\Settings\SettingsService::class)
                ->get('system.cleanup.sms_logs', 90),
            'oldCount' => \App\Models\SmsLog::query()
                ->where('created_at', '<', now()->subDays((int) app(\App\Services\Settings\SettingsService::class)->get('system.cleanup.sms_logs', 90)))
                ->count(),
        ]);
    }

    /** داده لاگ‌ها (AJAX + فیلتر + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $query = SmsLog::query();

        // جستجو: موبایل، متن، کلید قالب
        if ($q = trim((string) $request->query('q'))) {
            $digits = en_digits($q);
            $query->where(function ($w) use ($q, $digits) {
                $w->where('mobile', 'like', "%{$digits}%")
                    ->orWhere('template_key', 'like', "%{$q}%")
                    ->orWhere('message', 'like', "%{$q}%");
            });
        }

        if ($status = (string) $request->query('status')) {
            if (in_array($status, ['sent', 'failed'], true)) {
                $query->where('status', $status);
            }
        }

        if ($provider = (string) $request->query('provider')) {
            $query->where('provider', $provider);
        }

        if ($template = trim((string) $request->query('template'))) {
            $query->where('template_key', $template);
        }

        // بازه تاریخ شمسی → میلادی
        if ($from = (string) $request->query('from')) {
            if ($c = jalali_or_iso_to_carbon($from, '00:00')) {
                $query->where('created_at', '>=', $c);
            }
        }

        if ($to = (string) $request->query('to')) {
            if ($c = jalali_or_iso_to_carbon($to, '23:59')) {
                $query->where('created_at', '<=', $c);
            }
        }

        $paginator = $query->latest('id')->paginate(25);

        // عنوان فارسی قالب‌ها (یک کوئری)
        $titles = SmsTemplate::query()->pluck('title', 'key');

        $rows = $paginator->through(fn (SmsLog $log) => [
            'id' => $log->id,
            'mobile' => $log->mobile,
            'template_key' => $log->template_key,
            'template_title' => $log->template_key ? ($titles[$log->template_key] ?? null) : null,
            'message' => $log->message,
            'provider' => $log->provider,
            'status' => $log->status,
            'mode' => (string) ($log->response['mode'] ?? 'plain'),
            'response' => $log->response,
            'created_at' => $log->created_at ? fa_date($log->created_at, 'Y/m/d H:i') : '—',
        ]);

        // آمار کلی (بدون فیلتر) برای چیپ‌های بالای صفحه
        $stats = [
            'total' => SmsLog::count(),
            'sent' => SmsLog::where('status', 'sent')->count(),
            'failed' => SmsLog::where('status', 'failed')->count(),
            'today' => SmsLog::whereDate('created_at', today())->count(),
        ];

        return response()->json([
            'data' => $rows->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'stats' => $stats,
        ]);
    }

    /** پرووایدرهای شناخته‌شده برای فیلتر */
    public static function knownProviders(): array
    {
        return [
            'log' => 'لاگ توسعه',
            'kavenegar' => 'کاوه‌نگار',
            'fraasms' => 'فراز اس‌ام‌اس',
            'ippanel' => 'آی‌پی‌پنل',
            'melipayamak' => 'ملی‌پیامک',
            'idehpardazan' => 'ایده‌پردازان',
        ];
    }
}
