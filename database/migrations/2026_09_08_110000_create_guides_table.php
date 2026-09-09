<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فاز ۱۳ — سیستم آموزش پنل (مبتنی بر نقش):
 * هر نقش (super_admin | organization | coffeenet | operator) فقط راهنماهای خودش را می‌بیند.
 *
 * content (JSON): آرایه‌ای از بخش‌ها:
 *   { h: عنوان بخش, body: HTML, steps?: [متن گام‌ها] }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->string('role', 40)->index();          // super_admin | organization | coffeenet | operator
            $table->string('slug', 80)->index();
            $table->string('title', 150);
            $table->string('description', 300)->nullable();
            $table->string('icon', 40)->default('book');   // کلید آیکن سمت فرانت
            $table->unsignedInteger('sort_order')->default(100);
            $table->json('content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['role', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};
