<?php

namespace App\Enums;

enum SalaryType: string
{
    case Percent = 'percent';
    case FixedPerOrder = 'fixed_per_order';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'درصدی از هر سفارش',
            self::FixedPerOrder => 'مبلغ ثابت هر سفارش',
            self::Monthly => 'ماهیانه',
        };
    }
}
