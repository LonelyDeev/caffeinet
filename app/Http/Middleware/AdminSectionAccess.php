<?php

namespace App\Http\Middleware;

use App\Policies\AdminAccessPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * گارد پنل مدیریت کل (درخواست بازخوردی ۶-۴).
 *
 * جایگزین role:super_admin — مدیران دستیار (نقش admin) هم وارد می‌شوند،
 * اما هر روت بر اساس «بخش» نام روت از طریق AdminAccessPolicy بررسی می‌شود:
 *
 *   admin.orders.*      → بخش orders       → مجوز orders.view
 *   admin.coffeenets.*  → بخش coffeenets   → مجوز coffeenets.manage
 *   ...
 *
 * مدیر کل (super_admin) با Gate::before همیشه پاس است.
 */
class AdminSectionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();

            return redirect()->route('admin.login')
                ->withErrors(['login' => 'حساب شما غیرفعال است.']);
        }

        // فقط نقش‌های پنل مدیریت کل
        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            abort(403, 'شما به پنل مدیریت دسترسی ندارید.');
        }

        $section = $this->sectionOf($request);

        if ($section !== null && ! $user->can('access', [\App\Models\User::class, $section])) {
            abort(403, 'دسترسی به بخش «'.(AdminAccessPolicy::SECTION_LABELS[$section] ?? $section).'» برای شما فعال نیست.');
        }

        return $next($request);
    }

    /**
     * استخراج بخش از نام روت: admin.orders.index → orders
     * روت‌های بدون بخش (admin.dashboard خودش بخش است) همان نام کامل را برمی‌گردانند.
     */
    protected function sectionOf(Request $request): ?string
    {
        $name = (string) $request->route()?->getName();

        if ($name === '') {
            return null;
        }

        if (! str_starts_with($name, 'admin.')) {
            return null;
        }

        $rest = substr($name, strlen('admin.'));

        // admin.dashboard → dashboard
        if (! str_contains($rest, '.')) {
            return $rest;
        }

        // admin.orders.index → orders
        return strtok($rest, '.');
    }
}
