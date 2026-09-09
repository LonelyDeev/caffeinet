<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v10 — «پیش‌فرض پترن»:
 * قالب آزمایشی «test» برای دکمهٔ تست پیامک در تنظیمات اضافه می‌شود تا
 * ارسال آزمایشی هم از مسیر قالبی/پترنی برود (اگر کد پترن ثبت شود).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sms_templates')->updateOrInsert(
            ['key' => 'test'],
            [
                'title' => 'پیامک آزمایشی (تنظیمات)',
                'category' => 'other',
                'body' => 'پیامک آزمایشی «{app_name}» — تنظیمات پیامک با موفقیت ذخیره شد.',
                'variables' => '{app_name} نام سیستم',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('key', 'test')->delete();
    }
};
