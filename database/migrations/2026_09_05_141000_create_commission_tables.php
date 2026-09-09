<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قواعد کمیسیون: سراسری یا override هر خدمت — سهم پلتفرم/سازمان/کافی‌نت
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 15)->default('global'); // global | service
            $table->foreignId('service_id')->nullable()->constrained('services')->cascadeOnDelete();
            $table->string('platform_type', 10)->default('percent'); // percent | fixed
            $table->decimal('platform_value', 15, 2)->default(0);
            $table->string('organization_type', 10)->nullable(); // percent | fixed
            $table->decimal('organization_value', 15, 2)->nullable();
            $table->string('coffeenet_type', 10)->nullable();
            $table->decimal('coffeenet_value', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['scope', 'service_id']);
        });

        // تسویه هر سفارش موفق (فقط مبالغ is_commission)
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('role', 20); // platform | organization | coffeenet | operator
            $table->string('holder_type');
            $table->unsignedBigInteger('holder_id');
            $table->decimal('amount', 15, 2);
            $table->json('snapshot')->nullable(); // snapshot قواعد در لحظه تسویه
            $table->timestamp('created_at')->nullable();
            $table->index(['order_id', 'role']);
        });

        // تنظیمات پاداش معرفی کافی‌نت توسط سازمان (توسط مدیریت کل)
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('introduction_reward', 15, 2)->default(0); // پاداش اولیه (مثلاً صفر)
            $table->string('per_order_type', 10)->nullable(); // percent | fixed
            $table->decimal('per_order_value', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // درخواست برداشت از کیف پول (سازمان / کافی‌نت آینده)
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('status', 15)->default('pending'); // pending|approved|rejected|paid
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('referral_settings');
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('commission_settings');
    }
};
