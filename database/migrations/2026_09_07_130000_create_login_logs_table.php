<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * لاگ ورود/خروج کاربران — درخواست بازخوردی:
     * تاریخچهٔ ورود و خروج هر کاربر (IP + مرورگر + زمان) برای نمایش
     * در پروفایل مشتری پنل مدیریت کل.
     */
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('guard', 20)->default('customer'); // customer | admin | coffeenet | org | operator
            $table->string('event', 20); // login | logout | forced_logout
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
