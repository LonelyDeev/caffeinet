<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v25 — توکن‌های Web Push (FCM) هر کاربر:
     * هر دستگاه/مرورگری که «نوتیف دستگاه» را فعال کند، توکن FCM آن
     * این‌جا ثبت می‌شود؛ سرور با FCM HTTP v1 به این توکن‌ها پیام
     * می‌فرستد (برای وقتی که برنامه بسته است یا کاربر آنلاین نیست).
     */
    public function up(): void
    {
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 512);
            $table->string('platform', 20)->default('web'); // web | android | ios | windows | other
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('token');
            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
    }
};
