<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * مجوز «مدیریت مشتریان» برای بخش مشتریان پنل مدیریت کل (data migration).
     * برای نصب‌های موجود؛ نصب تازه همین را از Seeder می‌گیرد.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'customers.manage', 'guard_name' => 'web']);

        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin && ! $superAdmin->hasPermissionTo('customers.manage')) {
            $superAdmin->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'customers.manage')->delete();
    }
};
