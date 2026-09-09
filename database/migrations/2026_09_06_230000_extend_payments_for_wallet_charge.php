<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * گسترش جدول payments برای «شارژ کیف پول از درگاه»:
     *  - order_id اختیاری می‌شود (پرداخت شارژ کیف سفارش ندارد)
     *  - ستون purpose: order (پیش‌فرض — پرداخت سفارش) | wallet (شارژ کیف پول)
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
            $table->string('purpose', 20)->default('order')->index()->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('purpose');
            $table->foreignId('order_id')->nullable(false)->change();
        });
    }
};
