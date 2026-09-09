<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use App\Services\Audit\AuditLogger;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * مرکز پیامک (فاز ۱۰ + بازطراحی بازخوردی ۶-۶).
 *
 * GET  /admin/sms-templates             — مرکز پیامک (گروه‌بندی دسته + جستجو + وضعیت)
 * GET  /admin/sms-templates/data        — AJAX (همهٔ قالب‌ها + متادیتا)
 * PUT  /admin/sms-templates/{template}  — ذخیرهٔ متن/عنوان/پترن/وضعیت
 * POST /admin/sms-templates/{template}/test — پیش‌نمایش رندر + ارسال آزمایشی
 *
 * پترن: pattern_code کد ثبت‌شده در پنل پرووایدر است؛ اگر پر باشد و
 * پرووایدر فعال (کاوه‌نگار) باشد، ارسال از مسیر VerifyLookup می‌رود.
 */
class SmsTemplatesController extends Controller
{
    public function __construct(
        protected SmsTemplateService $templates,
    ) {}

    public function index(): View
    {
        return view('back.admin.sms-templates.index', [
            'provider' => app(SmsManager::class)->driver()->name(),
        ]);
    }

    public function data(): JsonResponse
    {
        $rows = SmsTemplate::query()->orderBy('category')->orderBy('key')->get()->map(fn (SmsTemplate $t) => [
            'id' => $t->id,
            'key' => $t->key,
            'title' => $t->title,
            'body' => $t->body,
            'variables' => $t->variables,
            'category' => $t->category,
            'pattern_code' => $t->pattern_code,
            'is_active' => (bool) $t->is_active,
            'updated_at' => $t->updated_at ? fa_date($t->updated_at, 'Y/m/d H:i') : null,
        ]);

        return response()->json([
            'data' => $rows,
            'categories' => SmsTemplate::CATEGORIES,
        ]);
    }

    public function update(Request $request, SmsTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:1000'],
            'pattern_code' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'عنوان قالب الزامی است.',
            'body.required' => 'متن قالب الزامی است.',
            'pattern_code.max' => 'کد پترن حداکثر ۱۰۰ کاراکتر است.',
        ]);

        $old = $template->only(['title', 'body', 'is_active', 'pattern_code']);

        $template->update([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'pattern_code' => trim((string) ($data['pattern_code'] ?? '')) ?: null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        $this->templates->flush($template->key);

        AuditLogger::log('sms.template_updated', $template, $old,
            ['title' => $template->title, 'body' => $template->body, 'is_active' => $template->is_active, 'pattern_code' => $template->pattern_code],
            'ویرایش قالب پیامک «'.$template->key.'»');

        return response()->json([
            'message' => 'قالب «'.$template->title.'» ذخیره شد.',
            'data' => [
                'id' => $template->id,
                'key' => $template->key,
                'title' => $template->title,
                'body' => $template->body,
                'pattern_code' => $template->pattern_code,
                'is_active' => (bool) $template->is_active,
            ],
        ]);
    }

    /** فعال/غیرفعال سریع (AJAX) — درخواست بازخوردی ۶-۶ */
    public function toggle(Request $request, SmsTemplate $template): JsonResponse
    {
        $old = ['is_active' => $template->is_active];

        $template->update(['is_active' => ! $template->is_active]);
        $this->templates->flush($template->key);

        AuditLogger::log('sms.template_toggled', $template, $old,
            ['is_active' => $template->is_active],
            ($template->is_active ? 'فعال‌سازی' : 'غیرفعال‌سازی').' پیامک «'.$template->key.'»');

        return response()->json([
            'message' => $template->is_active
                ? 'پیامک «'.$template->title.'» فعال شد.'
                : 'پیامک «'.$template->title.'» غیرفعال شد.',
            'is_active' => (bool) $template->is_active,
        ]);
    }

    /** پیش‌نمایش رندر با متغیرهای نمونه + ارسال آزمایشی اختیاری */
    public function test(Request $request, SmsTemplate $template, SmsManager $sms): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],
            'send' => ['nullable', 'boolean'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex' => 'فرمت موبایل صحیح نیست (مثل 09123456789).',
        ]);

        $appName = (string) app(\App\Services\Settings\SettingsService::class)->get('general.app_name', 'کافی‌نت آنلاین');

        // متغیرهای نمونه برای پیش‌نمایش + ارسال آزمایشی (v10 — همان vars به پترن می‌رود)
        $sampleVars = [
            'code' => '۱۲۳۴۵',
            'minutes' => '۳',
            'order_number' => 'CN050615-9441',
            'trace' => 'TRC-8412',
            'accepted_by' => 'کافی‌نت «نمونه»',
            'coffeenet' => 'نمونه',
            'operator' => 'نمونه',
            'reason' => 'نمونهٔ دلیل',
            'amount' => '۲۵۰٫۰۰۰',
            'balance' => '۱٫۲۵۰٫۰۰۰',
            'ticket_number' => 'TKT-1024',
            'app_name' => $appName,
        ];

        $preview = $this->templates->compose($template->key, $sampleVars);

        if (! ($data['send'] ?? false)) {
            return response()->json([
                'message' => 'پیش‌نمایش ساخته شد.',
                'preview' => $preview ?? '(قالب فعال نیست)',
                'sent' => false,
            ]);
        }

        if (! $preview) {
            return response()->json([
                'message' => 'قالب غیرفعال است؛ برای ارسال، فعالش کنید.',
            ], 422);
        }

        // v10 — مسیر واقعی ارسال (قالبی/پترنی): اگر pattern_code ثبت شده باشد
        // ارسال آزمایشی هم از همان مسیر پترن پرووایدر انجام می‌شود.
        $result = $sms->sendTemplate($data['mobile'], $template->key, $sampleVars, $preview);

        $modeLabel = ($result['mode'] ?? 'plain') === 'pattern' ? 'پترنی' : 'متنی';

        AuditLogger::log('sms.template_test_sent', $template, null,
            ['mobile' => $data['mobile'], 'ok' => $result['ok'], 'mode' => $result['mode'] ?? 'plain'],
            'ارسال آزمایشی قالب «'.$template->key.'» ('.$modeLabel.')');

        if (! $result['ok']) {
            return response()->json([
                'message' => 'ارسال ناموفق: '.$result['error'],
                'preview' => $preview,
            ], 422);
        }

        return response()->json([
            'message' => 'پیامک آزمایشی ارسال شد (پرووایدر فعال: '.$sms->driver()->name().' — '.$modeLabel.').',
            'preview' => $preview,
            'sent' => true,
        ]);
    }
}
