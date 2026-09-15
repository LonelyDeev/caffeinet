<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * v39 — بازهٔ سنین مجاز تاریخ تولد مشتریان (تنظیمات عمومی).
 *
 * صفحهٔ پروفایل مشتری از این‌ها برای ساخت لیست کشویی «سال تولد» استفاده می‌کند
 * (سال‌ها = سال جاری شمسی منهای بیشینه سن … سال جاری منهای کمینه سن).
 * پیش‌فرض ۱۰..۱۰۰ = همان رفتار قبلی اعتبارسنجی ProfileController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->firstOrCreate(
            ['key' => 'general.birth_min_age'],
            ['group' => 'general', 'key' => 'general.birth_min_age', 'value' => '10', 'cast' => 'integer', 'label' => 'حداقل سن مشتریان (سال)'],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'general.birth_max_age'],
            ['group' => 'general', 'key' => 'general.birth_max_age', 'value' => '100', 'cast' => 'integer', 'label' => 'حداکثر سن مشتریان (سال)'],
        );
    }

    public function down(): void
    {
        Setting::query()->whereIn('key', ['general.birth_min_age', 'general.birth_max_age'])->delete();
    }
};
