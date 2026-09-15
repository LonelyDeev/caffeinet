<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v39 — کارت‌های بانکی اپراتورها، مدیران کافی‌نت و سازمان‌ها.
 *
 * هر کاربر پنلی می‌تواند چند کارت ثبت کند (شماره کارت ۱۶ رقمی، شماره شبا IR+۲۴ رقم
 * و شماره حساب) و یکی را «پیش‌فرض تسویه» کند. مالکیت روی user است تا با تغییر
 * مدیرِ کافی‌نت/سازمان، کارت‌های شخص جدید با خودش بمانند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('card_number', 19)->nullable();      // ۱۶ رقم (بدون خط تیره)
            $table->string('sheba_number', 26)->nullable();     // IR + ۲۴ رقم
            $table->string('account_number', 40)->nullable();   // شماره حساب (الphanumerical)
            $table->string('holder_name', 120)->nullable();     // نام صاحب حساب (اختیاری)
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_cards');
    }
};
