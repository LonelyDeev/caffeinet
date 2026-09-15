<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v40 — کد ملی مشتری + نشان تأیید فینوتک.
 *
 * users.national_id: کد ملی ۱۰ رقمی (نزد مشتریان اختیاری؛ با فعال بودن
 * استعلام فینوتک + تیک «بررسی پروفایل» الزامی و باید با موبایل تطبیق کند).
 * users.national_id_verified_at: تاریخ موفق استعلام شاهکار — با تغییر کد ملی
 * پاک می‌شود تا مجدداً بررسی شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_id', 10)->nullable()->after('mobile');
            $table->timestamp('national_id_verified_at')->nullable()->after('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['national_id', 'national_id_verified_at']);
        });
    }
};
