<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قالب‌های پیامک سیستم (فاز ۱۰) — متن هر رویداد از همین‌جا قابل ویرایش است
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique(); // otp_login | order.accepted | ...
            $table->string('title', 150); // نام نمایشی در پنل
            $table->text('body'); // متن با متغیرهای {name}
            $table->text('variables')->nullable(); // راهنمای متغیرها (متنی)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
