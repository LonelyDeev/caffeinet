<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coffeenets', function (Blueprint $table) {
            $table->id();
            // nullable = کافی‌نت مستقل (بدون سازمان)
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->string('name');
            $table->string('phone', 15)->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|suspended
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('introduction_reward_paid')->default(false); // پاداش معرفی
            $table->json('settings')->nullable(); // تنظیمات دلخواه کافی‌نت
            $table->timestamps();
            $table->index(['status', 'city_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coffeenets');
    }
};
