<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // درخت دسته‌بندی خدمات (خودرو، قضایی، مالیاتی...)
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->constrained('service_categories')->nullOnDelete();
            $table->string('name');
            $table->string('icon', 60)->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('base_price', 15, 2)->default(0); // مبلغ دریافتی از مشتری
            $table->unsignedSmallInteger('estimated_time')->default(0); // دقیقه
            $table->boolean('requires_upload')->default(false); // نیاز به مدرک؟
            $table->boolean('requires_verification')->default(false); // احراز هویت مشتری لازم؟
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['category_id', 'is_active']);
        });

        // ردیف‌های هزینه/کارمزد خدمت — فلگ is_commission = جزو محاسبه کمیسیون
        Schema::create('service_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('type', 15)->default('expense'); // expense (هزینه مصرفی) | fee (کارمزد)
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_commission')->default(false);
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        // فیلدهای فرم داینامیک هر خدمت (فرم‌ساز)
        Schema::create('service_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('field_type', 25)->default('text');
            // text|number|mobile|national_code|email|date|select|textarea|file|checkbox|radio
            $table->string('label');
            $table->string('name'); // slug فیلد
            $table->string('placeholder', 200)->nullable();
            $table->string('help_text', 300)->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable(); // برای select/radio/checkbox
            $table->json('validation')->nullable(); // قواعد سمت سرور
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['service_id', 'name']);
        });

        // snapshot نسخه خدمت هنگام تغییر (قیمت/فرم/هزینه‌ها فریز می‌شوند)
        Schema::create('service_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_versions');
        Schema::dropIfExists('service_form_fields');
        Schema::dropIfExists('service_costs');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
