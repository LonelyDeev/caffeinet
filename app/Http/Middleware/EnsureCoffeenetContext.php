<?php

namespace App\Http\Middleware;

use App\Enums\CoffeenetStatus;
use App\Enums\StaffPosition;
use App\Models\Coffeenet;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * زمینه پنل کافی‌نت — کافی‌نت فعالِ session را اعتبارسنجی و با همه ویوها شیر می‌کند.
 *
 * جریان:
 *  - session coffeenet_id موجود و کاربر مدیرِ فعالِ همان کافی‌نت → عبور
 *  - session نامعتبر ولی کاربر فقط یک مدیریت فعال دارد → انتخاب خودکار
 *  - چند مدیریت بدون انتخاب → هدایت به صفحه انتخاب (خود صفحه بدون زمینه عبور می‌کند)
 *  - هیچ مدیریتی → خروج و بازگشت به ورود با پیام خطا
 */
class EnsureCoffeenetContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('coffeenet.login');
        }

        // صفحات انتخاب کافی‌نت بدون زمینه عبور می‌کنند
        if ($request->routeIs(['coffeenet.choose', 'coffeenet.select'])) {
            return $next($request);
        }

        $managed = static::managedQuery($user)->get();

        $coffeenet = $request->session()->get('coffeenet_id')
            ? $managed->firstWhere('id', $request->session()->get('coffeenet_id'))
            : null;

        if (! $coffeenet) {
            if ($managed->count() === 1) {
                $coffeenet = $managed->first();
                $request->session()->put('coffeenet_id', $coffeenet->id);
            } elseif ($managed->count() > 1) {
                return redirect()->route('coffeenet.choose');
            } else {
                auth()->logout();
                $request->session()->invalidate();

                return redirect()->route('coffeenet.login')
                    ->withErrors(['login' => 'حساب شما مدیریت هیچ کافی‌نتی را بر عهده ندارد.']);
            }
        }

        view()->share('coffeenet', $coffeenet);
        $request->attributes->set('current_coffeenet', $coffeenet);

        return $next($request);
    }

    /** کافی‌نت‌هایی که کاربر مدیرِ فعالِ آنها است (مدل‌های Coffeenet واقعی) */
    public static function managedQuery($user)
    {
        return Coffeenet::query()
            ->whereHas('staffAssignments', function ($q) use ($user) {
                $q->where('user_id', $user->getKey())
                    ->where('position', StaffPosition::Manager->value)
                    ->where('is_active', true);
            })
            ->where('status', CoffeenetStatus::Approved->value)
            ->orderBy('id');
    }
}
