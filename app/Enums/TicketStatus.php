<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case CustomerReply = 'customer_reply';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'باز',
            self::Answered => 'پاسخ داده‌شده',
            self::CustomerReply => 'پاسخ مشتری',
            self::Closed => 'بسته‌شده',
        };
    }
}
