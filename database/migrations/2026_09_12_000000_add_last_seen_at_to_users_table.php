<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v25 — حضور کاربران (last_seen_at):
     * برای تشخیص «آنلاین بودن» هنگام ارسال نوتیف دستگاه (FCM) و پیامک‌های
     * رویدادی (فقط وقتی کاربر آفلاین است ارسال می‌شوند).
     * میدل‌ور UpdateLastSeen روی هر درخواست وب/API آن را تازه می‌کند.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
