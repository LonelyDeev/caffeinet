<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * v40 — تنظیمات سرویس استعلام فینوتک (finnotech.ir).
 *
 *   • finnotech.enabled        کلید اصلی: فعال/غیرفعال بودن سرویس
 *   • finnotech.mode           محیط: production (api.finnotech.ir) | sandbox
 *   • finnotech.client_id      شناسه برنامه (console.finnotech.ir)
 *   • finnotech.client_secret  رمز برنامه
 *   • finnotech.nid            کد ملی صاحب برنامه (برای گرفتن توکن)
 *   • finnotech.verify_profile تیک: تطبیق کد ملی با موبایل در پروفایل مشتری
 *   • finnotech.verify_cards   تیک: تطبیق کارت و کد ملی در کارت‌های بانکی
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['finnotech.enabled', 'finnotech', '0', 'boolean', 'فعال‌سازی سرویس استعلام فینوتک'],
            ['finnotech.mode', 'finnotech', 'production', 'string', 'محیط سرویس فینوتک (production/sandbox)'],
            ['finnotech.client_id', 'finnotech', '', 'string', 'شناسه برنامهٔ فینوتک (clientId)'],
            ['finnotech.client_secret', 'finnotech', '', 'string', 'رمز برنامهٔ فینوتک (clientSecret)'],
            ['finnotech.nid', 'finnotech', '', 'string', 'کد ملی صاحب برنامهٔ فینوتک'],
            ['finnotech.verify_profile', 'finnotech', '1', 'boolean', 'بررسی تطبیق کد ملی با موبایل در پروفایل مشتری'],
            ['finnotech.verify_cards', 'finnotech', '1', 'boolean', 'بررسی تطبیق کارت بانکی با کد ملی'],
        ];

        foreach ($rows as [$key, $group, $value, $cast, $label]) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                compact('group', 'key', 'value', 'cast', 'label')
            );
        }
    }

    public function down(): void
    {
        Setting::query()->where('group', 'finnotech')->delete();
    }
};
