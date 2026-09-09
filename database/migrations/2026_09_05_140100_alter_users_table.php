<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تکمیل جدول کاربران برای هر دو دنیا:
     * مدیران/کارکنان (email) و مشتریان (mobile + OTP + پروفایل اجباری)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->string('mobile', 15)->nullable()->unique()->after('id');
            $table->string('family')->nullable()->after('name');
            $table->string('gender', 10)->nullable()->after('family'); // male | female
            $table->foreignId('province_id')->nullable()->after('gender')
                ->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('province_id')
                ->constrained('cities')->nullOnDelete();
            $table->date('birthdate')->nullable()->after('city_id');
            $table->timestamp('mobile_verified_at')->nullable();
            $table->boolean('profile_completed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'mobile', 'family', 'gender', 'province_id', 'city_id',
                'birthdate', 'mobile_verified_at', 'profile_completed',
                'is_active', 'last_login_at',
            ]);
            $table->string('name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
