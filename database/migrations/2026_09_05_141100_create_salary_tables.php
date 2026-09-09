<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مدل حقوق هر کارمند در هر کافی‌نت — توسط مدیر کافی‌نت قابل تنظیم
        Schema::create('salary_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coffeenet_id')->constrained('coffeenets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // percent: درصد از مبلغ مشتری موفق | fixed_per_order: مبلغ ثابت هر مشتری
            // monthly: ماهیانه (فقط لاگ گزارشی برای کافی‌نت)
            $table->string('type', 20)->default('percent');
            $table->decimal('rate', 15, 2)->default(0); // درصد یا مبلغ
            $table->decimal('overtime_rate', 15, 2)->nullable(); // نرخ اضافه‌کار (ماهیانه)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['coffeenet_id', 'user_id']);
        });

        // لاگ واریز حقوق/اضافه‌کار توسط کافی‌نت (گزارش‌گیری)
        Schema::create('salary_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coffeenet_id')->constrained('coffeenets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('period', 10); // Y-m
            $table->string('type', 20)->default('monthly'); // monthly | overtime | bonus | manual
            $table->decimal('amount', 15, 2);
            $table->string('description', 500)->nullable();
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['coffeenet_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_logs');
        Schema::dropIfExists('salary_settings');
    }
};
