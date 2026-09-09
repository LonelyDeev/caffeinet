<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // کیف پول چند-اشتراکی: user | organization | coffeenet
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('holder_type');
            $table->unsignedBigInteger('holder_id');
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['holder_type', 'holder_id']);
        });

        // تراکنش‌های دفتری — تغییرناپذیر (immutable)؛ مانع باگ‌های حسابداری
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('type', 10); // credit | debit
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('ref_type', 40)->nullable(); // order | payment | withdrawal | reward | salary
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
    }
};
