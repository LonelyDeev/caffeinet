<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v25 — تیک «یادآوری ارسال‌شده» برای سفارش‌های بی‌پذیرش:
     * دستور orders:notify-unaccepted برای هر سفارش فقط یک‌بار
     * (سفارش پخش/صف‌شده که مدتی است کسی قبول نکرده) به مدیران
     * پوش/پیامک می‌فرستد؛ این ستون از ارسال تکراری جلوگیری می‌کند.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('unaccepted_notified_at')->nullable()->after('broadcast_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('unaccepted_notified_at');
        });
    }
};
