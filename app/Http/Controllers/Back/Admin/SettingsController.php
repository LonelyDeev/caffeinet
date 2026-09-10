<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Services\Sms\SmsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** کلیدهای مجاز هر گروه (whitelist) */
    protected const GROUP_KEYS = [
        'general' => ['general.app_name'],
        'sms' => [
            'sms.provider',
            'sms.fraasms.api_key', 'sms.fraasms.sender', 'sms.fraasms.endpoint',
            'sms.kavenegar.api_key', 'sms.kavenegar.sender', 'sms.kavenegar.endpoint',
            'sms.ippanel.username', 'sms.ippanel.password', 'sms.ippanel.from', 'sms.ippanel.endpoint',
            'sms.melipayamak.username', 'sms.melipayamak.password', 'sms.melipayamak.from', 'sms.melipayamak.endpoint',
            'sms.idehpardazan.api_key', 'sms.idehpardazan.secret_key', 'sms.idehpardazan.endpoint',
        ],
        'orders' => [
            'orders.broadcast_scope', 'orders.broadcast_timeout',
            'orders.assign_after_timeout',
        ],
        'workhours' => [
            'workhours.enabled', 'workhours.start', 'workhours.end',
            'workhours.days', 'workhours.message',
        ],
        'payment' => [
            'payment.driver', 'payment.zarinpal.merchant_id',
            'payment.zarinpal.sandbox', 'payment.zibal.merchant_id',
            'payment.behpardakht.terminal_id', 'payment.behpardakht.username', 'payment.behpardakht.password',
            'payment.sep.terminal_id', 'payment.sepehr.terminal_id',
        ],
        'staff' => ['staff.hiring.mode'],
        'realtime' => [
            'realtime.pusher.enabled', 'realtime.pusher.app_id',
            'realtime.pusher.app_key', 'realtime.pusher.app_secret',
            'realtime.pusher.cluster',
        ],
    ];

    public function edit(): View
    {
        $settings = app(SettingsService::class);

        // وضعیت پوشر برای تب Realtime (محاسبه در کنترلر — blade تمیز می‌ماند)
        $pusherOn = (bool) $settings->get('realtime.pusher.enabled');
        $pusherReady = $pusherOn
            && trim((string) $settings->get('realtime.pusher.app_key')) !== ''
            && trim((string) $settings->get('realtime.pusher.app_secret')) !== ''
            && trim((string) $settings->get('realtime.pusher.app_id')) !== '';

        return view('back.admin.settings.index', [
            'settings' => $settings,
            'providers' => SmsManager::providers(),
            'referral' => \App\Models\ReferralSetting::current(),
            'pusherOn' => $pusherOn,
            'pusherReady' => $pusherReady,
        ]);
    }

    /** ذخیره تنظیمات یک گروه (AJAX) */
    public function save(Request $request, SettingsService $settings): JsonResponse
    {
        $data = $request->validate([
            'group' => ['required', 'string', 'in:'.implode(',', array_keys(self::GROUP_KEYS))],
            'values' => ['required', 'array'],
        ]);

        $allowed = self::GROUP_KEYS[$data['group']];

        $pairs = collect($data['values'])
            ->only($allowed)
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => $v !== null)
            ->all();

        $old = collect($settings->all())->only(array_keys($pairs))->all();

        $count = $settings->updateMany($pairs);

        AuditLogger::log('settings.updated', null, $old, $pairs,
            "بروزرسانی تنظیمات گروه «{$data['group']}» ({$count} مورد)");

        return response()->json([
            'message' => "تنظیمات با موفقیت ذخیره شد ({$count} مورد).",
        ]);
    }

    /** ذخیره تنظیمات پاداش معرفی (AJAX — فاز ۲) */
    public function saveReferral(Request $request): JsonResponse
    {
        $data = $request->validate([
            'introduction_reward' => ['required', 'numeric', 'min:0'],
            'per_order_type' => ['required', 'in:percent,fixed'],
            'per_order_value' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ], [
            'introduction_reward.required' => 'مبلغ پاداش اولیه الزامی است (۰ = بدون پاداش).',
            'introduction_reward.min' => 'پاداش اولیه نمی‌تواند منفی باشد.',
            'per_order_value.required' => 'مقدار پاداش هر سفارش الزامی است.',
        ]);

        $referral = \App\Models\ReferralSetting::current();
        $old = $referral->only(['introduction_reward', 'per_order_type', 'per_order_value', 'is_active']);

        $referral->update([
            'introduction_reward' => (float) $data['introduction_reward'],
            'per_order_type' => $data['per_order_type'],
            'per_order_value' => (float) $data['per_order_value'],
            'is_active' => (bool) $data['is_active'],
        ]);

        AuditLogger::log('settings.referral_updated', $referral, $old, [
            'introduction_reward' => (float) $data['introduction_reward'],
            'per_order_type' => $data['per_order_type'],
            'per_order_value' => (float) $data['per_order_value'],
            'is_active' => (bool) $data['is_active'],
        ], 'بروزرسانی تنظیمات پاداش معرفی');

        return response()->json([
            'message' => 'تنظیمات پاداش معرفی ذخیره شد.',
        ]);
    }

    /** تست اتصال پوشر (AJAX — اعتبارسنجی اعتبارنامه‌ها + رویداد آزمایشی) */
    public function testPusher(Request $request, \App\Services\Realtime\PusherService $pusher): JsonResponse
    {
        $result = $pusher->test();

        if ($result['ok']) {
            $event = $pusher->sendTestEvent();
            $result['message'] .= ' · '.$event['message'];
        }

        AuditLogger::log('settings.pusher_test', null, null, ['ok' => $result['ok']],
            'تست اتصال Pusher از پنل تنظیمات — '.($result['ok'] ? 'موفق' : 'ناموفق'));

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * ارسال پیامک آزمایشی (AJAX) — v10: مسیر قالبی/پترنی.
     * اگر برای قالب «test» کد پترن ثبت شده باشد ارسال پترنی انجام می‌شود؛
     * در غیر این صورت متن آزمایشی (در درایور پترن‌محور خطا می‌شود).
     */
    public function testSms(Request $request, SmsManager $sms): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex' => 'فرمت موبایل صحیح نیست (مثل 09123456789).',
        ]);

        $result = $sms->sendTemplate(
            $data['mobile'],
            'test',
            [],
            'پیامک آزمایشی «کافی‌نت آنلاین» — تنظیمات پیامک با موفقیت ذخیره شد.',
        );

        AuditLogger::log('sms.test_sent', null, null,
            ['mobile' => $data['mobile'], 'ok' => $result['ok'], 'mode' => $result['mode'] ?? 'plain'],
            'ارسال پیامک آزمایشی از پنل تنظیمات ('.($result['mode'] ?? 'plain').')');

        if (! $result['ok']) {
            return response()->json([
                'message' => 'ارسال ناموفق: '.($result['error'] ?? 'خطای نامشخص'),
            ], 422);
        }

        $modeLabel = ($result['mode'] ?? 'plain') === 'pattern' ? 'پترنی' : 'متنی';

        return response()->json([
            'message' => 'پیامک آزمایشی ارسال شد (پرووایدر فعال: '.$sms->driver()->name().' — '.$modeLabel.')',
        ]);
    }
}
