<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v26 — پشتیبانی چند سرویس نوتیف دستگاه در push_tokens:
     *  • provider: firebase | webpush (پیش‌فرض داخلی) | pusher (Beams)
     *  • p256dh / auth: کلیدهای اشتراک Web Push (برای provider=webpush؛
     *    token همان endpoint است)
     */
    public function up(): void
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            $table->string('provider', 20)->default('firebase')->after('token');
            $table->string('p256dh', 190)->nullable()->after('provider');
            $table->string('auth', 190)->nullable()->after('p256dh');

            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'provider']);
            $table->dropColumn(['provider', 'p256dh', 'auth']);
        });
    }
};
