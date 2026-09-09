<?php

namespace App\Support;

/**
 * کاتالوگ دسترسی‌های قابل‌گرانت به اپراتور در یک کافی‌نت.
 *
 * مدیر کافی‌نت از پنل خود این لیست را برای هر کارمند شخصی‌سازی می‌کند؛
 * مقدار در staff_assignments.permissions (JSON) ذخیره می‌شود و
 * در پنل اپراتور (فاز ۶+) به‌عنوان لایه دوم روی نقش Spatie اعمال می‌شود.
 */
class OperatorPermissions
{
    /** @var array<string, string> کلید => عنوان فارسی */
    public const CATALOG = [
        'dashboard.access' => 'دسترسی به داشبورد',
        'orders.view.own' => 'مشاهده سفارش‌های خودش',
        'orders.view' => 'مشاهده همه سفارش‌های کافی‌نت',
        'orders.accept' => 'قبول سفارش‌های پخش‌شده',
        'orders.update_status' => 'تغییر وضعیت سفارش',
        'tickets.view' => 'مشاهده تیکت‌های پشتیبانی',
    ];

    /** پیش‌فرضِ یک اپراتور جدید (مطابق نقش پایه) */
    public static function defaults(): array
    {
        return [
            'dashboard.access',
            'orders.view.own',
            'orders.accept',
            'orders.update_status',
            'tickets.view',
        ];
    }

    /** فقط کلیدهای مجاز کاتالوگ را نگه می‌دارد */
    public static function filter(?array $permissions): array
    {
        return array_values(array_intersect(
            array_unique((array) $permissions),
            array_keys(self::CATALOG),
        ));
    }
}
