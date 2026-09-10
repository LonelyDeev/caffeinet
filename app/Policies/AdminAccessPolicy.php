<?php

namespace App\Policies;

use App\Models\User;

/**
 * پالیس دسترسی مدیران پنل مدیریت کل (درخواست بازخوردی ۶-۴).
 *
 * هر «بخش» از پنل (منو/صفحه) به یک مجوز Spatie نگاشت می‌شود.
 * مدیر کل (super_admin) از طریق Gate::before پاس همهٔ بررسی‌هاست؛
 * مدیران دستیار (admin) فقط بخش‌هایی را می‌بینند که مجوزشان را
 * مدیر کل از صفحه «مدیران سیستم» فعال کرده باشد.
 *
 * نحوهٔ استفاده (مکانیزم Policy لاراول):
 *   $user->can('access', [User::class, 'orders'])   → AdminAccessPolicy::access($user, 'orders')
 *   در Blade: @can('access', [User::class, 'orders']) ... @endcan
 */
class AdminAccessPolicy
{
    /**
     * نگاشت بخش پنل → کلید مجوز Spatie.
     * null یعنی «بدون مجوز اضافه» — برای همهٔ مدیران پنل باز است.
     */
    public const SECTION_PERMISSIONS = [
        'dashboard' => 'dashboard.access',
        'orders' => 'orders.view',
        'services' => 'services.manage',
        'service-categories' => 'services.manage',
        'admins' => 'admins.manage',
        'organizations' => 'organizations.manage',
        'coffeenets' => 'coffeenets.manage',
        'operators' => 'staff.manage',
        'customers' => 'customers.manage',
        'withdrawals' => 'withdrawals.manage',
        'chats' => 'orders.view',
        'commissions' => 'commission.manage',
        'settlements' => 'payments.view',
        'finance' => 'reports.financial',
        'analytics' => 'reports.view',
        'tickets' => 'tickets.manage',
        'settings' => 'settings.manage',
        'sms-templates' => 'settings.manage',
        'sms-logs' => 'settings.manage',
        'announcements' => 'settings.manage',
        'audit' => 'audit.view',
        'system' => 'settings.manage',
        'api-docs' => null, // مستندات برای همهٔ مدیران پنل قابل مشاهده است
        'guide' => null, // راهنمای پنل برای همهٔ مدیران قابل مشاهده است (فاز ۱۳)
    ];

    /** عنوان فارسی بخش‌ها (برای پیام خطا و UI ماتریس مجوز) */
    public const SECTION_LABELS = [
        'dashboard' => 'داشبورد',
        'orders' => 'سفارش‌ها',
        'services' => 'خدمات و فرم‌ساز',
        'service-categories' => 'دسته‌بندی خدمات',
        'admins' => 'مدیران سیستم',
        'organizations' => 'سازمان‌ها',
        'coffeenets' => 'کافی‌نت‌ها',
        'operators' => 'کارکنان و اپراتورها',
        'customers' => 'مشتریان',
        'withdrawals' => 'برداشت‌ها',
        'chats' => 'گفتگوهای سفارش',
        'commissions' => 'قواعد کمیسیون',
        'settlements' => 'تسویه‌ها',
        'finance' => 'گزارش مالی',
        'analytics' => 'گزارش تحلیلی',
        'tickets' => 'تیکت‌های پشتیبانی',
        'settings' => 'تنظیمات سیستم',
        'sms-templates' => 'قالب‌های پیامک',
        'sms-logs' => 'لاگ پیامک‌ها',
        'announcements' => 'اطلاعیه‌های سامانه',
        'audit' => 'لاگ فعالیت',
        'system' => 'وضعیت سیستم',
        'api-docs' => 'مستندات API',
        'guide' => 'راهنمای پنل',
    ];

    /** مجوز لازم برای یک بخش (null = بدون مجوز) */
    public static function permissionFor(string $section): ?string
    {
        // array_key_exists چون مقدار null (بخش آزاد) با ?? اشتباه «ناموجود» فرض می‌شود
        return array_key_exists($section, self::SECTION_PERMISSIONS)
            ? self::SECTION_PERMISSIONS[$section]
            : '__unknown__';
    }

    /**
     * بررسی دسترسی به بخش — متد اصلی پالیس.
     * Gate::before مدیر کل را از قبل پاس کرده است؛ اینجا فقط دستیارها بررسی می‌شوند.
     */
    public function access(User $user, string $section): bool
    {
        // فقط نقش‌های پنل مدیریت کل
        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            return false;
        }

        $permission = self::permissionFor($section);

        // بخش ناشناخته → مسدود (سبک fail-closed)
        if ($permission === '__unknown__') {
            return false;
        }

        // بخش آزاد (مثل مستندات API)
        if ($permission === null) {
            return true;
        }

        return $user->can($permission);
    }

    /**
     * آیا کاربر فعلی بخش را می‌بیند؟ (کمکی برای سایدبار/بلید)
     * مدیر کل همیشه true.
     */
    public static function canSection(?User $user, string $section): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return app(\Illuminate\Contracts\Auth\Access\Gate::class)
            ->forUser($user)
            ->allows('access', [User::class, $section]);
    }
}
