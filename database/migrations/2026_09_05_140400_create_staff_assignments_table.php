<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // عضویت کارکنان در کافی‌نت (manager | operator) + دسترسی‌های شخصی‌سازی‌شده
        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coffeenet_id')->constrained('coffeenets')->cascadeOnDelete();
            $table->string('position', 20)->default('operator'); // manager | operator
            // permission های اختصاصی این کارمند در این کافی‌نت (توسط مدیر قابل تنظیم)
            $table->json('permissions')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'coffeenet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_assignments');
    }
};
