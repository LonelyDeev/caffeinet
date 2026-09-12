<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Push\PushManager;
use App\Services\Settings\SettingsService;
use App\Services\Sms\SmsManager;
use App\Support\WebPushCrypto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** کلیدهای مجاز هر گروه (whitelist) */
    protected const GROUP_KEYS = [
        'general' => ['general.app_name', 'general.timezone'],
        'sms' => [
            'sms.provider',
            'sms.fraasms.api_key', 'sms.fraasms.sender', 'sms.fraasms.endpoint',
            'sms.kavenegar.api_key', 'sms.kavenegar.sender', 'sms.kavenegar.endpoint',
            'sms.ippanel.username', 'sms.ippanel.password', 'sms.ippanel.from', 'sms.ippanel.endpoint',
            'sms.melipayamak.username', 'sms.melipayamak.password', 'sms.melipayamak.from', 'sms.melipayamak.endpoint',
            'sms.idehpardazan.api_key', 'sms.idehpardazan.secret_key', 'sms.idehpardazan.endpoint',
            // v25 — رویدادهای اطلاع‌رسانی پیامکی
            'sms.notify.ticket_reply',
            'sms.notify.transfer_offline',
            'sms.notify.salary',
            'sms.notify.unaccepted',
            'sms.notify.unaccepted_minutes',
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
        'notifications' => [
            'notification.sound.enabled',
            'notification.sound.use_default',
            'notification.push.provider',
            // v29 — آستانهٔ آفلاین انتخابی (ثانیه‌ای + لحظه‌ای)
            'notification.push.offline_enabled',
            'notification.push.offline_seconds',
            'notification.push.firebase.project_id',
            'notification.push.firebase.sender_id',
            'notification.push.firebase.api_key',
            'notification.push.firebase.app_id',
            // v26 — پوشر Beams (کلید خصوصی VAPID وب‌پوش هرگز از فرم نمی‌آید)
            'notification.push.beams.instance_id',
            'notification.push.beams.primary_key',
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
            'notificationStats' => $this->notificationStats($settings),
        ]);
    }

    /** آمار/وضعیت تب اعلان‌ها (v25/v26) */
    private function notificationStats(SettingsService $settings): array
    {
        $push = app(PushManager::class);
        $soundFile = trim((string) $settings->get('notification.sound.file', ''));

        return [
            'sound_custom' => $soundFile !== '' ? [
                'name' => basename($soundFile),
                'url' => media_url('sounds/'.$soundFile),
            ] : null,
            'push_provider' => $push->provider(),
            'push_enabled' => $push->enabled(),
            'push_provider_label' => $push->providerLabel(),
            'webpush_public' => $push->webpush()->publicKey(),
            'beams_ready' => $push->beams()->ready(),
            'firebase_ready' => $push->provider() === 'firebase'
                && trim((string) $settings->get('notification.push.firebase.sender_id')) !== ''
                && trim((string) $settings->get('notification.push.firebase.api_key')) !== '',
            'push_tokens' => \App\Models\PushToken::query()->count(),
        ];
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

        // v29 — اعتبارسنجی منطقهٔ زمانی (IANA معتبر)
        if (isset($pairs['general.timezone'])
            && ! in_array($pairs['general.timezone'], timezone_identifiers_list(), true)) {
            return response()->json([
                'message' => 'منطقهٔ زمانی انتخاب‌شده معتبر نیست.',
            ], 422);
        }

        // v29 — آستانهٔ آفلاین: خالی → کلید نادیده (مقدار موجود حفظ شود)؛ در غیر این صورت ۱..۸۶۴۰۰
        if (isset($pairs['notification.push.offline_seconds'])) {
            if ($pairs['notification.push.offline_seconds'] === '') {
                unset($pairs['notification.push.offline_seconds']);
            } else {
                $pairs['notification.push.offline_seconds'] = (string) max(1, min(86400, (int) $pairs['notification.push.offline_seconds']));
            }
        }

        $old = collect($settings->all())->only(array_keys($pairs))->all();

        $count = $settings->updateMany($pairs);

        // v26 — سرویس پیش‌فرض: کلیدهای VAPID خودکار ساخته می‌شوند
        $webpushGenerated = false;

        if (($pairs['notification.push.provider'] ?? null) === 'default'
            && ! app(PushManager::class)->webpush()->hasKeys()) {
            $webpushGenerated = app(PushManager::class)->ensureWebpushKeys();
        }

        AuditLogger::log('settings.updated', null, $old, $pairs,
            "بروزرسانی تنظیمات گروه «{$data['group']}» ({$count} مورد)"
            .($webpushGenerated ? ' + کلیدهای وب‌پوش ساخته شد' : ''));

        $extra = [];

        if ($webpushGenerated) {
            $extra['webpush_public'] = app(PushManager::class)->webpush()->publicKey();
        }

        return response()->json(array_merge([
            'message' => "تنظیمات با موفقیت ذخیره شد ({$count} مورد)"
                .($webpushGenerated ? '؛ کلیدهای وب‌پوش داخلی ساخته شد.' : '.'),
        ], $extra));
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

    /* ================================================================== */
    /* اعلان‌ها (v25) — صدا + پوش فایربیس                                   */
    /* ================================================================== */

    /** فرمت‌های مجاز صدای اعلان */
    private const SOUND_MIMES = 'mp3,wav,ogg,m4a';

    private const SOUND_MAX_KB = 2048;

    /**
     * آپلود صدای سفارشی اعلان پنل‌ها (AJAX).
     * ذخیره روی دیسک public در sounds/ و سرو از /media/sounds/….
     */
    public function uploadSound(Request $request, SettingsService $settings): JsonResponse
    {
        $request->validate([
            'sound' => ['required', 'file', 'mimes:'.self::SOUND_MIMES, 'max:'.self::SOUND_MAX_KB],
        ], [
            'sound.required' => 'فایل صدا انتخاب نشده است.',
            'sound.mimes' => 'فرمت صدا مجاز نیست (mp3 / wav / ogg / m4a).',
            'sound.max' => 'حجم صدا حداکثر '.fa_number(self::SOUND_MAX_KB / 1024).' مگابایت.',
        ]);

        $file = $request->file('sound');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'mp3');
        $name = 'custom-'.now()->format('Ymd_His').'-'.bin2hex(random_bytes(4)).'.'.$ext;
        $path = 'sounds/'.$name;

        $disk = Storage::disk('public');
        $disk->put($path, $file->getContent());

        // حذف صدای سفارشی قبلی
        $old = trim((string) $settings->get('notification.sound.file', ''));
        if ($old !== '' && $old !== $name && $disk->exists('sounds/'.$old)) {
            $disk->delete('sounds/'.$old);
        }

        $settings->set('notification.sound.file', $name);
        // صدای سفارشی انتخاب شده → تیک «صدای پیش‌فرض» خودکار برداشته می‌شود
        $settings->set('notification.sound.use_default', '0');

        AuditLogger::log('settings.updated', null,
            ['notification.sound.file' => $old],
            ['notification.sound.file' => $name],
            'آپلود صدای سفارشی اعلان‌ها («'.basename($name).'»)');

        return response()->json([
            'message' => 'صدای سفارشی اعلان‌ها ذخیره شد.',
            'name' => $name,
            'url' => media_url($path),
        ]);
    }

    /** حذف صدای سفارشی → بازگشت به صدای پیش‌فرض */
    public function deleteSound(Request $request, SettingsService $settings): JsonResponse
    {
        $old = trim((string) $settings->get('notification.sound.file', ''));

        if ($old !== '') {
            $disk = Storage::disk('public');
            if ($disk->exists('sounds/'.$old)) {
                $disk->delete('sounds/'.$old);
            }
        }

        $settings->set('notification.sound.file', '');
        $settings->set('notification.sound.use_default', '1');

        AuditLogger::log('settings.updated', null,
            ['notification.sound.file' => $old, 'notification.sound.use_default' => '0'],
            ['notification.sound.file' => '', 'notification.sound.use_default' => '1'],
            'حذف صدای سفارشی اعلان‌ها — بازگشت به پیش‌فرض');

        return response()->json([
            'message' => 'صدای سفارشی حذف شد؛ صدای پیش‌فرض سامانه فعال است.',
            'url' => asset('assets/sounds/notify.mp3'),
        ]);
    }

    /**
     * آپلود فایل Service Account فایربیس (JSON) برای ارسال پوش (AJAX).
     * ذخیره روی دیسک private (خارج از دسترس وب) با نام ثابت.
     */
    public function uploadFirebaseCredentials(Request $request, SettingsService $settings): JsonResponse
    {
        $request->validate([
            'credentials' => ['required', 'file', 'mimes:json', 'max:256'],
        ], [
            'credentials.required' => 'فایل JSON اعتبارنامه انتخاب نشده است.',
            'credentials.mimes' => 'فایل باید Service Account JSON معتبر فایربیس باشد.',
            'credentials.max' => 'حجم فایل حداکثر ۲۵۶ کیلوبایت.',
        ]);

        $json = json_decode((string) $request->file('credentials')->getContent(), true);

        if (! is_array($json)
            || empty($json['project_id'])
            || empty($json['client_email'])
            || empty($json['private_key'])) {
            return response()->json([
                'message' => 'ساختار فایل معتبر نیست؛ باید شامل project_id و client_email و private_key باشد (Service Account فایربیس).',
            ], 422);
        }

        $path = 'push/firebase-credentials.json';

        Storage::disk('local')->put($path, json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $settings->set('notification.push.firebase.credentials', $path);

        // اگر project_id تنظیمات خالی است، از فایل پر می‌شود
        if (trim((string) $settings->get('notification.push.firebase.project_id', '')) === '') {
            $settings->set('notification.push.firebase.project_id', (string) $json['project_id']);
        }

        AuditLogger::log('settings.updated', null, null,
            ['notification.push.firebase.credentials' => $path],
            'بارگذاری Service Account فایربیس («'.$json['client_email'].'»)');

        return response()->json([
            'message' => 'اعتبارنامهٔ فایربیس ذخیره شد ('.$json['client_email'].').',
            'project_id' => (string) $json['project_id'],
        ]);
    }

    /** حذف اعتبارنامهٔ فایربیس */
    public function deleteFirebaseCredentials(Request $request, SettingsService $settings): JsonResponse
    {
        $path = trim((string) $settings->get('notification.push.firebase.credentials', ''));

        if ($path !== '' && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        $settings->set('notification.push.firebase.credentials', '');

        AuditLogger::log('settings.updated', null,
            ['notification.push.firebase.credentials' => $path],
            ['notification.push.firebase.credentials' => ''],
            'حذف اعتبارنامهٔ فایربیس');

        return response()->json([
            'message' => 'اعتبارنامهٔ فایربیس حذف شد؛ ارسال پوش تا بارگذاری مجدد انجام نمی‌شود.',
        ]);
    }

    /**
     * بازتولید کلیدهای VAPID سرویس پیش‌فرض (AJAX — v26).
     * هشدار: دستگاه‌های ثبت‌شده باید دوباره فعال شوند.
     */
    public function regenerateWebpushKeys(Request $request, SettingsService $settings): JsonResponse
    {
        $keys = WebPushCrypto::generateVapidKeys();

        $old = trim((string) $settings->get('notification.push.webpush.public_key', ''));

        $settings->set('notification.push.webpush.public_key', $keys['public']);
        $settings->set('notification.push.webpush.private_key', $keys['private']);

        // اشتراک‌های وب‌پوش قبلی با کلید قدیمی بی‌اعتبار می‌شوند
        \App\Models\PushToken::query()->where('provider', 'webpush')->delete();

        AuditLogger::log('settings.updated', null,
            ['notification.push.webpush.public_key' => $old],
            ['notification.push.webpush.public_key' => $keys['public']],
            'بازتولید کلیدهای VAPID وب‌پوش داخلی');

        return response()->json([
            'message' => 'کلیدهای جدید ساخته شد؛ کاربرانی که قبلاً فعال کرده بودند باید دوباره «فعال‌سازی نوتیف دستگاه» را بزنند.',
            'public_key' => $keys['public'],
        ]);
    }

    /** تست پوش دستگاه — پیام آزمایشی به دستگاه‌های مدیر جاری (v26: هر سه سرویس) */
    public function testPush(Request $request, PushManager $push): JsonResponse
    {
        $result = $push->sendTest($request->user());

        AuditLogger::log('settings.push_test', null, null,
            ['ok' => $result['ok'], 'provider' => $push->provider()],
            'تست نوتیف دستگاه ('.$push->providerLabel().') از پنل تنظیمات — '.($result['ok'] ? 'موفق' : 'ناموفق'));

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }
}
