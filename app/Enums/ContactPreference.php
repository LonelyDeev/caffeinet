<?php

namespace App\Enums;

/**
 * v39/v40 — راه‌های ارتباطی پیشنهادی مشتری پس از پایان مهلت پخش بدون پذیرش اپراتور.
 *
 * v40 — مدل جدید (درخواست مالک): «تماس تلفنی» یک چک‌باکس مستقل است (می‌شود
 * تیکش را برداشت) و «چت» یک انتخاب یگانه از پیام‌رسان‌ها. مقدار ذخیره‌شده
 * در orders.contact_preference ترکیبی است:
 *
 *      "call,telegram"   → تماس + چت تلگرام
 *      "call"            → فقط تماس تلفنی
 *      "telegram"        → فقط چت تلگرام
 *      "call,any"        → تماس + «فرقی ندارد»
 *
 * مقادیر تک‌بخشی نسخهٔ v39 (call/app_chat/telegram/whatsapp/bale/eitaa/any)
 * همچنان به‌درستی تفسیر می‌شوند (سازگاری با داده‌های موجود).
 */
enum ContactPreference: string
{
    case Call = 'call'; // تماس تلفنی (چک‌باکس مستقل v40)
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

    /** توضیح کوتاه برای نمایش زیر گزینه (v40) */
    public function description(): string
    {
        return match ($this) {
            self::Call => 'کارشناس ما مستقیماً با شما تماس می‌گیرد',
            self::AppChat => 'گفتگو در همین برنامه و همین سفارش',
            self::Telegram => 'پیام از طریق تلگرام',
            self::WhatsApp => 'پیام از طریق واتس‌اپ',
            self::Bale => 'پیام از طریق بله',
            self::Eitaa => 'پیام از طریق ایتا',
            self::Any => 'هر راهی که برای ما راحت‌تر باشد',
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

    /** گزینه‌های بخش «چت» (انتخاب یگانه — v40) */
    public static function chatOptions(): array
    {
        return [self::AppChat, self::Telegram, self::WhatsApp, self::Bale, self::Eitaa, self::Any];
    }

    /** مقدار ترکیبی از اجزا می‌سازد: call?,chat */
    public static function fromParts(bool $call, ?string $chat): string
    {
        $tokens = [];
        if ($call) {
            $tokens[] = self::Call->value;
        }
        if ($chat && $chat !== self::Call->value) {
            $tokens[] = $chat;
        }

        return implode(',', $tokens) ?: self::Any->value;
    }

    /**
     * تجزیهٔ مقدار ذخیره‌شده → [call => bool, chat => enum|null].
     * مقادیر قدیمی تک‌بخشی هم پشتیبانی می‌شوند.
     *
     * @return array{call: bool, chat: self|null}
     */
    public static function parse(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return ['call' => false, 'chat' => null];
        }

        $tokens = array_filter(array_map('trim', explode(',', $value)));

        $call = false;
        $chat = null;

        foreach ($tokens as $token) {
            if ($token === self::Call->value) {
                $call = true;
                continue;
            }
            try {
                $chat = self::from($token);
            } catch (\ValueError) {
                // توکن ناشناخته — نادیده گرفته می‌شود
            }
        }

        return ['call' => $call, 'chat' => $chat];
    }

    /** شرح فارسی کامل ترکیب برای پنل ادمین (v40) */
    public static function describe(?string $value): ?string
    {
        if (! $value || trim($value) === '') {
            return null;
        }

        $parts = self::parse($value);
        $labels = [];

        if ($parts['call']) {
            $labels[] = '📞 '.self::Call->label();
        }
        if ($parts['chat']) {
            $labels[] = $parts['chat']->icon().' '.$parts['chat']->label();
        }

        return $labels ? implode(' + ', $labels) : null;
    }
}
