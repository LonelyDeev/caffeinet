<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * نقش‌ها و دسترسی‌های سیستم (RBAC)
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // هسته پنل‌ها
            'dashboard.access',

            // مدیریت کل
            'admins.manage',
            'customers.manage',
            'settings.manage',
            'audit.view',
            'organizations.manage',
            'coffeenets.manage',
            'coffeenets.approve',
            'referral.manage',
            'commission.manage',
            'sms.send',

            // سازمان
            'coffeenets.introduce',
            'wallet.withdraw',
            'wallet.view',

            // کافی‌نت
            'staff.manage',
            'salary.manage',
            'coffeenet.settings.manage',

            // خدمات
            'services.manage',
            'services.view',

            // سفارش‌ها
            'orders.view',
            'orders.view.own',
            'orders.assign',
            'orders.update_status',
            'orders.accept',

            // مالی
            'payments.view',
            'withdrawals.manage',
            'reports.view',
            'reports.financial',

            // پشتیبانی
            'tickets.manage',
            'tickets.view',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roles = [
            'super_admin' => $permissions, // همه دسترسی‌ها (+ Gate::before)
            'org_manager' => ['dashboard.access', 'coffeenets.introduce', 'wallet.view', 'wallet.withdraw'],
            'coffeenet_manager' => [
                'dashboard.access', 'staff.manage', 'salary.manage',
                'coffeenet.settings.manage', 'orders.view', 'orders.assign',
                'services.view', 'reports.view', 'tickets.view', 'tickets.manage',
            ],
            'operator' => [
                'dashboard.access', 'orders.view.own', 'orders.accept',
                'orders.update_status', 'tickets.view',
            ],
            'customer' => [],
        ];

        foreach ($roles as $role => $rolePermissions) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
                ->syncPermissions($rolePermissions);
        }
    }
}
