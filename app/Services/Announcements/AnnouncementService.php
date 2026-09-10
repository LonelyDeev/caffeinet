<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * فاز ۱۵ — سرویس اطلاعیه‌ها.
 *
 * مخاطب‌یابی بر اساس نقش‌های spatie:
 *   customers → نقش customer
 *   admins → super_admin | admin
 *   org_managers / coffeenet_managers / operators → همان نقش‌ها
 *   all_panels → هر کاربر بدون نقش customer
 *   everyone → همه
 */
class AnnouncementService
{
    /** نگاشت مخاطب → نقش‌های مجاز (null یعنی همه) */
    protected const AUDIENCE_ROLES = [
        'customers' => ['customer'],
        'admins' => ['super_admin', 'admin'],
        'org_managers' => ['org_manager'],
        'coffeenet_managers' => ['coffeenet_manager'],
        'operators' => ['operator'],
        'all_panels' => '__non_customer__',
        'everyone' => null,
    ];

    /**
     * اطلاعیه‌های فعالِ در پنجره‌ی نمایشِ مخاطبِ کاربر که هنوز ندیده.
     *
     * @return Collection<int, Announcement>
     */
    public function pendingFor(User $user, int $limit = 5): Collection
    {
        $roles = $user->roles->pluck('name')->all();

        $audiences = $this->audiencesForRoles($roles);

        if ($audiences === null) {
            return collect();
        }

        $readIds = AnnouncementRead::query()
            ->where('user_id', $user->id)
            ->pluck('announcement_id');

        return Announcement::query()
            ->where('is_active', true)
            ->whereIn('audience', $audiences)
            ->whereNotIn('id', $readIds)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** علامت‌گذاری اطلاعیه به‌عنوان دیده‌شده (idempotent) */
    public function markRead(Announcement $announcement, User $user): void
    {
        AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }

    /** لیست مخاطبان مجاز این مجموعه نقش — null = هیچ */
    protected function audiencesForRoles(array $roles): ?array
    {
        $any = fn (array $need) => count(array_intersect($need, $roles)) > 0;

        $audiences = [];

        foreach (self::AUDIENCE_ROLES as $audience => $roleRule) {
            $match = match (true) {
                $roleRule === null => true, // everyone
                $roleRule === '__non_customer__' => ! in_array('customer', $roles, true) || $any(['super_admin', 'admin', 'org_manager', 'coffeenet_manager', 'operator']),
                default => $any($roleRule),
            };

            if ($match) {
                $audiences[] = $audience;
            }
        }

        if ($audiences === []) {
            return null;
        }

        return $audiences;
    }
}
