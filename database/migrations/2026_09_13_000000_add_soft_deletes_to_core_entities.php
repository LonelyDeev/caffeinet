<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v28 — حذف نرم‌افزاری (سطل زباله) برای موجودیت‌های اصلی:
     * users (مشتری/اپراتور/مدیر)، coffeenets، organizations، orders، tickets.
     *
     * رکورد حذف‌شده در «حذف‌شده‌ها» دیده می‌شود و قابل بازگردانی است؛
     * حذف دائم (purge) رکورد و فایل‌های وابسته را برای همیشه پاک می‌کند.
     */
    public function up(): void
    {
        foreach (['users', 'coffeenets', 'organizations', 'orders', 'tickets'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->timestamp('deleted_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'coffeenets', 'organizations', 'orders', 'tickets'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex(['deleted_at']);
                $t->dropColumn('deleted_at');
            });
        }
    }
};
