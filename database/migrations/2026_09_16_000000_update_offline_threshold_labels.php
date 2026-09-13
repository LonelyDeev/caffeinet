<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * v35 — قانون «برنامه باز → فقط اعلان داخل برنامه».
 *
 * برچسب دو کلید آستانهٔ آفلاین به‌روز می‌شود تا معنای تازه را نشان دهد:
 *  • خاموش = حالت کوتاه (۹۰ ثانیه) — دیگر «آفلاین لحظه‌ای» نیست؛
 *    آن حالت با کف ۶۰ ثانیه‌ای نوشتن last_seen عملاً همه را همیشه
 *    آفلاین می‌کرد و ریشهٔ «نوتیف سیستمی با برنامهٔ باز» بود.
 *  • روشن = آستانه با کف ۹۰ ثانیه (کمتر از آن بی‌معناست).
 *
 * مقدارها تغییر نمی‌کنند (کف در زمان اجرا اعمال می‌شود)؛ این مهاجرت
 * فقط برچسب‌ها + مقادیر کمتر از ۹۰ را به ۹۰ می‌رساند (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        $labels = [
            'notification.push.offline_enabled' => 'آستانهٔ آفلاین فعال باشد؟ (خاموش = حالت کوتاه ۹۰ ثانیه)',
            'notification.push.offline_seconds' => 'پس از چند ثانیه بی‌فعالیتی، کاربر «آفلاین» فرض شود (حداقل ۹۰)',
        ];

        foreach ($labels as $key => $label) {
            $row = Setting::query()->where('key', $key)->first();

            if (! $row) {
                Setting::query()->create([
                    'group' => 'notifications',
                    'key' => $key,
                    'value' => $key === 'notification.push.offline_enabled' ? '1' : '180',
                    'cast' => $key === 'notification.push.offline_enabled' ? 'boolean' : 'integer',
                    'label' => $label,
                ]);
                continue;
            }

            $row->forceFill(['label' => $label])->save();

            // مقادیر زیر کف → کف
            if ($key === 'notification.push.offline_seconds' && (int) $row->value < 90) {
                $row->forceFill(['value' => '90'])->save();
            }
        }
    }

    public function down(): void
    {
        // برچسب‌ها به حالت v29 برمی‌گردند — مقادیر دست‌نخورده
        $labels = [
            'notification.push.offline_enabled' => 'آستانهٔ آفلاین فعال باشد؟ (خاموش = آفلاین لحظه‌ای)',
            'notification.push.offline_seconds' => 'پس از چند ثانیه بی‌فعالیتی، کاربر «آفلاین» فرض شود',
        ];

        foreach ($labels as $key => $label) {
            Setting::query()->where('key', $key)->update(['label' => $label]);
        }
    }
};
