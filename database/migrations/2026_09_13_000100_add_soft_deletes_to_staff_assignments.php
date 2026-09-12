<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v28 — حذف نرم برای عضویت‌های کاری (کارکنان کافی‌نت).
     * لازم برای بازگردانی صحیح مدیر/کارمند پس از حذف کافی‌net یا کاربر.
     */
    public function up(): void
    {
        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropColumn('deleted_at');
        });
    }
};
