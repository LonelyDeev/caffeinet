<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Success => 'موفق',
            self::Failed => 'ناموفق',
            self::Cancelled => 'لغوشده',
            self::Refunded => 'بازگشت وجه',
        };
    }
}
