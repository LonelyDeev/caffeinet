<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // گفتگوی هر سفارش (مشتری ↔ اپراتور) — شبیه چت تلگرام
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message_type', 15)->default('text');
            // text | image | audio | video | file | system
            $table->text('content')->nullable();
            $table->string('file_path')->nullable();
            $table->json('file_meta')->nullable(); // duration, width, thumbnail...
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
