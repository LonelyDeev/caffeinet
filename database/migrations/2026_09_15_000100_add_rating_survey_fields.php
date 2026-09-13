<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v33 — نظرسنجی کامل + پخش هوشمند بر اساس امتیاز:
 *  ۱) order_ratings: امتیاز اپراتور + اسنپ‌شات گزینه‌های انتخابی مشتری
 *  ۲) تنظیمات گروه ratings: پخش بر اساس امتیاز + اعلان امتیاز پایین + فعال‌بودن نظرسنجی
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_ratings', function (Blueprint $table) {
            $table->unsignedTinyInteger('operator_rating')->nullable()->after('rating'); // 1..5
            // اسنپ‌شات گزینه‌ها [{id,title,type}] — مستقل از حذف/ویرایش گزینه‌ها
            $table->json('options')->nullable()->after('comment');
        });

        $rows = [
            ['group' => 'ratings', 'key' => 'ratings.survey_enabled', 'value' => '1', 'cast' => 'boolean', 'label' => 'نظرسنجی پس از تحویل فعال باشد'],
            // پخش هوشمند سفارش بر اساس امتیاز کافی‌نت‌ها
            ['group' => 'ratings', 'key' => 'ratings.routing_enabled', 'value' => '0', 'cast' => 'boolean', 'label' => 'پخش سفارش‌ها بر اساس امتیاز کافی‌نت‌ها'],
            ['group' => 'ratings', 'key' => 'ratings.routing_mode', 'value' => 'hybrid', 'cast' => 'string', 'label' => 'سیاست پخش هوشمند (filter | priority | hybrid)'],
            ['group' => 'ratings', 'key' => 'ratings.routing_min_rating', 'value' => '3', 'cast' => 'integer', 'label' => 'حداقل میانگین امتیاز کافی‌نت برای دریافت سفارش (۱..۵)'],
            ['group' => 'ratings', 'key' => 'ratings.routing_min_votes', 'value' => '3', 'cast' => 'integer', 'label' => 'حداقل تعداد نظرات برای اعتبار امتیاز کافی‌نت'],
            ['group' => 'ratings', 'key' => 'ratings.routing_unrated_policy', 'value' => 'include', 'cast' => 'string', 'label' => 'کافی‌نت بدون امتیاز معتبر (include | exclude)'],
            // اعلان امتیاز پایین به مدیر کل و مدیر کافی‌نت
            ['group' => 'ratings', 'key' => 'ratings.notify_low', 'value' => '1', 'cast' => 'boolean', 'label' => 'اعلان امتیاز پایین به مدیران'],
            ['group' => 'ratings', 'key' => 'ratings.notify_low_threshold', 'value' => '2', 'cast' => 'integer', 'label' => 'آستانهٔ اعلان امتیاز پایین (۱..۴)'],
        ];

        foreach ($rows as $row) {
            Setting::firstOrCreate(
                ['key' => $row['key']],
                [
                    'group' => $row['group'],
                    'value' => $row['value'],
                    'cast' => $row['cast'],
                    'label' => $row['label'],
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::table('order_ratings', function (Blueprint $table) {
            $table->dropColumn(['operator_rating', 'options']);
        });

        \App\Models\Setting::query()->where('key', 'like', 'ratings.%')->delete();
    }
};
