<?php

namespace App\Enums;

enum StaffPosition: string
{
    case Manager = 'manager';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'مدیر کافی‌نت',
            self::Operator => 'اپراتور',
        };
    }
}
