<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فاز ۱۵ — سیستم اطلاعیه‌ها (Announcements):
 *   ارسال اطلاعیه متن/تصویر/ویدیو به پنل‌ها یا مشتریان + جدول خوانده‌شده‌ها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body')->nullable(); // متن اطلاعیه
            $table->string('media_type', 20)->default('none'); // none | image | video
            $table->string('media_path')->nullable(); // فایل تصویر/ویدیوی آپلودشده (public disk)
            $table->string('video_url', 500)->nullable(); // لینک مستقیم ویدیو (mp4)
            $table->string('audience', 30)->default('customers');
            // customers | admins | org_managers | coffeenet_managers | operators | all_panels | everyone
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable(); // پنجره نمایش (اختیاری)
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'audience']);
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }
};
