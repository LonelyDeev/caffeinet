<?php

namespace App\Services\Notifications;

use App\Enums\StaffPosition;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\Push\PushManager;
use App\Services\Realtime\PusherService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * اعلان‌های درون‌برنامه‌ای (فاز ۱۰) — ساخته‌شده روی جدول استاندارد notifications.
 *
 * قواعد:
 *  - همهٔ متدهای «فراخوانی از جریان اصلی» (tryNotify و مشابه) هرگز خطا را بالا نمی‌برند.
 *  - data هر رکورد: {title, body, url?, ref?} — url نسبی است و فرانت پارامتر گیت‌وی را خودش اضافه می‌کند.
 */
class NotificationService
{
    /** دامنهٔ مجاز type (برای فیلتر لیست) */
    public const TYPES = ['order', 'ticket', 'withdrawal', 'settlement', 'system'];

    /* ================================================================== */
    /* ۱) ساخت                                                            */
    /* ================================================================== */

    /**
     * ارسال اعلان رویدادی (v25) — عنوان/متن از رجیستری NotificationTemplate
     * (هر رویداد و هر بخش متن و عنوان اختصاصی خودش را دارد).
     *
     * علاوه بر اعلان درون‌برنامه‌ای، اگر گیرنده آفلاین باشد (برنامه بسته/غیرآنلاین)
     * پوش دستگاه با سرویس فعال (v26: پیش‌فرض/پوشر/فایربیس) هم با همین
     * عنوان/متن ارسال می‌شود.
     *
     * @param  array  $vars  متغیرهای قالب: ['ticket' => 'TK-...', ...]
     * @param  array  $data  دادهٔ اضافهٔ رکورد: url / ref / ...
     */
    public function notifyEvent(User|iterable|null $users, string $event, array $vars = [], array $data = []): void
    {
        $composed = NotificationTemplate::compose($event, $vars);

        if (! $composed) {
            return; // رویداد ناشناخته — بی‌صدا رد می‌شود
        }

        $data['event'] = $event;

        $this->notify($users, $composed['type'], $composed['title'], $composed['body'], $data);
    }

    /** ارسال امن رویدادی */
    public function tryNotifyEvent(User|iterable|null $users, string $event, array $vars = [], array $data = []): void
    {
        try {
            $this->notifyEvent($users, $event, $vars, $data);
        } catch (Throwable) {
            // اعلان هرگز نباید عملیات اصلی را متوقف کند
        }
    }

    /** ارسال امن اعلان — خطاها فقط لاگ می‌شوند (برای فراخوانی از جریان‌های اصلی) */
    public function tryNotify(User|iterable|null $users, string $type, string $title, string $body, array $data = []): void
    {
        try {
            $this->notify($users, $type, $title, $body, $data);
        } catch (Throwable) {
            // اعلان هرگز نباید عملیات اصلی را متوقف کند
        }
    }

    /**
     * ارسال اعلان به یک یا چند کاربر.
     *
     * @param  User|iterable|null  $users
     */
    public function notify(User|iterable|null $users, string $type, string $title, string $body, array $data = []): void
    {
        if ($users === null) {
            return;
        }

        $users = $users instanceof User ? collect([$users]) : collect($users);

        $rows = $users
            ->filter(fn ($u) => $u instanceof User && $u->exists)
            ->unique('id')
            ->map(fn (User $u) => [
                'id' => (string) str()->uuid(),
                'type' => 'App\\Notifications\\PanelNotification',
                'notifiable_type' => $u->getMorphClass(),
                'notifiable_id' => $u->id,
                'data' => json_encode([
                    'type' => in_array($type, self::TYPES, true) ? $type : 'system',
                    'title' => mb_substr($title, 0, 150),
                    'body' => mb_substr($body, 0, 500),
                ] + $data, JSON_UNESCAPED_UNICODE),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows) {
            DB::table('notifications')->insert($rows);

            // Realtime (فاز ۱۳): بیدارباش زنگ اعلان کاربران از طریق پوشر
            try {
                app(PusherService::class)->notifyUsers(
                    array_column($rows, 'notifiable_id'),
                    in_array($type, self::TYPES, true) ? $type : 'system',
                );
            } catch (Throwable) {
                // پوشر هرگز نباید ساخت اعلان را متوقف کند
            }

            // نوتیف دستگاه (v26): فقط برای گیرندگانِ آفلاین — پیام با همین
            // عنوان/متن به گوشی/ویندوز می‌رسد تا وقتی برنامه بسته است.
            // سرویس فعال (پیش‌فرض/پوشر/فایربیس) از تنظیمات انتخاب می‌شود.
            try {
                app(PushManager::class)->notifyOfflineUsers(
                    $users,
                    mb_substr($title, 0, 100),
                    mb_substr($body, 0, 250),
                    ['url' => $data['url'] ?? null, 'event' => $data['event'] ?? null, 'tag' => 'cn-'.$type],
                );
            } catch (Throwable) {
                // پوش هرگز نباید ساخت اعلان را متوقف کند
            }
        }
    }

    /** اعلان به همهٔ مدیران کل فعال */
    public function notifyAdmins(string $type, string $title, string $body, array $data = []): void
    {
        $this->tryNotify(
            User::query()->role('super_admin')->where('is_active', true)->get(),
            $type, $title, $body, $data,
        );
    }

    /** اعلان رویدادی به همهٔ مدیران کل فعال (v25) */
    public function notifyAdminsEvent(string $event, array $vars = [], array $data = []): void
    {
        $this->tryNotifyEvent(
            User::query()->role('super_admin')->where('is_active', true)->get(),
            $event, $vars, $data,
        );
    }

    /** اعلان رویدادی به مدیر فعال یک کافی‌نت (v25) */
    public function notifyCoffeenetManagersEvent(int $coffeenetId, string $event, array $vars = [], array $data = []): void
    {
        $users = StaffAssignment::query()
            ->where('coffeenet_id', $coffeenetId)
            ->where('position', StaffPosition::Manager->value)
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->map(fn (StaffAssignment $a) => $a->user)
            ->filter();

        $this->tryNotifyEvent($users, $event, $vars, $data);
    }

    /** اعلان به مدیر فعال یک کافی‌نت (بر اساس انتصاب مدیر) */
    public function notifyCoffeenetManagers(int $coffeenetId, string $type, string $title, string $body, array $data = []): void
    {
        $users = StaffAssignment::query()
            ->where('coffeenet_id', $coffeenetId)
            ->where('position', StaffPosition::Manager->value)
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->map(fn (StaffAssignment $a) => $a->user)
            ->filter();

        $this->tryNotify($users, $type, $title, $body, $data);
    }

    /** اعلان به کاربر اپراتور (انتساب فعال) */
    public function notifyOperator(int $userId, string $type, string $title, string $body, array $data = []): void
    {
        $this->tryNotify(User::find($userId), $type, $title, $body, $data);
    }

    /** اعلان به مالک یک سازمان */
    public function notifyOrgOwner(int $organizationId, string $type, string $title, string $body, array $data = []): void
    {
        $owner = \App\Models\Organization::query()->whereKey($organizationId)->value('owner_id');

        if ($owner) {
            $this->tryNotify(User::find($owner), $type, $title, $body, $data);
        }
    }

    /* ================================================================== */
    /* ۲) خواندن                                                          */
    /* ================================================================== */

    /** شمار اعلان‌های خوانده‌نشدهٔ کاربر (بج زنگ) */
    public function badge(User $user): int
    {
        return (int) $user->unreadNotifications()->count();
    }

    /**
     * لیست اعلان‌های کاربر.
     *
     * @param  string|null  $type  فیلتر type (از TYPES)
     * @param  bool|null  $unreadOnly  فقط خوانده‌نشده‌ها
     */
    public function list(User $user, ?string $type = null, ?bool $unreadOnly = false, int $perPage = 15): LengthAwarePaginator
    {
        $query = $user->notifications()->orderByDesc('created_at');

        if ($type && in_array($type, self::TYPES, true)) {
            $query->where('data->type', $type);
        }

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->paginate($perPage)->through(fn (DatabaseNotification $n) => $this->format($n));
    }

    /** آخرین N اعلان برای زنگ/دراپ‌داون */
    public function latest(User $user, int $limit = 8): Collection
    {
        return $user->notifications()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /* ================================================================== */
    /* ۳) خوانده‌شده                                                        */
    /* ================================================================== */

    /** علامت‌گذاری یک اعلان (یا همه) خوانده‌شده — تعداد ردیف‌های به‌روزشده را برمی‌گرداند */
    public function markRead(User $user, ?string $id = null): int
    {
        $query = $user->unreadNotifications();

        if ($id) {
            $query->where('id', $id);
        }

        return (int) $query->update(['read_at' => now()]);
    }

    /* ================================================================== */
    /* ۴) سریال‌سازی                                                        */
    /* ================================================================== */

    /** ساختار JSON یک اعلان برای فرانت (هر دو سمت پنل و اپ) */
    public function format(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;

        $created = $notification->created_at;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? 'system',
            'event' => $data['event'] ?? null,
            'title' => $data['title'] ?? 'اعلان',
            'body' => $data['body'] ?? '',
            'url' => $data['url'] ?? null,
            'ref' => $data['ref'] ?? null,
            'read' => $notification->read_at !== null,
            'time_fa' => $created ? fa_date($created, 'H:i') : null,
            'date_fa' => $created ? fa_date($created, 'Y/m/d') : null,
            'ts' => optional($created)->timestamp,
        ];
    }
}
