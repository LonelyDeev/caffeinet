<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * فاز ۱۳ — Realtime پوشر (در کنار پولینگ):
 *   realtime.pusher.enabled  (boolean — پیش‌فرض خاموش)
 *   realtime.pusher.app_id   (حساس)
 *   realtime.pusher.app_key  (عمومی — به کلاینت تزریق می‌شود)
 *   realtime.pusher.app_secret (حساس — فقط سرور)
 *   realtime.pusher.cluster  (mt1 پیش‌فرض)
 *
 * راه‌اندازی: تنظیمات ← Realtime (پوشر)
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['realtime.pusher.enabled', '1', 'boolean', 'فعال‌سازی Realtime پوشر', 0],
            ['realtime.pusher.app_id', '', 'string', 'Pusher App ID', 1],
            ['realtime.pusher.app_key', '', 'string', 'Pusher App Key (عمومی)', 0],
            ['realtime.pusher.app_secret', '', 'string', 'Pusher App Secret', 1],
            ['realtime.pusher.cluster', 'mt1', 'string', 'Pusher Cluster (mt1 | eu | ap2 | us2 …)', 0],
        ];

        foreach ($rows as [$key, $value, $cast, $label, $sensitive]) {
            DB::table('settings')->insertOrIgnore([
                'group' => 'realtime',
                'key' => $key,
                'value' => $value,
                'cast' => $cast,
                'label' => $label,
                'is_sensitive' => $sensitive,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'like', 'realtime.pusher.%')->delete();
    }
};
