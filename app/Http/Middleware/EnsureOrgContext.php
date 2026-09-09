<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * زمینه پنل سازمان — سازمان فعالِ session را اعتبارسنجی و با همه ویوها شیر می‌کند.
 *
 * جریان:
 *  - session org_id موجود و متعلق به کاربر → عبور
 *  - session نامعتبر ولی کاربر فقط یک سازمان دارد → انتخاب خودکار
 *  - چند سازمان بدون انتخاب → هدایت به صفحه انتخاب (خود صفحه بدون زمینه عبور می‌کند)
 *  - هیچ سازمانی → خروج و بازگشت به ورود با پیام خطا
 */
class EnsureOrgContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('org.login');
        }

        // صفحات انتخاب سازمان بدون زمینه عبور می‌کنند
        if ($request->routeIs(['org.choose', 'org.select'])) {
            return $next($request);
        }

        /** @var Organization|null $org */
        $org = $request->session()->get('org_id')
            ? Organization::query()
                ->where('id', $request->session()->get('org_id'))
                ->where('owner_id', $user->id)
                ->first()
            : null;

        if (! $org) {
            $owned = Organization::query()->where('owner_id', $user->id)->orderBy('id')->get();

            if ($owned->count() === 1) {
                // تنها یک سازمان → انتخاب خودکار
                $org = $owned->first();
                $request->session()->put('org_id', $org->id);
            } elseif ($owned->count() > 1) {
                return redirect()->route('org.choose');
            } else {
                auth()->logout();
                $request->session()->invalidate();

                return redirect()->route('org.login')
                    ->withErrors(['login' => 'حساب شما به هیچ سازمانی متصل نیست.']);
            }
        }

        view()->share('organization', $org);
        $request->attributes->set('current_organization', $org);

        return $next($request);
    }
}
