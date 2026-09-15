<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v40 — نشان تأیید فینوتک روی کارت‌های بانکی.
 *
 * verified_at: تاریخ موفق استعلام «تطبیق شماره کارت و کد ملی» (فینوتک)
 * verified_method: روش استعلام (nid = تطبیق کارت و کد ملی)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_cards', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('is_default');
            $table->string('verified_method', 20)->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('bank_cards', function (Blueprint $table) {
            $table->dropColumn(['verified_at', 'verified_method']);
        });
    }
};
