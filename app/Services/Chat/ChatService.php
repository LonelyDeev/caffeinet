<?php

namespace App\Services\Chat;

use App\Enums\MessageType;
use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Services\Realtime\PusherService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * موتور گفتگوی سفارش (فاز ۷ — چت تلگرام‌گونه).
 *
 * یک گفتگو برای هر سفارش (customer ↔ اپراتورهای کافی‌نت):
 *  - پیام متنی و چندرسانه‌ای (تصویر/صدا/ویدیو/فایل) با ذخیره در دیسک خصوصی
 *  - پیام‌های سیستمی برای گذارهای وضعیت (بدون فرستنده)
 *  - پولینگ افزایشی (after_id) + دیده‌شدن (seen_at) یک‌طرفه
 *  - فایل‌ها فقط با URL موقتِ امضاشده در دسترس‌اند
 */
class ChatService
{
    /** وابستگی Realtime — بیدارباش Pusher برای پیام‌های جدید */
    protected function pusher(): PusherService
    {
        return app(PusherService::class);
    }

    /** وضعیت‌هایی که چت «قابل ارسال» است */
    public const SEND_STATUSES = [
        OrderStatus::Accepted,
        OrderStatus::Paid, // فاز ۱۱: گفتگو بعد از پرداخت هم فعال می‌ماند تا تحویل
        OrderStatus::InProgress,
        OrderStatus::NeedsInfo,
    ];

    /** وضعیت‌هایی که گفتگو فقط «خواندنی» است */
    public const READONLY_STATUSES = [
        OrderStatus::Delivered,
        OrderStatus::Completed,
    ];

    /** قواعد آپلود بر اساس نوع: mimeهای مجاز + حداکثر حجم (کیلوبایت) + برچسب */
    public const FILE_RULES = [
        'image' => ['mimes' => 'jpg,jpeg,png,webp,gif', 'max' => 5120, 'label' => 'تصویر'],
        'audio' => ['mimes' => 'mp3,ogg,wav,m4a,aac,opus', 'max' => 10240, 'label' => 'صدا'],
        'video' => ['mimes' => 'mp4,webm,mkv,mov', 'max' => 51200, 'label' => 'ویدیو'],
        'file' => ['mimes' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z', 'max' => 20480, 'label' => 'فایل'],
    ];

    /** پیام‌های متنی از این تعداد کاراکتر بیشتر خطا می‌خورند */
    public const MAX_TEXT_LENGTH = 2000;

    /** اعتبار لینک فایل‌های چت (ساعت) */
    public const FILE_URL_HOURS = 6;

    /* ================================================================== */
    /* ۱) وضعیت و دسترسی چت                                                */
    /* ================================================================== */

    /** آیا گفتگو در این وضعیت قابل ارسال است؟ */
    public function canSend(Order $order): bool
    {
        return in_array($order->status, self::SEND_STATUSES, true);
    }

    /** آیا گفتگو در این وضعیت قابل مشاهده است (ارسال یا فقط-خواندن)؟ */
    public function canView(Order $order): bool
    {
        return $this->canSend($order) || in_array($order->status, self::READONLY_STATUSES, true);
    }

    /** فرادادهٔ وضعیت چت (روی پاسخ API هم می‌رود) */
    public function chatMeta(Order $order): array
    {
        return [
            'enabled' => $this->canView($order),
            'can_send' => $this->canSend($order),
            'readonly' => $this->canView($order) && ! $this->canSend($order),
        ];
    }

    /* ================================================================== */
    /* ۲) گفتگو                                                            */
    /* ================================================================== */

    /** گفتگوی سفارش (در صورت نبود، ساخته می‌شود) */
    public function conversationFor(Order $order): Conversation
    {
        return Conversation::query()->firstOrCreate(
            ['order_id' => $order->id],
            ['created_at' => now()],
        );
    }

    /* ================================================================== */
    /* ۳) ارسال                                                            */
    /* ================================================================== */

    /**
     * ارسال پیام کاربری (متن یا چندرسانه‌ای).
     *
     * @param  string  $type  text|image|audio|video|file
     * @param  UploadedFile|null  $file  برای انواع چندرسانه‌ای الزامی است
     * @param  float|null  $duration  مدت صدا/ویدیو (ثانیه — از کلاینت)
     */
    public function send(Order $order, User $sender, string $type, ?string $content = null, ?UploadedFile $file = null, ?float $duration = null): Message
    {
        if (! $this->canSend($order)) {
            throw ValidationException::withMessages([
                'chat' => ['گفتگوی این سفارش در وضعیت «'.$order->status->label().'» قابل ارسال پیام نیست.'],
            ]);
        }

        $enum = MessageType::tryFrom($type);

        if (! $enum || ! $enum->hasFile()) {
            $enum = MessageType::Text;
        }

        $meta = null;

        if ($enum->hasFile()) {
            if (! $file || ! $file->isValid()) {
                throw ValidationException::withMessages([
                    'file' => ['فایل '.self::FILE_RULES[$enum->value]['label'].' الزامی است.'],
                ]);
            }

            $meta = $this->validateAndStore($order, $enum, $file, $duration);
            $content = $content ? trim(mb_substr($content, 0, self::MAX_TEXT_LENGTH)) : null; // کپشن اختیاری
        } else {
            $content = trim((string) $content);

            if ($content === '') {
                throw ValidationException::withMessages([
                    'content' => ['متن پیام خالی است.'],
                ]);
            }

            if (mb_strlen($content) > self::MAX_TEXT_LENGTH) {
                throw ValidationException::withMessages([
                    'content' => ['متن پیام نباید بیش از '.fa_digits(self::MAX_TEXT_LENGTH).' کاراکتر باشد.'],
                ]);
            }
        }

        $conversation = $this->conversationFor($order);

        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'message_type' => $enum->value,
            'content' => $content,
            'file_path' => $meta['path'] ?? null,
            'file_meta' => $meta ? $meta['meta'] : null,
            'created_at' => now(),
        ]);

        // Realtime (فاز ۱۳): بیدارباش لحظه‌ای طرف مقابل از طریق پوشر
        $this->pusher()->chatMessage($order, (int) $message->id, (int) $sender->id);

        return $message;
    }

    /**
     * پیام سیستمی (تاریخچهٔ گذار وضعیت داخل چت) — هرگز جریان اصلی را نمی‌شکند.
     *
     * @param  bool  $onlyIfExists  فقط وقتی گفتگو وجود دارد (مثلاً لغو)
     */
    public function systemMessage(Order $order, string $text, bool $onlyIfExists = false): ?Message
    {
        try {
            $conversation = $onlyIfExists
                ? Conversation::query()->where('order_id', $order->id)->first()
                : $this->conversationFor($order);

            if (! $conversation) {
                return null;
            }

            $message = $conversation->messages()->create([
                'sender_id' => null,
                'message_type' => MessageType::System->value,
                'content' => mb_substr($text, 0, self::MAX_TEXT_LENGTH),
                'created_at' => now(),
            ]);

            // Realtime: رویداد سیستمی (تغییر وضعیت/اتصال) هم باید چت را بیدار کند
            $this->pusher()->chatMessage($order, (int) $message->id, null);

            return $message;
        } catch (\Throwable) {
            return null; // چت هرگز نباید عملیات اصلی سفارش را متوقف کند
        }
    }

    /* ================================================================== */
    /* ۴) پولینگ و دیده‌شدن                                                 */
    /* ================================================================== */

    /**
     * پیام‌های گفتگو برای پولینگ افزایشی.
     *
     * @param  int|null  $afterId  null/0 → آخرین پیام‌ها (صفحهٔ اول)، >0 → فقط جدیدترها
     */
    public function messages(Conversation $conversation, ?int $afterId = null, int $limit = 60): array
    {
        $query = $conversation->messages()->with('sender:id,name,family');

        if ($afterId && $afterId > 0) {
            $query->where('id', '>', $afterId)->orderBy('id');

            return $query->get()->all();
        }

        // آخرین N پیام (نزولی) → نمایش صعودی
        return $query->latest('id')->limit($limit)->get()->reverse()->values()->all();
    }

    /** آخرین شناسهٔ پیام گفتگو (برای after_id) */
    public function lastId(Conversation $conversation): int
    {
        return (int) $conversation->messages()->max('id');
    }

    /**
     * پیام‌های فرستاده‌شده توسط «طرف مقابل بیننده» را دیده‌شده علامت می‌زند.
     *
     * @param  string  $viewerSide  'customer' | 'operator' — سمت بیننده
     */
    public function markSeen(Conversation $conversation, string $viewerSide): void
    {
        if (! in_array($viewerSide, ['customer', 'operator'], true)) {
            return;
        }

        $order = $conversation->order()->select(['id', 'customer_id'])->first();

        if (! $order) {
            return;
        }

        $otherIsCustomer = $viewerSide === 'operator'; // مقابلِ اپراتور = مشتری

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('seen_at');

        if ($otherIsCustomer) {
            // اپراتور در حال دیدن ← پیام‌های مشتری دیده شد
            $query->where('sender_id', $order->customer_id);
        } else {
            // مشتری در حال دیدن ← پیام‌های غیر-مشتری (اپراتورها + سیستمی)
            $query->where(
                fn ($q) => $q->whereNull('sender_id')->orWhere('sender_id', '!=', $order->customer_id),
            );
        }

        $query->update(['seen_at' => now()]);
    }

    /** پیام‌های دیده‌نشدهٔ «مشتری» در گفتگوهای یک کافی‌نت (بج ناخوانده) */
    public function unseenForCoffeenet(int $coffeenetId): int
    {
        return (int) Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('orders', 'orders.id', '=', 'conversations.order_id')
            ->where('orders.coffeenet_id', $coffeenetId)
            ->whereNull('messages.seen_at')
            ->whereColumn('messages.sender_id', 'orders.customer_id')
            ->count();
    }

    /* ================================================================== */
    /* ۵) فایل‌ها                                                          */
    /* ================================================================== */

    /** URL موقتِ امضاشده برای فایل پیام (۶ ساعت) */
    public function fileUrl(Message $message): ?string
    {
        if (! $message->file_path) {
            return null;
        }

        try {
            return URL::temporarySignedRoute(
                'files.chat',
                now()->addHours(self::FILE_URL_HOURS),
                ['message' => $message->id],
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /** آیا فایل باید «درون‌برنامه‌ای» نمایش داده شود؟ (تصویر/صدا/ویدیو) */
    public function isInlineType(MessageType $type): bool
    {
        return in_array($type, [MessageType::Image, MessageType::Audio, MessageType::Video], true);
    }

    /* ================================================================== */
    /* ۶) سریال‌سازی                                                        */
    /* ================================================================== */

    /**
     * ساختار JSON پیام برای فرانت (هر دو سمت).
     *
     * @param  int|null  $viewerId  شناسهٔ بیننده (برای mine/seen)
     */
    public function serializeMessage(Message $message, ?int $viewerId = null, ?Order $order = null): array
    {
        $order ??= $message->conversation?->order()->select(['id', 'customer_id'])->first();

        $senderId = $message->sender_id;
        $role = 'system';

        if ($senderId) {
            $role = $order && (int) $senderId === (int) $order->customer_id ? 'customer' : 'operator';
        }

        $type = MessageType::tryFrom($message->message_type) ?? MessageType::Text;

        $payload = [
            'id' => $message->id,
            'type' => $type->value,
            'role' => $role,
            'sender' => $senderId ? [
                'id' => $senderId,
                'name' => trim(($message->sender?->name ?? '').' '.($message->sender?->family ?? '')) ?: 'کاربر',
            ] : null,
            'content' => $role === 'system' || ! $type->hasFile() ? $message->content : ($message->content ?: null),
            'time_fa' => fa_date($message->created_at, 'H:i'),
            'date_fa' => fa_date($message->created_at, 'Y/m/d'),
            'ts' => optional($message->created_at)->timestamp,
            'mine' => $viewerId && $senderId && (int) $viewerId === (int) $senderId,
            'seen' => $message->seen_at !== null,
        ];

        // نوع رویداد سیستمی — برای نمایش زیبا (مثل «اتصال اپراتور») در فرانت
        if ($role === 'system') {
            $payload['kind'] = $this->systemKind((string) $message->content);
        }

        if ($type->hasFile() && $message->file_path) {
            $meta = (array) ($message->file_meta ?? []);

            $payload['file'] = [
                'url' => $this->fileUrl($message),
                'name' => $meta['name'] ?? 'فایل',
                'size' => (int) ($meta['size'] ?? 0),
                'size_fa' => fa_number($meta['size'] ?? 0).' کیلوبایت',
                'mime' => $meta['mime'] ?? null,
                'duration' => isset($meta['duration']) ? round((float) $meta['duration']) : null,
                'inline' => $this->isInlineType($type),
            ];
        }

        return $payload;
    }

    /** پیام‌های خود بیننده که مقابل دیده است (برای تیک دوتایی در پولینگ افزایشی) */
    public function seenMineIds(Order $order, ?int $viewerId, int $limit = 100): array
    {
        if (! $viewerId) {
            return [];
        }

        $conversationId = Conversation::query()->where('order_id', $order->id)->value('id');

        if (! $conversationId) {
            return [];
        }

        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('sender_id', $viewerId)
            ->whereNotNull('seen_at')
            ->latest('id')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    /**
     * پاسخ استاندارد پولینگ/بارگذاری اولیه.
     *
     * @param  Message[]  $messages
     */
    public function payload(Order $order, array $messages, ?int $viewerId): array
    {
        $pusher = $this->pusher();

        return [
            'data' => array_map(fn (Message $m) => $this->serializeMessage($m, $viewerId, $order), $messages),
            'last_id' => $messages ? (int) end($messages)->id : 0,
            'chat' => $this->chatMeta($order),
            // Realtime (فاز ۱۳): کلاینت با این کانال پوشر، پولینگ خود را «بیدار» می‌کند
            'rt' => $pusher->enabled() ? [
                'enabled' => true,
                'key' => (string) app(\App\Services\Settings\SettingsService::class)->get('realtime.pusher.app_key', ''),
                'cluster' => (string) app(\App\Services\Settings\SettingsService::class)->get('realtime.pusher.cluster', 'mt1'),
                'channel' => $pusher->chatChannel((int) $order->id),
                'event' => 'message.new',
            ] : ['enabled' => false],
            'order' => [
                'id' => $order->id,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'is_paid' => (bool) $order->paid_at, // فاز ۱۱ — نشانگر پرداخت برای اپراتور
                // اپ مشتری (فاز ۱۲+) — سربرگ گفتگو با اطلاعات اپراتور متصل
                'operator' => $this->operatorSummary($order),
                'coffeenet' => $order->coffeenet_id && $order->relationLoaded('coffeenet')
                    ? ($order->coffeenet ? ['id' => $order->coffeenet->id, 'name' => $order->coffeenet->name] : null)
                    : null,
                'accepted_at_fa' => $order->accepted_at ? fa_date($order->accepted_at, 'Y/m/d H:i') : null,
            ],
            'seen_mine' => $this->seenMineIds($order, $viewerId),
        ];
    }

    /** خلاصهٔ اپراتور متصل برای سربرگ چت (نام + کوچک‌ترین بار) */
    protected function operatorSummary(Order $order): ?array
    {
        $operator = $order->operator_id
            ? ($order->relationLoaded('operator') ? $order->operator : $order->operator()->select(['id', 'name', 'family'])->first())
            : null;

        if (! $operator) {
            return null;
        }

        return [
            'id' => $operator->id,
            'name' => trim(($operator->name ?? '').' '.($operator->family ?? '')) ?: 'اپراتور',
        ];
    }

    /**
     * دسته‌بندی پیام سیستمی بر اساس متن — برای نمایش رویدادی در فرانت.
     * «connected» رویداد اتصال اپراتور/کافی‌نت است که با کارت زیبا رندر می‌شود.
     */
    protected function systemKind(string $content): string
    {
        if (mb_strpos($content, 'پذیرفت') !== false || mb_strpos($content, 'پذیرفته شد') !== false) {
            return 'connected';
        }

        if (mb_strpos($content, 'پرداخت') !== false) {
            return 'paid';
        }

        if (mb_strpos($content, 'آغاز') !== false || mb_strpos($content, 'شروع') !== false) {
            return 'started';
        }

        if (mb_strpos($content, 'تحویل') !== false) {
            return 'delivered';
        }

        if (mb_strpos($content, 'لغو') !== false) {
            return 'cancelled';
        }

        return 'info';
    }

    /* ================================================================== */
    /* ابزارها                                                             */
    /* ================================================================== */

    /** اعتبارسنجی و ذخیرهٔ فایل بر اساس قواعد نوع — متا را برمی‌گرداند */
    protected function validateAndStore(Order $order, MessageType $type, UploadedFile $file, ?float $duration = null): array
    {
        $rules = self::FILE_RULES[$type->value];

        if ($file->getSize() > $rules['max'] * 1024) {
            throw ValidationException::withMessages([
                'file' => ['حجم '.$rules['label'].' نباید بیش از '.fa_number($rules['max'] / 1024).' مگابایت باشد.'],
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');

        if (! in_array($extension, explode(',', $rules['mimes']), true)) {
            $fa = collect(explode(',', $rules['mimes']))->map(fn ($m) => '.'.$m)->implode('، ');

            throw ValidationException::withMessages([
                'file' => ['قالب '.$rules['label'].' مجاز نیست؛ فرمت‌های مجاز: '.$fa],
            ]);
        }

        $path = 'chat/'.$order->id.'/'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$extension;

        \App\Support\SecureFile::put('local', $path, $file->getContent());

        $meta = [
            'name' => mb_substr($file->getClientOriginalName() ?: ($rules['label'].'.'.$extension), 0, 180),
            'size' => (int) ($file->getSize() / 1024), // کیلوبایت
            'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
        ];

        if ($duration && $duration > 0 && in_array($type, [MessageType::Audio, MessageType::Video], true)) {
            $meta['duration'] = round($duration, 1);
        }

        return ['path' => $path, 'meta' => $meta];
    }
}
