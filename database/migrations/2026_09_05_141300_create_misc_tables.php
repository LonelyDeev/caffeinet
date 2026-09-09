<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OTP هش‌شده با انقضا (API مشتری — فاز ۵)
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 15);
            $table->string('code_hash');
            $table->string('purpose', 20)->default('login');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['mobile', 'expires_at']);
        });

        // اعلان‌های in-app
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // لاگ پیامک‌ها (همه پرووایدرها)
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 15);
            $table->string('template_key', 60)->nullable();
            $table->text('message');
            $table->string('provider', 30);
            $table->string('status', 10)->default('sent'); // sent | failed
            $table->json('response')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['mobile', 'created_at']);
        });

        // تنظیمات داینامیک پنل مدیریت (گروه‌بندی‌شده)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 20)->default('general'); // general|sms|payment|orders
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('cast', 15)->default('string'); // string|integer|boolean|json
            $table->string('label', 200)->nullable();
            $table->boolean('is_sensitive')->default(false); // مقدار در UI ماسک شود
            $table->timestamps();
        });

        // لاگ فعالیت (اقدامات حساس همه پنل‌ها)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('auditable_type', 60)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('otp_codes');
    }
};
