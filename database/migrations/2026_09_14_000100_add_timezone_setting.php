<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * v29 — منطقهٔ زمانی سامانه در تنظیمات عمومی.
 * پیش‌فرض 'UTC' = رفتار فعلی (config/app.php)؛ مدیر می‌تواند از تب «عمومی» تغییر دهد.
 * مقدار در AppServiceProvider::boot اعمال می‌شود (config + date_default_timezone_set).
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->firstOrCreate(
            ['key' => 'general.timezone'],
            ['group' => 'general', 'key' => 'general.timezone', 'value' => 'UTC', 'cast' => 'string', 'label' => 'منطقهٔ زمانی سامانه (IANA مثل Asia/Tehran)'],
        );
    }

    public function down(): void
    {
        Setting::query()->where('key', 'general.timezone')->delete();
    }
};
