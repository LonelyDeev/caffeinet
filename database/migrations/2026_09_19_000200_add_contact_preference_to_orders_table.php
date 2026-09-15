<?php

use App\Enums\ContactPreference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v39 — ترجیح راه ارتباطی مشتری پس از پایان مهلت پخش بدون پذیرش اپراتور:
 * مشتری در کارت «صف تعیین‌تکلیف» انتخاب می‌کند کارشناسان از چه راهی با او
 * در تماس باشند (تماس تلفنی، چت داخل برنامه، تلگرام، واتس‌اپ، بله، ایتا یا فرقی ندارد).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('contact_preference', 40)->nullable()->after('unaccepted_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('contact_preference');
        });
    }
};
