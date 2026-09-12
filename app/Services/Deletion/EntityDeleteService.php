<?php

namespace App\Services\Deletion;

use App\Models\Coffeenet;
use App\Models\Order;
use App\Models\OrderBroadcast;
use App\Models\OrderFile;
use App\Models\OrderRating;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * موتور حذف نرم‌افزاری/دائم (v28) — مشترک بین بخش‌های پنل مدیریت کل.
 *
 * ساختار:
 *  • info()        خلاصهٔ وابستگی‌ها برای پیام تأیید (هشدارهای حذف)
 *  • softDelete()  حذف نرم (رفتن به «حذف‌شده‌ها») + آبشار موارد وابسته
 *  • trashed()     لیست حذف‌شده‌ها
 *  • restore()     بازگردانی (+ بازگردانی وابسته‌های هم‌زمان حذف‌شده)
 *  • purge()       حذف دائم — رکوردها + فایل‌های روی دیسک پاک می‌شوند
 *
 * قواعد آبشار (سفارش صریح مالک):
 *  • کارمندِ «مدیر کافی‌نت» ← حذف او ⇒ حذف کافی‌نت (و بالعکس)
 *  • کافی‌نت ← حذف ⇒ حذف کاربر مدیر + سفارش‌ها/گفتگوهایش
 *  • سازمان ← حذف ⇒ کافی‌نت‌های زیرمجموعه «مستقل» می‌شوند (حذف نمی‌شوند)
 *  • سفارش ← حذف ⇒ تصاویر/مدارک و گفتگوی وابسته هم پنهان (و در حذف دائم پاک) می‌شوند
 */
class EntityDeleteService
{
    public const SECTIONS = ['customers', 'operators', 'admins', 'coffeenets', 'organizations', 'tickets', 'orders'];

    public const SECTION_LABELS = [
        'customers' => 'مشتری',
        'operators' => 'کارمند / اپراتور',
        'admins' => 'مدیر سیستم',
        'coffeenets' => 'کافی‌نت',
        'organizations' => 'سازمان',
        'tickets' => 'تیکت',
        'orders' => 'سفارش',
    ];

    /* ================================================================== */
    /* ۱) رزولو                                                           */
    /* ================================================================== */

    /** یافتن موجودیت (شامل حذف‌شده‌ها) بر اساس بخش */
    public function resolve(string $section, int $id): ?object
    {
        if (! in_array($section, self::SECTIONS, true)) {
            return null;
        }

        return match ($section) {
            'customers', 'operators', 'admins' => User::withTrashed()->find($id),
            'coffeenets' => Coffeenet::withTrashed()->find($id),
            'organizations' => Organization::withTrashed()->find($id),
            'tickets' => Ticket::withTrashed()->find($id),
            'orders' => Order::withTrashed()->find($id),
            default => null,
        };
    }

    /* ================================================================== */
    /* ۲) خلاصهٔ وابستگی‌ها (پیام تأیید)                                    */
    /* ================================================================== */

    /**
     * خلاصه برای دیالوگ تأیید حذف.
     *
     * @return array{label:string, warnings:array<int,string>, deletable:bool, reason:string|null}
     */
    public function info(string $section, int $id, User $actor): array
    {
        $entity = $this->resolve($section, $id);
        abort_unless((bool) $entity, 404, 'رکورد یافت نشد.');

        if ($entity instanceof User) {
            return $this->userInfo($section, $entity, $actor);
        }

        if ($entity instanceof Coffeenet) {
            return $this->coffeenetInfo($entity);
        }

        if ($entity instanceof Organization) {
            return $this->organizationInfo($entity);
        }

        if ($entity instanceof Ticket) {
            return $this->ticketInfo($entity);
        }

        return $this->orderInfo($entity);
    }

    protected function userInfo(string $section, User $user, User $actor): array
    {
        $warnings = [];

        if ($section === 'admins' && $user->hasRole('super_admin')) {
            if ((int) $user->id === (int) $actor->id) {
                return ['label' => $user->full_name, 'warnings' => [], 'deletable' => false,
                    'reason' => 'حساب خودتان قابل حذف نیست.'];
            }

            $otherSupers = User::role('super_admin')->where('is_active', true)
                ->whereKeyNot($user->id)->count();
            if ($otherSupers === 0) {
                return ['label' => $user->full_name, 'warnings' => [], 'deletable' => false,
                    'reason' => 'آخرین مدیر کل سیستم قابل حذف نیست.'];
            }
        }

        // مدیر کافی‌نت؟ ⇒ کافی‌نت‌ها هم حذف می‌شوند (قاعدهٔ صریح مالک)
        $managed = Coffeenet::query()
            ->whereHas('staffAssignments', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('position', 'manager')
                ->where('is_active', true))
            ->pluck('name');

        foreach ($managed as $netName) {
            $warnings[] = 'این کارمند «مدیر کافی‌net '.$netName.'» است؛ با حذف او، کافی‌net «'.$netName.'» و سفارش‌ها/گفتگوهایش هم حذف می‌شود.';
        }

        $assignments = StaffAssignment::query()->where('user_id', $user->id)->where('is_active', true)->count();
        if ($assignments > 0 && $managed->isEmpty()) {
            $warnings[] = 'عضویت‌های این کاربر در '.fa_digits((string) $assignments).' کافی‌net حذف می‌شود.';
        }

        $orders = Order::query()->where('customer_id', $user->id)->count();
        if ($orders > 0) {
            $warnings[] = fa_digits((string) $orders).' سفارش و گفتگوی وابستهٔ این مشتری حذف می‌شود.';
        }

        $tickets = Ticket::query()->where('user_id', $user->id)->count();
        if ($tickets > 0) {
            $warnings[] = fa_digits((string) $tickets).' تیکت پشتیبانی این کاربر حذف می‌شود.';
        }

        if ($warnings === []) {
            $warnings[] = 'این رکورد به لیست «حذف‌شده‌ها» منتقل می‌شود و تا حذف دائم قابل بازگردانی است.';
        }

        return ['label' => $user->full_name.($user->mobile ? ' — '.$user->mobile : ''), 'warnings' => $warnings,
            'deletable' => true, 'reason' => null];
    }

    protected function coffeenetInfo(Coffeenet $coffeenet): array
    {
        $warnings = [];
        $manager = $coffeenet->managerUser();

        if ($manager) {
            $warnings[] = 'کاربر مدیر این کافی‌net («'.$manager->full_name.'») هم حذف می‌شود و دیگر نمی‌تواند وارد پنل کافی‌net شود.';
        }

        $orders = Order::query()->where('coffeenet_id', $coffeenet->id)->count();
        if ($orders > 0) {
            $warnings[] = fa_digits((string) $orders).' سفارش این کافی‌net همراه تصاویر/مدارک و گفتگوهایشان حذف می‌شود.';
        }

        $staff = StaffAssignment::query()->where('coffeenet_id', $coffeenet->id)->where('is_active', true)->count();
        if ($staff > 0) {
            $warnings[] = 'عضویت '.fa_digits((string) $staff).' کارمند/اپراتور این کافی‌net حذف می‌شود.';
        }

        if ($warnings === []) {
            $warnings[] = 'این رکورد به لیست «حذف‌شده‌ها» منتقل می‌شود و تا حذف دائم قابل بازگردانی است.';
        }

        return ['label' => $coffeenet->name, 'warnings' => $warnings, 'deletable' => true, 'reason' => null];
    }

    protected function organizationInfo(Organization $organization): array
    {
        $nets = Coffeenet::query()->where('organization_id', $organization->id)->pluck('name');
        $warnings = [];

        if ($nets->isNotEmpty()) {
            $warnings[] = 'کافی‌net‌های زیرمجموعه ('.fa_digits((string) $nets->count()).' مورد'.($nets->count() <= 3 ? ': «'.$nets->implode('», «').'»' : '').') حذف نمی‌شوند و به‌صورت «مستقل» ادامه می‌دهند.';
        } else {
            $warnings[] = 'این سازمان کافی‌net زیرمجموعه‌ای ندارد.';
        }

        return ['label' => $organization->name, 'warnings' => $warnings, 'deletable' => true, 'reason' => null];
    }

    protected function ticketInfo(Ticket $ticket): array
    {
        $messages = TicketMessage::query()->where('ticket_id', $ticket->id)->count();
        $attachments = TicketMessage::query()->where('ticket_id', $ticket->id)
            ->whereNotNull('attachments')->count();

        $warnings = ['گفتگوی تیکت ('.fa_digits((string) $messages).' پیام) به‌همراه پیوست‌ها حذف می‌شود.'];

        if ($attachments > 0) {
            $warnings[] = fa_digits((string) $attachments).' فایل پیوست‌شده در گفتگو حذف می‌شود.';
        }

        return ['label' => $ticket->ticket_number.' — '.($ticket->subject ?? ''), 'warnings' => $warnings,
            'deletable' => true, 'reason' => null];
    }

    protected function orderInfo(Order $order): array
    {
        $files = OrderFile::query()->where('order_id', $order->id)->count();
        $messages = \App\Models\Message::query()
            ->whereHas('conversation', fn ($q) => $q->where('order_id', $order->id))
            ->count();

        $warnings = ['گفتگوی این سفارش ('.fa_digits((string) $messages).' پیام) حذف می‌شود.'];

        if ($files > 0) {
            $warnings[] = fa_digits((string) $files).' فایل/تصویر پیوست سفارش حذف می‌شود.';
        }

        $customer = $order->customer?->full_name;
        $label = $order->order_number
            .($order->service?->name ? ' — '.$order->service->name : '')
            .($customer ? ' — '.$customer : '');

        return ['label' => $label, 'warnings' => $warnings, 'deletable' => true, 'reason' => null];
    }

    /* ================================================================== */
    /* ۳) حذف نرم                                                          */
    /* ================================================================== */

    /** @return array{message:string} */
    public function softDelete(string $section, int $id, User $actor): array
    {
        $entity = $this->resolve($section, $id);
        abort_unless((bool) $entity, 404, 'رکورد یافت نشد.');

        $info = $this->info($section, $id, $actor);

        if (! $info['deletable']) {
            throw ValidationException::withMessages(['delete' => [$info['reason'] ?? 'حذف این رکورد مجاز نیست.']]);
        }

        $message = DB::transaction(function () use ($section, $entity, $actor) {
            return match (true) {
                $entity instanceof User => $this->deleteUser($entity, $actor),
                $entity instanceof Coffeenet => $this->deleteCoffeenet($entity, $actor),
                $entity instanceof Organization => $this->deleteOrganization($entity, $actor),
                $entity instanceof Ticket => $this->deleteTicket($entity, $actor),
                default => $this->deleteOrder($entity, $actor),
            };
        });

        AuditLogger::log('trash.soft_delete', $entity, null, ['section' => $section],
            'حذف نرمِ '.(self::SECTION_LABELS[$section] ?? $section).' «'.$info['label'].'»');

        return ['message' => $message];
    }

    protected function deleteUser(User $user, User $actor, ?int $skipCoffeenetId = null): string
    {
        if ($user->trashed()) {
            return 'این کاربر از قبل در حذف‌شده‌ها است.';
        }

        // قطع دسترسی‌ها: توکن‌های API و توکن‌های نوتیف دستگاه
        try {
            $user->tokens()->delete();
        } catch (Throwable) {}
        $user->pushTokens()->delete();

        // کافی‌net‌هایی که مدیرِشان است ⇒ آبشار حذف (قاعدهٔ بالعکس)
        $nets = Coffeenet::query()
            ->whereKeyNot($skipCoffeenetId ?? 0)
            ->whereHas('staffAssignments', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('position', 'manager')
                ->where('is_active', true))
            ->get();

        foreach ($nets as $net) {
            $this->deleteCoffeenet($net, $actor, skipManagerId: (int) $user->id);
        }

        // عضویت‌های کاری (کارمند/اپراتور بودن) حذف نرم می‌شوند
        // (برای بازگردانی بعدی حفظ می‌شوند)
        $user->staffAssignments()->delete();

        // سفارش‌ها و تیکت‌های مشتری ⇒ حذف نرم
        $orders = Order::query()->where('customer_id', $user->id)->whereNull('deleted_at')->get();
        foreach ($orders as $order) {
            $this->deleteOrder($order, $actor);
        }

        $tickets = Ticket::query()->where('user_id', $user->id)->whereNull('deleted_at')->get();
        foreach ($tickets as $ticket) {
            $this->deleteTicket($ticket, $actor);
        }

        $user->delete();

        $parts = ['کاربر «'.$user->full_name.'» به حذف‌شده‌ها منتقل شد'];
        if ($nets->isNotEmpty()) {
            $parts[] = fa_digits((string) $nets->count()).' کافی‌net مدیرِ او هم حذف شد';
        }
        if ($orders->isNotEmpty()) {
            $parts[] = fa_digits((string) $orders->count()).' سفارش وابسته';
        }
        if ($tickets->isNotEmpty()) {
            $parts[] = fa_digits((string) $tickets->count()).' تیکت وابسته';
        }

        return implode(' + ', $parts).'.';
    }

    protected function deleteCoffeenet(Coffeenet $coffeenet, User $actor, ?int $skipManagerId = null): string
    {
        if ($coffeenet->trashed()) {
            return 'این کافی‌net از قبل در حذف‌شده‌ها است.';
        }

        // کاربر مدیر ⇒ حذف نرم (قاعدهٔ بالعکس) — مگر این‌که خودش مبدأ حذف باشد
        $manager = $coffeenet->managerUser();
        $managerDeleted = false;

        if ($manager && (int) $manager->id !== ($skipManagerId ?? 0) && ! $manager->trashed()) {
            $this->deleteUser($manager, $actor, skipCoffeenetId: (int) $coffeenet->id);
            $managerDeleted = true;
        }

        // عضویت کارکنان — حذف نرم (برای بازگردانی)
        $staffCount = StaffAssignment::query()->where('coffeenet_id', $coffeenet->id)->whereNull('deleted_at')->count();
        StaffAssignment::query()->where('coffeenet_id', $coffeenet->id)->whereNull('deleted_at')->delete();

        // سفارش‌های کافی‌net ⇒ حذف نرم
        $orders = Order::query()->where('coffeenet_id', $coffeenet->id)->whereNull('deleted_at')->get();
        foreach ($orders as $order) {
            $this->deleteOrder($order, $actor);
        }

        $coffeenet->delete();

        $parts = ['کافی‌net «'.$coffeenet->name.'» به حذف‌شده‌ها منتقل شد'];
        if ($managerDeleted) {
            $parts[] = 'کاربر مدیرش حذف شد';
        }
        if ($orders->isNotEmpty()) {
            $parts[] = fa_digits((string) $orders->count()).' سفارش/گفتگو وابسته';
        }
        if ($staffCount > 0) {
            $parts[] = 'عضویت '.fa_digits((string) $staffCount).' کارمند';
        }

        return implode(' + ', $parts).'.';
    }

    protected function deleteOrganization(Organization $organization, User $actor): string
    {
        if ($organization->trashed()) {
            return 'این سازمان از قبل در حذف‌شده‌ها است.';
        }

        // کافی‌net‌های زیرمجموعه مستقل می‌شوند (حذف نمی‌شوند) — قاعدهٔ صریح مالک
        $nets = Coffeenet::query()->where('organization_id', $organization->id)->pluck('name');
        Coffeenet::query()->where('organization_id', $organization->id)->update(['organization_id' => null]);

        $organization->delete();

        return 'سازمان «'.$organization->name.'» حذف شد'
            .($nets->isNotEmpty()
                ? '؛ '.fa_digits((string) $nets->count()).' کافی‌net زیرمجموعه ('.(($nets->count() <= 3) ? '«'.$nets->implode('», «').'» — ' : '').'هم‌اکنون مستقل‌اند).'
                : '.');
    }

    protected function deleteTicket(Ticket $ticket, User $actor): string
    {
        if (! $ticket->trashed()) {
            $ticket->delete();
            $this->purgeNotifications('ticket_id', (int) $ticket->id);
        }

        return 'تیکت «'.$ticket->ticket_number.'» به حذف‌شده‌ها منتقل شد.';
    }

    protected function deleteOrder(Order $order, User $actor): string
    {
        if (! $order->trashed()) {
            $order->delete();
            $this->purgeNotifications('order_id', (int) $order->id);
        }

        return 'سفارش «'.$order->order_number.'» به حذف‌شده‌ها منتقل شد.';
    }

    /* ================================================================== */
    /* ۴) لیست حذف‌شده‌ها                                                   */
    /* ================================================================== */

    /** @return array<int,array<string,mixed>> */
    public function trashed(string $section, ?string $q = null): array
    {
        $query = match ($section) {
            'customers' => $this->trashedUsers(['customer'], $q),
            'operators' => $this->trashedUsers(['coffeenet_manager', 'operator'], $q),
            'admins' => $this->trashedUsers(['super_admin', 'admin'], $q),
            'coffeenets' => $this->trashedByName(Coffeenet::onlyTrashed(), $q),
            'organizations' => $this->trashedByName(Organization::onlyTrashed(), $q),
            'tickets' => $this->trashedTickets($q),
            'orders' => $this->trashedOrders($q),
            default => null,
        };

        abort_unless((bool) $query, 422, 'بخش نامعتبر است.');

        return $query->limit(100)->get()
            ->map(fn ($row) => $this->serializeTrashed($section, $row))
            ->values()
            ->all();
    }

    protected function trashedByName($base, ?string $q): object
    {
        $query = $base->orderByDesc('deleted_at');
        if ($q) {
            $query->where('name', 'like', "%{$q}%");
        }

        return $query;
    }

    protected function trashedUsers(array $roles, ?string $q): object
    {
        $query = User::onlyTrashed()
            ->role($roles)
            ->orderByDesc('deleted_at');

        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('family', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return $query;
    }

    protected function trashedTickets(?string $q): object
    {
        $query = Ticket::onlyTrashed()->with('user:id,name,family,mobile')->orderByDesc('deleted_at');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('ticket_number', 'like', "%{$q}%")->orWhere('subject', 'like', "%{$q}%");
            });
        }

        return $query;
    }

    protected function trashedOrders(?string $q): object
    {
        $query = Order::onlyTrashed()
            ->with(['service:id,name', 'customer:id,name,family,mobile'])
            ->orderByDesc('deleted_at');
        if ($q) {
            $query->where('order_number', 'like', "%{$q}%");
        }

        return $query;
    }

    protected function serializeTrashed(string $section, object $row): array
    {
        $base = [
            'id' => (int) $row->id,
            'deleted_at_fa' => $row->deleted_at ? fa_date($row->deleted_at, 'Y/m/d H:i') : '—',
        ];

        if ($row instanceof User) {
            return $base + [
                'label' => $row->full_name,
                'sub' => trim(($row->mobile ?? '').' · '.($row->email ?? '')) ?: '—',
            ];
        }

        if ($row instanceof Coffeenet) {
            return $base + ['label' => $row->name, 'sub' => 'کافی‌net'];
        }

        if ($row instanceof Organization) {
            return $base + ['label' => $row->name, 'sub' => 'سازمان'];
        }

        if ($row instanceof Ticket) {
            return $base + [
                'label' => $row->ticket_number.' — '.($row->subject ?? ''),
                'sub' => $row->user?->full_name ?? '—',
            ];
        }

        return $base + [
            'label' => $row->order_number.' — '.($row->service?->name ?? '—'),
            'sub' => $row->customer?->full_name ?? '—',
        ];
    }

    /* ================================================================== */
    /* ۵) بازگردانی                                                        */
    /* ================================================================== */

    /** @return array{message:string} */
    public function restore(string $section, int $id, User $actor): array
    {
        $entity = $this->resolve($section, $id);
        abort_unless((bool) $entity, 404, 'رکورد یافت نشد.');

        $message = DB::transaction(function () use ($section, $entity) {
            if ($entity instanceof User) {
                return $this->restoreUser($entity);
            }

            if ($entity instanceof Coffeenet) {
                return $this->restoreCoffeenet($entity);
            }

            if ($entity instanceof Organization) {
                return $this->restoreOrganization($entity);
            }

            if ($entity instanceof Ticket && $entity->trashed()) {
                $entity->restore();

                return 'تیکت «'.$entity->ticket_number.'» بازگردانی شد.';
            }

            if ($entity->trashed()) {
                $entity->restore();

                return 'سفارش «'.$entity->order_number.'» بازگردانی شد.';
            }

            return 'این رکورد در حذف‌شده‌ها نیست.';
        });

        AuditLogger::log('trash.restore', $entity, ['section' => $section], null,
            'بازگردانی '.(self::SECTION_LABELS[$section] ?? $section).' از حذف‌شده‌ها');

        return ['message' => $message];
    }

    protected function restoreUser(User $user): string
    {
        if (! $user->trashed()) {
            return 'این کاربر در حذف‌شده‌ها نیست.';
        }

        $user->restore();

        // عضویت‌های کاری‌ای که با او حذف شده بودند — فقط در کافی‌net‌های زنده ⇒ بازگردانی
        StaffAssignment::onlyTrashed()
            ->where('user_id', $user->id)
            ->whereHas('coffeenet')
            ->restore();

        // کافی‌net‌هایی که با او حذف شده بودند (مدیرش بود) ⇒ بازگردانی
        $nets = Coffeenet::onlyTrashed()->get()
            ->filter(fn (Coffeenet $net) => StaffAssignment::onlyTrashed()
                ->where('coffeenet_id', $net->id)
                ->where('user_id', $user->id)
                ->where('position', 'manager')
                ->exists());

        foreach ($nets as $net) {
            $this->restoreCoffeenet($net, restoreManager: false);
        }

        // سفارش‌ها/تیکت‌های وابسته (هم‌زمان حذف‌شده) ⇒ بازگردانی
        $orders = Order::onlyTrashed()->where('customer_id', $user->id)->get();
        foreach ($orders as $order) {
            $order->restore();
        }

        $tickets = Ticket::onlyTrashed()->where('user_id', $user->id)->get();
        foreach ($tickets as $ticket) {
            $ticket->restore();
        }

        $parts = ['کاربر «'.$user->full_name.'» بازگردانی شد'];
        if ($nets->isNotEmpty()) {
            $parts[] = fa_digits((string) $nets->count()).' کافی‌net او';
        }
        if ($orders->isNotEmpty()) {
            $parts[] = fa_digits((string) $orders->count()).' سفارش';
        }
        if ($tickets->isNotEmpty()) {
            $parts[] = fa_digits((string) $tickets->count()).' تیکت';
        }

        return implode(' + ', $parts).'. توکن‌های ورود به‌خاطر امنیت باطل شده بودند — کاربر باید دوباره وارد شود.';
    }

    protected function restoreCoffeenet(Coffeenet $coffeenet, bool $restoreManager = true): string
    {
        if (! $coffeenet->trashed()) {
            return 'این کافی‌net در حذف‌شده‌ها نیست.';
        }

        $coffeenet->restore();

        // مدیرِ هم‌زمان حذف‌شده ⇒ بازگردانی (پیش از بازگردانی عضویت‌ها — تا از عضویت حذف‌شده قابل تشخیص باشد)
        $manager = null;
        if ($restoreManager) {
            $managerId = StaffAssignment::onlyTrashed()
                ->where('coffeenet_id', $coffeenet->id)
                ->where('position', 'manager')
                ->value('user_id');
            $manager = $managerId ? User::onlyTrashed()->find($managerId) : null;

            if ($manager) {
                $this->restoreUser($manager);
            }
        }

        // عضویت‌های حذف‌شدهٔ این کافی‌net ⇒ بازگردانی
        StaffAssignment::onlyTrashed()->where('coffeenet_id', $coffeenet->id)->restore();

        // سفارش‌های وابسته ⇒ بازگردانی
        $orders = Order::onlyTrashed()->where('coffeenet_id', $coffeenet->id)->get();
        foreach ($orders as $order) {
            $order->restore();
        }

        $parts = ['کافی‌net «'.$coffeenet->name.'» بازگردانی شد'];
        if ($manager) {
            $parts[] = 'کاربر مدیرش';
        }
        if ($orders->isNotEmpty()) {
            $parts[] = fa_digits((string) $orders->count()).' سفارش';
        }

        $message = implode(' + ', $parts).'.';

        if (! $manager && $restoreManager) {
            $message .= ' (مدیر این کافی‌net قابل تشخیص نبود — از ویرایش کافی‌net اطلاعات ورود را دوباره تعریف کنید.)';
        }

        return $message;
    }

    protected function restoreOrganization(Organization $organization): string
    {
        if (! $organization->trashed()) {
            return 'این سازمان در حذف‌شده‌ها نیست.';
        }

        $organization->restore();

        return 'سازمان «'.$organization->name.'» بازگردانی شد. کافی‌net‌های زیرمجموعهٔ قبلی هنگام حذف مستقل شده بودند؛ در صورت نیاز از ویرایش کافی‌net دوباره متصل کنید.';
    }

    /* ================================================================== */
    /* ۶) حذف دائم (purge)                                                 */
    /* ================================================================== */

    /** @return array{message:string} */
    public function purge(string $section, int $id, User $actor): array
    {
        $entity = $this->resolve($section, $id);
        abort_unless((bool) $entity, 404, 'رکورد یافت نشد.');

        $label = $this->info($section, $id, $actor)['label'];

        DB::transaction(function () use ($section, $entity, $actor) {
            match (true) {
                $entity instanceof User => $this->purgeUser($entity, $actor),
                $entity instanceof Coffeenet => $this->purgeCoffeenet($entity, $actor),
                $entity instanceof Organization => $this->purgeOrganization($entity),
                $entity instanceof Ticket => $this->purgeTicket($entity),
                default => $this->purgeOrder($entity),
            };
        });

        AuditLogger::log('trash.purge', null, ['section' => $section, 'label' => $label], null,
            'حذف دائمِ '.(self::SECTION_LABELS[$section] ?? $section).' «'.$label.'» + فایل‌های وابسته');

        return ['message' => (self::SECTION_LABELS[$section] ?? 'رکورد').' «'.$label.'» برای همیشه حذف شد.'];
    }

    protected function purgeUser(User $user, User $actor, ?int $skipCoffeenetId = null): void
    {
        // کافی‌net‌های تحت مدیریت ⇒ حذف دائم آبشاری
        $nets = Coffeenet::withTrashed()
            ->whereKeyNot($skipCoffeenetId ?? 0)
            ->whereHas('staffAssignments', fn ($q) => $q->where('user_id', $user->id)->where('position', 'manager'))
            ->get();

        foreach ($nets as $net) {
            $this->purgeCoffeenet($net, $actor, skipManagerId: (int) $user->id);
        }

        // سفارش‌ها/تیکت‌های مشتری
        foreach (Order::withTrashed()->where('customer_id', $user->id)->get() as $order) {
            $this->purgeOrder($order);
        }

        foreach (Ticket::withTrashed()->where('user_id', $user->id)->get() as $ticket) {
            $this->purgeTicket($ticket);
        }

        // سفارش‌هایی که اپراتورش بوده — تاریخ عملیاتی می‌ماند
        Order::withTrashed()->where('operator_id', $user->id)->update(['operator_id' => null]);

        StaffAssignment::withTrashed()->where('user_id', $user->id)->forceDelete();
        $user->tokens()->delete();
        $user->pushTokens()->delete();
        $user->loginLogs()->delete();
        $user->roles()->detach();
        $user->permissions()->detach();

        // کیف پول + تراکنش‌ها
        $wallet = $user->wallet()->first();
        if ($wallet) {
            $wallet->transactions()->delete();
            $wallet->delete();
        }

        DB::table('notifications')->where('notifiable_id', $user->id)
            ->where('notifiable_type', $user->getMorphClass())->delete();

        $user->forceDelete();
    }

    protected function purgeCoffeenet(Coffeenet $coffeenet, User $actor, ?int $skipManagerId = null): void
    {
        // مدیر ⇒ حذف دائم
        $managerId = StaffAssignment::withTrashed()->where('coffeenet_id', $coffeenet->id)
            ->where('position', 'manager')->value('user_id');

        if ($managerId && (int) $managerId !== ($skipManagerId ?? 0)) {
            $manager = User::withTrashed()->find($managerId);
            if ($manager) {
                $this->purgeUser($manager, $actor, skipCoffeenetId: (int) $coffeenet->id);
            }
        }

        foreach (Order::withTrashed()->where('coffeenet_id', $coffeenet->id)->get() as $order) {
            $this->purgeOrder($order);
        }

        StaffAssignment::withTrashed()->where('coffeenet_id', $coffeenet->id)->forceDelete();
        OrderBroadcast::query()->where('coffeenet_id', $coffeenet->id)->delete();

        $wallet = $coffeenet->wallet()->first();
        if ($wallet) {
            $wallet->transactions()->delete();
            $wallet->delete();
        }

        $coffeenet->forceDelete();
    }

    protected function purgeOrganization(Organization $organization): void
    {
        // کافی‌net‌ها هنگام حذف نرم مستقل شده‌اند — این‌جا هم دست نمی‌زنیم
        $wallet = $organization->wallet()->first();
        if ($wallet) {
            $wallet->transactions()->delete();
            $wallet->delete();
        }

        DB::table('notifications')->where('data->ref->organization_id', $organization->id)->delete();

        $organization->forceDelete();
    }

    protected function purgeTicket(Ticket $ticket): void
    {
        // فایل‌های گفتگو روی دیسک
        try {
            Storage::disk('local')->deleteDirectory('tickets/'.$ticket->id);
        } catch (Throwable) {}

        TicketMessage::query()->where('ticket_id', $ticket->id)->delete();
        $this->purgeNotifications('ticket_id', (int) $ticket->id);

        $ticket->forceDelete();
    }

    protected function purgeOrder(Order $order): void
    {
        // فایل‌های سفارش + گفتگو روی دیسک
        try {
            Storage::disk('local')->deleteDirectory('orders/'.$order->id);
            Storage::disk('local')->deleteDirectory('chat/'.$order->id);
        } catch (Throwable) {}

        // گفتگو و پیام‌ها
        foreach (\App\Models\Conversation::query()->where('order_id', $order->id)->get() as $conversation) {
            \App\Models\Message::query()->where('conversation_id', $conversation->id)->delete();
            $conversation->delete();
        }

        OrderFile::query()->where('order_id', $order->id)->delete();
        OrderStatusHistory::query()->where('order_id', $order->id)->delete();
        OrderBroadcast::query()->where('order_id', $order->id)->delete();
        OrderRating::query()->where('order_id', $order->id)->delete();
        \App\Models\Payment::query()->where('order_id', $order->id)->delete();
        $this->purgeNotifications('order_id', (int) $order->id);

        $order->forceDelete();
    }

    /* ================================================================== */
    /* ابزار                                                               */
    /* ================================================================== */

    /** حذف اعلان‌هایی که به یک موجودیت ارجاع می‌دهند */
    protected function purgeNotifications(string $refKey, int $id): void
    {
        try {
            DB::table('notifications')->where('data->ref->'.$refKey, $id)->delete();
            DB::table('notifications')->where('data->'.$refKey, $id)->delete();
        } catch (Throwable) {
            // اعلان‌ها هرگز مانع حذف نمی‌شوند
        }
    }
}
