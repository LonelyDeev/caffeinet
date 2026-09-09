<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // نظرسنجی سفارش — امتیاز مشتری پس از تحویل/تکمیل (۱ تا ۵ ستاره)
        Schema::create('order_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->string('comment', 500)->nullable();
            $table->timestamp('rated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_ratings');
    }
};
