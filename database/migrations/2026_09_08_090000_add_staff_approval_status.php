<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * فاز ۱۲+ — گردش‌کار تایید کارمندان:
 *   staff_assignments.approval_status (approved | pending | rejected)
 *   - pending: مدیر کافی‌نت ساخته و منتظر تایید مدیر کل است (is_active=false)
 *   - approved: مدیر کل تایید کرده (یا حالت «تایید خودکار»)
 *   - rejected: مدیر کل رد کرده (غیرفعال — فقط برای تاریخچه)
 *
 * همچنین endpoint فراز به API پترن‌محور ایران‌پیامک مهاجرت می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->string('approval_status')->default('approved')->index()->after('is_active');
        });

        // همهٔ عضویت‌های موجود → تاییدشده
        DB::table('staff_assignments')->whereNull('approval_status')->update(['approval_status' => 'approved']);

        // تنظیم جدید: سیاست افزودن کارمند (پیش‌فرض = تایید خودکار)
        DB::table('settings')->insertOrIgnore([
            'group' => 'staff',
            'key' => 'staff.hiring.mode',
            'value' => 'auto',
            'cast' => 'string',
            'label' => 'افزودن کارمند توسط مدیر کافی‌نت (auto | approval)',
            'is_sensitive' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // مهاجرت اندپوینت فراز به API پترن ایران‌پیامک (مطابق درایور جدید)
        DB::table('settings')
            ->where('key', 'sms.fraasms.endpoint')
            ->whereIn('value', ['', 'https://ippanel.com/api/send'])
            ->update(['value' => 'https://api.iranpayamak.com/ws/v1/sms/pattern']);
    }

    public function down(): void
    {
        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->dropIndex(['approval_status_index']);
            $table->dropColumn('approval_status');
        });

        DB::table('settings')->where('key', 'staff.hiring.mode')->delete();
    }
};
