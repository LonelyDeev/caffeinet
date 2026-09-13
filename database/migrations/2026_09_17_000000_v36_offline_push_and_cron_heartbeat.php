<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * v36 — ریشه‌یابی «پوش سیستمی نمی‌آید»:
 *
 * مقدار پیش‌فرض آستانهٔ آفلاین ۱۸۰ ثانیه بود (v35 کش ۹۰ ثانیه هم داشت)
 * یعنی مشتری تا ۳ دقیقه بعد از بستن برنامه هم «آنلاین» تلقی می‌شد و
 * پوش هیچ‌وقت نمی‌رفت — تصمیم پوش فقط در لحظهٔ ساخت اعلان گرفته
 * می‌شد و بعداً دوباره چک نمی‌شد.
 *
 * این مهاجرت:
 *  • مقدارهای ۹۰ و ۱۸۰ (پیش‌فرض/کفِ قدیمی) را به ۴۵ می‌رساند
 *    (فقط همین دو مقدار — مقدار دلخواهِ مدیر دست‌نخورده می‌ماند)
 *  • برچسب‌ها را با معنای جدید هم‌گام می‌کند
 *  • کلید ضربان کرون (system.cron.last) را می‌سازد — نشانگر قرمز/سبز
 *    تنظیمات عمومی؛ هر اجرای schedule:run آن را تازه می‌کند
 */
return new class extends Migration
{
    public function up(): void
    {
        $labels = [
            'notification.push.offline_enabled' => 'آستانهٔ آفلاین فعال باشد؟ (خاموش = حالت کوتاه ۴۵ ثانیه)',
            'notification.push.offline_seconds' => 'پس از چند ثانیه بی‌فعالیتی، کاربر «آفلاین» فرض شود (حداقل ۴۵)',
        ];

        foreach ($labels as $key => $label) {
            $row = Setting::query()->where('key', $key)->first();

            if (! $row) {
                Setting::query()->create([
                    'group' => 'notifications',
                    'key' => $key,
                    'value' => $key === 'notification.push.offline_enabled' ? '1' : '45',
                    'cast' => $key === 'notification.push.offline_enabled' ? 'boolean' : 'integer',
                    'label' => $label,
                ]);
                continue;
            }

            $row->forceFill(['label' => $label]);

            // فقط مقدارهای «پیش‌فرض/کفِ قدیمی» → ۴۵
            if ($key === 'notification.push.offline_seconds'
                && in_array((int) $row->value, [90, 180], true)) {
                $row->forceFill(['value' => '45']);
            }

            $row->save();
        }

        // v36 — کلید ضربان کرون (idempotent)
        Setting::query()->firstOrCreate(
            ['key' => 'system.cron.last'],
            [
                'group' => 'system',
                'value' => null,
                'cast' => 'string',
                'label' => 'آخرین ضربان کرون (schedule:run)',
            ],
        );

        // v36 — نقش «مدیر دستیار» روی نصب‌های موجود هم ساخته شود (idempotent)
        // (مجوزها دست‌نخورده — از پنل تنظیم می‌شوند)
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // کشِ تنظیمات تازه شود تا مقدار ۴۵ بلافاصله اعمال شود
        // (بدون این، تا ۱۰ دقیقه مقدار قدیمی ۱۸۰ سرو می‌شود)
        try {
            \Illuminate\Support\Facades\Cache::forget('settings.all');
        } catch (\Throwable) {
            // کش هرگز مهاجرت را متوقف نمی‌کند
        }
    }

    public function down(): void
    {
        // برچسب‌ها به حالت v35 برمی‌گردند — مقادیر دست‌نخورده
        Setting::query()->where('key', 'notification.push.offline_enabled')
            ->update(['label' => 'آستانهٔ آفلاین فعال باشد؟ (خاموش = حالت کوتاه ۹۰ ثانیه)']);
        Setting::query()->where('key', 'notification.push.offline_seconds')
            ->update(['label' => 'پس از چند ثانیه بی‌فعالیتی، کاربر «آفلاین» فرض شود (حداقل ۹۰)']);
    }
};
