<?php

namespace App\Services\Support;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Sms\CustomerSmsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;
use Throwable;

/**
 * موتور تیکت پشتیبانی (فاز ۱۰).
 *
 * چرخه: مشتری تیکت می‌سازد (اختیاری با سفارش مرتبط) →
 *   open → پاسخ کارشناس → answered → پاسخ مشتری → customer_reply → … → closed
 * پیام‌ها: متن + پیوست اختیاری (دیسک خصوصی + لینک موقت امضاشده)
 * یادداشت داخلی (is_internal) فقط برای کارشناسان با دسترسی tickets.manage دیده می‌شود.
 */
class TicketService
{
    /** وضعیت‌هایی که مشتری می‌تواند در آن‌ها پاسخ دهد */
    public const CUSTOMER_REPLY_STATUSES = [
        TicketStatus::Open,
        TicketStatus::Answered,
        TicketStatus::CustomerReply,
    ];

    /** قواعد پیوست تیکت — همهٔ انواع مجاز با سقف ۱۵ مگابایت */
    public const FILE_MIMES = 'jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,mp3,mp4,webm';

    public const FILE_MAX_KB = 15360;

    public const MAX_MESSAGE_LENGTH = 3000;

    public const MAX_SUBJECT_LENGTH = 150;

    /** اعتبار لینک پیوست (ساعت) */
    public const FILE_URL_HOURS = 6;

    public function __construct(
        protected NotificationService $notifications,
        protected CustomerSmsService $customerSms,
    ) {}

    /* ================================================================== */
    /* ۱) ساخت                                                            */
    /* ================================================================== */

    /**
     * ثبت تیکت جدید توسط مشتری.
     *
     * @param  array{subject:string, message:string, priority?:string, order_id?:int|null}  $data
     */
    public function create(User $customer, array $data): Ticket
    {
        $subject = trim((string) ($data['subject'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($subject === '' || mb_strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            throw ValidationException::withMessages([
                'subject' => ['موضوع الزامی است و حداکثر '.fa_digits(self::MAX_SUBJECT_LENGTH).' کاراکتر.'],
            ]);
        }

        if ($message === '' || mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw ValidationException::withMessages([
                'message' => ['متن توضیح الزامی است و حداکثر '.fa_digits(self::MAX_MESSAGE_LENGTH).' کاراکتر.'],
            ]);
        }

        $order = null;

        if (! empty($data['order_id'])) {
            $order = \App\Models\Order::query()
                ->where('customer_id', $customer->id)
                ->whereKey((int) $data['order_id'])
                ->first();

            if (! $order) {
                throw ValidationException::withMessages([
                    'order_id' => ['سفارش انتخاب‌شده متعلق به شما نیست.'],
                ]);
            }
        }

        $priority = in_array($data['priority'] ?? '', ['low', 'normal', 'high'], true)
            ? $data['priority']
            : 'normal';

        $ticket = Ticket::create([
            'ticket_number' => $this->generateTicketNumber(),
            'user_id' => $customer->id,
            'order_id' => $order?->id,
            'subject' => $subject,
            'status' => TicketStatus::Open->value,
            'priority' => $priority,
        ]);

        $ticket->messages()->create([
            'sender_id' => $customer->id,
            'message' => $message,
            'is_internal' => false,
            'created_at' => now(),
        ]);

        AuditLogger::log('ticket.created', $ticket, null,
            ['subject' => $subject, 'priority' => $priority, 'order_id' => $order?->id],
            'ثبت تیکت پشتیبانی '.$ticket->ticket_number.' توسط مشتری');

        // اعلان به مدیران کل (فاز ۱۰) + پوش دستگاه در صورت آفلاین بودن (v25)
        $this->notifications->notifyAdminsEvent('ticket.new_admin', [
            'ticket' => $ticket->ticket_number,
            'subject' => $subject,
        ], ['url' => '/admin/tickets/'.$ticket->id, 'ref' => ['ticket_id' => $ticket->id]]);

        // اگر سفارش مرتبط دارد، مدیر کافی‌نتِ همان سفارش هم مطلع شود
        if ($order && $order->coffeenet_id) {
            $this->notifications->notifyCoffeenetManagersEvent(
                (int) $order->coffeenet_id,
                'ticket.new_coffeenet',
                ['order' => $order->order_number, 'subject' => $subject],
                ['url' => '/coffeenet/'.$order->coffeenet_id.'/tickets/'.$ticket->id, 'ref' => ['ticket_id' => $ticket->id]],
            );
        }

        return $ticket;
    }

    /* ================================================================== */
    /* ۲) پاسخ و گذارها                                                     */
    /* ================================================================== */

    /**
     * ارسال پیام روی تیکت.
     *
     * @param  bool  $internal  یادداشت داخلی (نیاز به tickets.manage)
     * @param  bool|null  $fromCustomer  فرستنده مشتری است؟ (null → تشخیص از نقش)
     *                                     API مشتری true و پنل‌ها false پاس می‌دهند تا
     *                                     کاربران دو-نقشه به‌درستی دسته‌بندی شوند.
     */
    public function reply(Ticket $ticket, User $sender, string $message, ?UploadedFile $file = null, bool $internal = false, ?bool $fromCustomer = null): TicketMessage
    {
        $message = trim($message);

        $fromCustomer ??= ! $this->isStaff($sender);

        if ($internal && $fromCustomer) {
            throw ValidationException::withMessages([
                'internal' => ['یادداشت داخلی فقط برای کارشناسان مجاز است.'],
            ]);
        }

        // مشتری فقط در وضعیت‌های باز پاسخ می‌دهد (پیام مشتری روی تیکت بسته = بازگشایی)
        if ($fromCustomer && $ticket->status === TicketStatus::Closed) {
            $this->transition($ticket, TicketStatus::CustomerReply, $sender, 'بازگشایی تیکت با پیام مشتری');
        }

        $attachment = $file ? $this->storeAttachment($ticket, $file) : null;

        if ($message === '' && ! $attachment) {
            throw ValidationException::withMessages([
                'message' => ['متن پیام یا پیوست الزامی است.'],
            ]);
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw ValidationException::withMessages([
                'message' => ['متن پیام نباید بیش از '.fa_digits(self::MAX_MESSAGE_LENGTH).' کاراکتر باشد.'],
            ]);
        }

        $msg = $ticket->messages()->create([
            'sender_id' => $sender->id,
            'message' => $message ?: '(پیوست)',
            'attachments' => $attachment ? [$attachment] : null,
            'is_internal' => $internal,
            'created_at' => now(),
        ]);

        // گذار وضعیت + اعلان‌ها فقط برای پیام‌های عمومی
        if (! $internal) {
            if (! $fromCustomer) {
                $this->transition($ticket, TicketStatus::Answered, $sender, 'پاسخ کارشناس');

                // پیامک پاسخ پشتیبانی به مشتری (درخواست بازخوردی ۶-۶ — fail-safe)
                // v25: با تنظیم sms.notify.ticket_reply قابل قطع/وصل است
                try {
                    if (app(\App\Services\Sms\NotifySmsService::class)->ticketReplyEnabled()) {
                        $this->customerSms->ticketReplied($ticket);
                    }
                } catch (\Throwable) {
                    // پیامک پاسخ تیکت را نمی‌شکند
                }

                if ((int) $ticket->user_id !== (int) $sender->id) {
                    $this->notifications->tryNotifyEvent(
                        $ticket->user,
                        'ticket.answered_customer',
                        ['ticket' => $ticket->ticket_number],
                        ['url' => '/app/support/'.$ticket->id, 'ref' => ['ticket_id' => $ticket->id]],
                    );
                }
            } else {
                $this->transition($ticket, TicketStatus::CustomerReply, $sender, 'پاسخ مشتری');

                $url = $ticket->assigned_to
                    ? '/admin/tickets/'.$ticket->id
                    : '/admin/tickets/'.$ticket->id;

                $this->notifications->notifyAdminsEvent('ticket.customer_replied', [
                    'ticket' => $ticket->ticket_number,
                ], ['url' => $url, 'ref' => ['ticket_id' => $ticket->id]]);

                if ($ticket->assigned_to && (int) $ticket->assigned_to !== (int) $sender->id) {
                    $this->notifications->tryNotifyEvent(
                        User::find($ticket->assigned_to),
                        'ticket.customer_replied',
                        ['ticket' => $ticket->ticket_number],
                        ['url' => $url, 'ref' => ['ticket_id' => $ticket->id]],
                    );
                }

                if ($ticket->order?->coffeenet_id) {
                    $this->notifications->notifyCoffeenetManagersEvent(
                        (int) $ticket->order->coffeenet_id,
                        'ticket.customer_replied',
                        ['ticket' => $ticket->ticket_number],
                        ['url' => '/coffeenet/'.$ticket->order->coffeenet_id.'/tickets/'.$ticket->id, 'ref' => ['ticket_id' => $ticket->id]],
                    );
                }
            }
        }

        return $msg;
    }

    /** بستن تیکت (کارشناس یا خود مشتری) */
    public function close(Ticket $ticket, ?User $actor = null): Ticket
    {
        if ($ticket->status === TicketStatus::Closed) {
            return $ticket;
        }

        $this->transition($ticket, TicketStatus::Closed, $actor, 'بستن تیکت'.($actor ? ' توسط '.$actor->full_name : ''));

        $this->notifications->tryNotifyEvent(
            $ticket->user,
            'ticket.closed_customer',
            ['ticket' => $ticket->ticket_number],
            ['url' => '/app/support/'.$ticket->id, 'ref' => ['ticket_id' => $ticket->id]],
        );

        return $ticket;
    }

    /** بازگشایی (فقط کارشناس) */
    public function reopen(Ticket $ticket, User $actor): Ticket
    {
        if ($ticket->status !== TicketStatus::Closed) {
            return $ticket;
        }

        return $this->transition($ticket, TicketStatus::Open, $actor, 'بازگشایی توسط '.$actor->full_name);
    }

    /** ارجاع به کارشناس (ادمین) */
    public function assign(Ticket $ticket, int $adminId, ?User $actor = null): Ticket
    {
        $admin = User::find($adminId);

        if (! $admin || ! $admin->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'user_id' => ['کارشناس انتخاب‌شده معتبر نیست.'],
            ]);
        }

        $ticket->update(['assigned_to' => $admin->id]);
        $ticket->refresh();

        AuditLogger::log('ticket.assigned', $ticket, [], ['assigned_to' => $admin->id],
            'ارجاع تیکت '.$ticket->ticket_number.' به '.$admin->full_name);

        $this->notifications->tryNotifyEvent(
            $admin,
            'ticket.assigned_staff',
            ['ticket' => $ticket->ticket_number, 'subject' => $ticket->subject],
            ['url' => '/admin/tickets/'.$ticket->id, 'ref' => ['ticket_id' => $ticket->id]],
        );

        return $ticket;
    }

    /** تغییر اولویت (کارشناس) */
    public function setPriority(Ticket $ticket, string $priority, ?User $actor = null): Ticket
    {
        if (! in_array($priority, ['low', 'normal', 'high'], true)) {
            throw ValidationException::withMessages([
                'priority' => ['اولویت نامعتبر است.'],
            ]);
        }

        $old = $ticket->priority;
        $ticket->update(['priority' => $priority]);

        AuditLogger::log('ticket.priority', $ticket, ['priority' => $old], ['priority' => $priority],
            'تغییر اولویت تیکت '.$ticket->ticket_number);

        return $ticket->refresh();
    }

    /* ================================================================== */
    /* ۳) پیوست‌ها                                                          */
    /* ================================================================== */

    /** URL موقت امضاشدهٔ پیوست پیام */
    public function fileUrl(TicketMessage $message): ?string
    {
        $list = (array) ($message->attachments ?? []);

        if (empty($list) || empty($list[0]['path'])) {
            return null;
        }

        try {
            return URL::temporarySignedRoute(
                'files.ticket',
                now()->addHours(self::FILE_URL_HOURS),
                ['message' => $message->id],
            );
        } catch (Throwable) {
            return null;
        }
    }

    /* ================================================================== */
    /* ۴) سریال‌سازی                                                        */
    /* ================================================================== */

    /** ساختار JSON پیام (برای هر دو سمت) — یادداشت داخلی فقط با مجاز manage */
    public function serializeMessage(TicketMessage $message, bool $canSeeInternal, ?int $viewerId = null): array
    {
        if ($message->is_internal && ! $canSeeInternal) {
            return ['id' => $message->id, 'internal_hidden' => true];
        }

        $sender = $message->sender;

        $payload = [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender_name' => $sender ? $sender->full_name : 'سیستم',
            'message' => $message->message,
            'internal' => (bool) $message->is_internal,
            'mine' => $viewerId && $message->sender_id && (int) $viewerId === (int) $message->sender_id,
            'time_fa' => fa_date($message->created_at, 'H:i'),
            'date_fa' => fa_date($message->created_at, 'Y/m/d'),
        ];

        if (! empty($message->attachments)) {
            $file = (array) $message->attachments[0];

            $payload['file'] = [
                'url' => $this->fileUrl($message),
                'name' => $file['name'] ?? 'پیوست',
                'size' => (int) ($file['size'] ?? 0),
                'size_fa' => fa_number(max(1, (int) ($file['size'] ?? 1))).' کیلوبایت',
                'mime' => $file['mime'] ?? null,
                'is_image' => str_starts_with((string) ($file['mime'] ?? ''), 'image/'),
            ];
        }

        return $payload;
    }

    /** ساختار JSON تیکت (ردیف لیست) */
    public function serializeTicket(Ticket $ticket, bool $canSeeInternal = false): array
    {
        $lastMessage = $ticket->messages()->latest('id')->first();
        $lastInternalHidden = $lastMessage?->is_internal && ! $canSeeInternal;

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'priority' => $ticket->priority,
            'priority_label' => self::priorityLabel($ticket->priority),
            'order_id' => $ticket->order_id,
            'order_number' => $ticket->order?->order_number,
            'customer' => $ticket->user?->full_name ?? '—',
            'customer_mobile' => $ticket->user?->mobile,
            'assigned_to' => $ticket->assigned_to,
            'assigned_name' => $ticket->assignedTo?->full_name,
            'messages_count' => (int) $ticket->messages()->count(),
            'last_message' => $lastInternalHidden
                ? '(یادداشت داخلی)'
                : ($lastMessage?->message ? mb_substr($lastMessage->message, 0, 90) : null),
            'last_message_at' => $lastMessage?->created_at ? fa_date($lastMessage->created_at, 'Y/m/d H:i') : null,
            'last_message_ts' => optional($lastMessage?->created_at)->timestamp,
            'created_at' => fa_date($ticket->created_at, 'Y/m/d H:i'),
        ];
    }

    public static function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'کم',
            'high' => 'زیاد',
            default => 'معمولی',
        };
    }

    /* ================================================================== */
    /* ابزارهای داخلی                                                       */
    /* ================================================================== */

    /** آیا فرستنده کارشناس است (نقش غیر-مشتری)؟ */
    public function isStaff(User $user): bool
    {
        return $user->hasAnyRole('super_admin', 'coffeenet_manager', 'operator', 'org_manager');
    }

    protected function transition(Ticket $ticket, TicketStatus $to, ?User $actor, string $note): Ticket
    {
        $from = $ticket->status;

        $ticket->update(['status' => $to->value]);
        $ticket->refresh();

        if ($from !== $to) {
            AuditLogger::log(
                'ticket.'.match ($to) {
                    TicketStatus::Closed => 'closed',
                    TicketStatus::Open => 'reopened',
                    TicketStatus::Answered => 'answered',
                    TicketStatus::CustomerReply => 'customer_reply',
                },
                $ticket,
                ['status' => $from->value],
                ['status' => $to->value],
                $note.' — '.$ticket->ticket_number,
            );
        }

        return $ticket;
    }

    /** شماره تیکت یکتا: TK{jalali yymmdd}-XXXX */
    protected function generateTicketNumber(): string
    {
        $prefix = 'TK'.Jalalian::now()->format('ymd');

        for ($i = 0; $i < 8; $i++) {
            $number = $prefix.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            if (! Ticket::query()->where('ticket_number', $number)->exists()) {
                return $number;
            }
        }

        return $prefix.'-'.str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
    }

    /** ذخیرهٔ پیوست روی دیسک خصوصی + متا */
    protected function storeAttachment(Ticket $ticket, UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['فایل پیوست نامعتبر است.'],
            ]);
        }

        if ($file->getSize() > self::FILE_MAX_KB * 1024) {
            throw ValidationException::withMessages([
                'file' => ['حجم پیوست نباید بیش از '.fa_number(self::FILE_MAX_KB / 1024).' مگابایت باشد.'],
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');

        if (! in_array($extension, explode(',', self::FILE_MIMES), true)) {
            $fa = collect(explode(',', self::FILE_MIMES))->map(fn ($m) => '.'.$m)->implode('، ');

            throw ValidationException::withMessages([
                'file' => ['فرمت پیوست مجاز نیست؛ فرمت‌های مجاز: '.$fa],
            ]);
        }

        $path = 'tickets/'.$ticket->id.'/'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$extension;

        \App\Support\SecureFile::put('local', $path, $file->getContent());

        return [
            'name' => mb_substr($file->getClientOriginalName() ?: ('پیوست.'.$extension), 0, 180),
            'path' => $path,
            'size' => (int) ($file->getSize() / 1024), // کیلوبایت
            'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
        ];
    }
}
