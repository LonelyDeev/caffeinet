<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * v29 — آستانهٔ «آفلاین» انتخابی (ثانیه‌ای + لحظه‌ای).
 *
 *  • notification.push.offline_enabled → سوییچ: فعال = آستانهٔ زمانی، غیرفعال = لحظه‌ای
 *  • notification.push.offline_seconds  → آستانه بر حسب ثانیه (پیش‌فرض ۱۸۰ = ۳ دقیقه)
 *
 * مقدار قدیمی notification.push.offline_minutes (دقیقه) به ثانیه مهاجرت داده می‌شود؛
 * کلید قدیمی حذف نمی‌شود تا عقب‌گرد نسخه‌ها سالم بماند.
 */
return new class extends Migration
{
    public function up(): void
    {
        // مهاجرت مقدار قدیمی: دقیقه → ثانیه
        $legacy = Setting::query()->where('key', 'notification.push.offline_minutes')->first();
        $seconds = $legacy ? max(1, (int) $legacy->value) * 60 : 180;

        $rows = [
            ['group' => 'notifications', 'key' => 'notification.push.offline_enabled', 'value' => '1', 'cast' => 'boolean', 'label' => 'آستانهٔ آفلاین فعال باشد؟ (خاموش = آفلاین لحظه‌ای)'],
            ['group' => 'notifications', 'key' => 'notification.push.offline_seconds', 'value' => (string) $seconds, 'cast' => 'integer', 'label' => 'پس از چند ثانیه بی‌فعالیتی، کاربر «آفلاین» فرض شود'],
        ];

        foreach ($rows as $row) {
            Setting::query()->firstOrCreate(['key' => $row['key']], $row);
        }
    }

    public function down(): void
    {
        Setting::query()->whereIn('key', [
            'notification.push.offline_enabled',
            'notification.push.offline_seconds',
        ])->delete();
    }
};
