<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فاز ۱۵ — قابلیت‌های جدید کاتالوگ خدمات:
 *   تصویر خدمت + وضعیت برخط (فعال/قطع از سایت اصلی) + مهلت انقضا + آلرت اطلاع‌رسانی.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description'); // تصویر خدمت (کاتالوگ مشتری)
            $table->string('availability', 20)->default('active')->after('image_path'); // active | unavailable
            $table->string('unavailable_note', 500)->nullable()->after('availability'); // پیام «قطع از سایت اصلی»
            $table->timestamp('expires_at')->nullable()->after('unavailable_note'); // مهلت خدمت (مثلاً مهلت ثبت‌نام)
            $table->string('expired_note', 500)->nullable()->after('expires_at'); // پیام پایان مهلت
            $table->string('alert_type', 20)->nullable()->after('expired_note'); // null | text | image
            $table->text('alert_text')->nullable()->after('alert_type'); // متن آلرت
            $table->string('alert_image_path')->nullable()->after('alert_text'); // تصویر آلرت
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'image_path', 'availability', 'unavailable_note',
                'expires_at', 'expired_note',
                'alert_type', 'alert_text', 'alert_image_path',
            ]);
        });
    }
};
