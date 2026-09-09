<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('service_version_id')->nullable()
                ->constrained('service_versions')->nullOnDelete();
            // وضعیت‌ها: pending_payment → paid → broadcasting → accepted →
            // in_progress → (needs_info) → delivered → completed
            // انشعابات: queued (صف تعیین‌تکلیف دستی بعد از ۶۰s)، cancelled، refunded
            $table->string('status', 25)->default('pending_payment');
            $table->foreignId('coffeenet_id')->nullable()->constrained('coffeenets')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('form_data')->nullable(); // پاسخ‌های فرم داینامیک
            // snapshot مبالغ (فریز در لحظه ثبت)
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('expenses', 15, 2)->default(0);
            $table->decimal('commissionable_amount', 15, 2)->default(0); // مشمول کمیسیون
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['coffeenet_id', 'status']);
            $table->index(['operator_id', 'status']);
        });

        // ردیابی پخش سفارش بین کافی‌نت‌ها (۶۰ ثانیه)
        Schema::create('order_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('coffeenet_id')->constrained('coffeenets')->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'coffeenet_id']);
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 25)->nullable();
            $table->string('to_status', 25);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // مدارک مشتری + خروجی اپراتور
        Schema::create('order_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_type', 20)->default('document'); // document | result | message
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_files');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_broadcasts');
        Schema::dropIfExists('orders');
    }
};
