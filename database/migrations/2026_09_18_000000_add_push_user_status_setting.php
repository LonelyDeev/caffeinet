<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * v38 — «وضعیت آنلاین/آفلاین کاربران» در تنظیمات اعلان‌ها و پوش.
 *
 * مدیر خودش تصمیم می‌گیرد که وقتی کاربران آفلاین‌اند نوتیف سیستمی
 * (پوش دستگاه) برود یا نه. پیش‌فرض: «آفلاین» — یعنی کاربران آفلاین
 * فرض می‌شوند و نوتیف سیستمی همیشه برای همه ارسال می‌شود (رفتار
 * v37 حفظ می‌شود؛ مطمئن‌ترین حالت).
 *
 *  • offline (پیش‌فرض) — پوش همیشه برای همه
 *  • online            — پوش هیچ‌وقت (فقط زنگ درون‌برنامه‌ای)
 *  • auto              — تشخیص از حضور واقعی هر کاربر (آستانهٔ آفلاین)
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->firstOrCreate(
            ['key' => 'notification.push.user_status'],
            [
                'group' => 'notifications',
                'value' => 'offline',
                'cast' => 'string',
                'label' => 'وضعیت کاربران برای ارسال نوتیف سیستمی (offline | online | auto)',
            ],
        );

        // کش تنظیمات تازه شود تا مقدار جدید بلافاصله سرو شود
        try {
            Cache::forget('settings.all');
        } catch (\Throwable) {
            // کش هرگز مهاجرت را متوقف نمی‌کند
        }
    }

    public function down(): void
    {
        Setting::query()->where('key', 'notification.push.user_status')->delete();

        try {
            Cache::forget('settings.all');
        } catch (\Throwable) {
        }
    }
};
