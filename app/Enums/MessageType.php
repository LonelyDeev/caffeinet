<?php

namespace App\Enums;

/**
 * انواع پیام گفتگوی سفارش (چت تلگرام‌گونه — فاز ۷).
 */
enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case File = 'file';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'متن',
            self::Image => 'تصویر',
            self::Audio => 'صدا',
            self::Video => 'ویدیو',
            self::File => 'فایل',
            self::System => 'سیستمی',
        };
    }

    /** آیا این نوع دارای فایل پیوست است؟ */
    public function hasFile(): bool
    {
        return in_array($this, [self::Image, self::Audio, self::Video, self::File], true);
    }
}
