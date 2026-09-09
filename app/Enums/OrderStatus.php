<?php

namespace App\Enums;

/**
 * ماشین وضعیت سفارش «کافی‌نت آنلاین»
 *
 * فاز ۱۱ — جریان جدید «اتصال اول، پرداخت بعد»:
 * pending_payment → broadcasting(۶۰s) → queued
 * broadcasting → accepted (اپراتور/کافی‌نت) → paid → in_progress → delivered → completed
 * لغو مشتری فقط پیش از پرداخت (pending/broadcasting/queued/accepted)
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Broadcasting = 'broadcasting';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case NeedsInfo = 'needs_info';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Queued = 'queued'; // صف تعیین‌تکلیف دستی (بعد از ۶۰ ثانیه بدون پذیرش)
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'در انتظار پذیرش',
            self::Paid => 'پرداخت‌شده',
            self::Broadcasting => 'در انتظار پذیرش اپراتور',
            self::Accepted => 'پذیرفته‌شده',
            self::InProgress => 'در حال انجام',
            self::NeedsInfo => 'نیازمند اطلاعات',
            self::Delivered => 'تحویل‌شده',
            self::Completed => 'تکمیل‌شده',
            self::Queued => 'در صف تعیین‌تکلیف',
            self::Cancelled => 'لغوشده',
            self::Refunded => 'بازگشت وجه',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingPayment, self::Paid => 'amber',
            self::Broadcasting, self::Queued => 'sky',
            self::Accepted => 'teal',
            self::InProgress => 'blue',
            self::NeedsInfo => 'orange',
            self::Delivered => 'teal',
            self::Completed => 'emerald',
            self::Cancelled, self::Refunded => 'rose',
        };
    }

    /** وضعیت‌های مجاز بعدی (قواعد انتقال) */
    public function allowedTransitions(): array
    {
        return match ($this) {
            // فاز ۱۱: ثبت → پخش فوری؛ Paid فقط برای سفارش‌های قدیمی (legacy)
            self::PendingPayment => [self::Broadcasting, self::Queued, self::Cancelled, self::Paid],
            // فاز ۱۱: بعد از پرداخت دیگر پخشی نیست — اپراتور قبلاً وصل است
            self::Paid => [self::InProgress, self::Cancelled, self::Refunded],
            self::Broadcasting => [self::Accepted, self::Queued, self::Cancelled],
            self::Queued => [self::Accepted, self::Broadcasting, self::Cancelled],
            // فاز ۱۱: پذیرش → پرداخت مشتری → شروع کار
            self::Accepted => [self::Paid, self::InProgress, self::NeedsInfo, self::Cancelled],
            self::InProgress => [self::NeedsInfo, self::Delivered, self::Cancelled],
            self::NeedsInfo => [self::InProgress, self::Delivered, self::Cancelled],
            self::Delivered => [self::Completed, self::Refunded],
            self::Completed, self::Cancelled, self::Refunded => [],
        };
    }

    /** مقدارهای وضعیت‌هایی که چت «قابل ارسال» است (فاز ۷ + paid فاز ۱۱) */
    public static function chattableValues(): array
    {
        return [
            self::Accepted->value,
            self::Paid->value, // فاز ۱۱: گفتگو بعد از پرداخت هم فعال می‌ماند تا تحویل
            self::InProgress->value,
            self::NeedsInfo->value,
        ];
    }

    /** مقدارهای وضعیت‌هایی که گفتگو قابل مشاهده است (ارسال + فقط-خواندن) */
    public static function chatVisibleValues(): array
    {
        return array_merge(self::chattableValues(), [
            self::Delivered->value,
            self::Completed->value,
        ]);
    }
}
