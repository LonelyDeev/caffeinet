<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v33 — نظرسنجی کامل:
 *  ۱) جدول گزینه‌های دلایل نظرسنجی (مدیریت از پنل مدیریت کل)
 *  ۲) گزینه‌های پیش‌فرض مثبت/منفی (seed در خود مهاجرت تا دپلوی بدون seed کار کند)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_options', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100); // متن گزینه (مثل «برخورد مناسب»)
            $table->string('type', 6); // pos = نقطه قوت | neg = نقطه ضعف
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // گزینه‌های پیش‌فرض — idempotent (اولین بار در هر محیطی ساخته می‌شوند)
        $now = now();
        $defaults = [
            // نقاط قوت (برای امتیازهای بالا)
            ['برخورد مناسب', 'pos', 1],
            ['انجام سریع کار', 'pos', 2],
            ['کیفیت عالی کار', 'pos', 3],
            ['قیمت مناسب', 'pos', 4],
            ['نظافت و محیط مناسب', 'pos', 5],
            ['دقت در جزئیات سفارش', 'pos', 6],
            ['پاسخگویی سریع در گفتگو', 'pos', 7],
            ['تحویل به موقع', 'pos', 8],
            // نقاط ضعف (برای امتیازهای پایین)
            ['برخورد نامناسب', 'neg', 1],
            ['تأخیر در انجام کار', 'neg', 2],
            ['کیفیت پایین', 'neg', 3],
            ['قیمت نامناسب', 'neg', 4],
            ['بی‌دقتی در سفارش', 'neg', 5],
            ['پاسخگویی کند', 'neg', 6],
            ['تحویل دیرتر از موعد', 'neg', 7],
        ];

        foreach ($defaults as [$title, $type, $sort]) {
            DB::table('rating_options')->insertOrIgnore([
                'title' => $title,
                'type' => $type,
                'is_active' => true,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_options');
    }
};
