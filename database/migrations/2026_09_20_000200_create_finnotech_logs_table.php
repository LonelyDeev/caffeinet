<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v40 — لاگ استعلام‌های فینوتک (شاهکار / تطبیق کارت).
 *
 * هر استعلام موفق یا ناموفق ثبت می‌شود تا مدیر بتواند سوابق احراز هویت را
 * پیگیری کند (چه کسی، چه زمانی، نتیجه چی، کد پاسخ فینوتک چه بود).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finnotech_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);                    // shahkar | card_owner | mobile_card | token
            $table->string('mobile', 15)->nullable();      // موبایل استعلام‌شده (ماسک برای نمایش)
            $table->string('national_id', 10)->nullable(); // کد ملی استعلام‌شده
            $table->string('card_number', 19)->nullable(); // شماره کارت استعلام‌شده
            $table->boolean('matched')->default(false);    // نتیجهٔ تطبیق
            $table->boolean('succeeded')->default(false);  // آیا استعلام اصلاً موفق انجام شد؟
            $table->string('response_code', 40)->nullable();
            $table->string('track_id', 60)->nullable();
            $table->string('error_message', 190)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finnotech_logs');
    }
};
