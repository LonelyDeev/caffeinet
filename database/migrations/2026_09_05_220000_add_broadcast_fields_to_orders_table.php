<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فاز ۶ — موتور تخصیص سفارش:
     *  broadcast_expires_at : پایان مهلت پخش (۶۰ ثانیه پیش‌فرض از تنظیمات)
     *  broadcast_attempts   : تعداد دفعات پخش (برای ری‌پخش محدود)
     *  queued_at            : زمان ورود به صف تعیین‌تکلیف دستی
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('broadcast_expires_at')->nullable()->after('accepted_at');
            $table->unsignedTinyInteger('broadcast_attempts')->default(0)->after('broadcast_expires_at');
            $table->timestamp('queued_at')->nullable()->after('broadcast_attempts');
            $table->index('broadcast_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['broadcast_expires_at']);
            $table->dropColumn(['broadcast_expires_at', 'broadcast_attempts', 'queued_at']);
        });
    }
};
