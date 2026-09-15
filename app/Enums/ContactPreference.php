<?php

namespace App\Enums;

/**
 * v39 — راه‌های ارتباطی پیشنهادی مشتری پس از پایان مهلت پخش بدون پذیرش اپراتور.
 *
 * مشتری در کارت «صف تعیین‌تکلیف» اپ خود انتخاب می‌کند کارشناسان کافی‌نتی از
 * چه راهی با او در تماس باشند؛ انتخاب برای مدیر (صف تعیین‌تکلیف پنل ادمین) نمایش داده می‌شود.
 */
enum ContactPreference: string
{
    case Call = 'call'; // تماس تلفنی
    case AppChat = 'app_chat'; // چت داخل خود برنامه
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Bale = 'bale';
    case Eitaa = 'eitaa';
    case Any = 'any'; // فرقی ندارد

    /** عنوان فارسی */
    public function label(): string
    {
        return match ($this) {
            self::Call => 'تماس تلفنی',
            self::AppChat => 'چت داخل برنامه',
            self::Telegram => 'تلگرام',
            self::WhatsApp => 'واتس‌اپ',
            self::Bale => 'بله',
            self::Eitaa => 'ایتا',
            self::Any => 'فرقی ندارد',
        };
    }

    /** ایموجی/آیکون نمایشی */
    public function icon(): string
    {
        return match ($this) {
            self::Call => '📞',
            self::AppChat => '💬',
            self::Telegram => '✈️',
            self::WhatsApp => '🟢',
            self::Bale => '🔵',
            self::Eitaa => '📨',
            self::Any => '🤝',
        };
    }

    /** همهٔ مقادیر مجاز برای اعتبارسنجی */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
