<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بررسی نقش برای پنل‌ها — guests به صفحه ورود همان پنل هدایت می‌شوند.
 * استفاده: Route::...->middleware('role:super_admin')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route(self::loginRouteFor($request));
        }

        if (! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();

            return redirect()->route(self::loginRouteFor($request))
                ->withErrors(['login' => 'حساب شما غیرفعال است.']);
        }

        if ($roles !== [] && ! $user->hasAnyRole($roles)) {
            abort(403, 'شما به این بخش دسترسی ندارید.');
        }

        return $next($request);
    }

    /** روت ورود متناسب با پنلی که درخواست از آن آمده */
    protected static function loginRouteFor(Request $request): string
    {
        return match (true) {
            $request->routeIs('org.*'), $request->is('organization*') => 'org.login',
            $request->routeIs('coffeenet.*'), $request->is('coffeenet*') => 'coffeenet.login',
            $request->routeIs('operator.*'), $request->is('operator*') => 'operator.login',
            default => 'admin.login',
        };
    }
}
