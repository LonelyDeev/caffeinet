<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * فاز ۱۵ — کلیدهای تنظیمات «ساعت کاری» (برای دیتابیس‌های موجود).
 * در نصب تازه، SettingsSeeder هم همین ردیف‌ها را می‌سازد.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['group' => 'workhours', 'key' => 'workhours.enabled', 'value' => '0', 'cast' => 'boolean', 'label' => 'محدودیت ثبت سفارش به ساعت کاری'],
            ['group' => 'workhours', 'key' => 'workhours.start', 'value' => '08:00', 'cast' => 'string', 'label' => 'شروع ساعت کاری (HH:MM)'],
            ['group' => 'workhours', 'key' => 'workhours.end', 'value' => '22:00', 'cast' => 'string', 'label' => 'پایان ساعت کاری (HH:MM)'],
            // روزها با شماره‌ی Carbon dayOfWeek: 0=یکشنبه … 5=جمعه، 6=شنبه
            ['group' => 'workhours', 'key' => 'workhours.days', 'value' => '6,0,1,2,3,4', 'cast' => 'string', 'label' => 'روزهای کاری (CSV — 6=شنبه، 0=یکشنبه، …، 5=جمعه)'],
            ['group' => 'workhours', 'key' => 'workhours.message', 'value' => '', 'cast' => 'string', 'label' => 'پیام سفارشی مودال خارج از ساعت کاری'],
        ];

        foreach ($rows as $row) {
            Setting::query()->firstOrCreate(['key' => $row['key']], $row);
        }
    }

    public function down(): void
    {
        Setting::query()->whereIn('key', [
            'workhours.enabled', 'workhours.start', 'workhours.end',
            'workhours.days', 'workhours.message',
        ])->delete();
    }
};
