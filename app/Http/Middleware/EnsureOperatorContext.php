<?php

namespace App\Http\Middleware;

use App\Enums\CoffeenetStatus;
use App\Enums\StaffPosition;
use App\Models\StaffAssignment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * زمینه پنل اپراتور — کافی‌نتِ فعالِ session را اعتبارسنجی و با ویوها شیر می‌کند.
 *
 * جریان:
 *  - session operator_net_id موجود و اپراتورِ فعالِ همان کافی‌نت → عبور
 *  - session نامعتبر ولی فقط یک انتصاب فعال دارد → انتخاب خودکار
 *  - چند انتصاب بدون انتخاب → هدایت به صفحه انتخاب (خود صفحه بدون زمینه عبور می‌کند)
 *  - هیچ انتصابی → خروج و بازگشت به ورود با پیام خطا
 *
 * توجه: session key از پنل کافی‌نت ('coffeenet_id') مستقل است تا تداخل پیش نیاید.
 */
class EnsureOperatorContext
{
    public const SESSION_KEY = 'operator_net_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('operator.login');
        }

        $assignments = static::assignmentsQuery($user)->with('coffeenet')->get();

        $assignment = $request->session()->get(self::SESSION_KEY)
            ? $assignments->first(fn (StaffAssignment $a) => $a->coffeenet_id === (int) $request->session()->get(self::SESSION_KEY))
            : null;

        if (! $assignment) {
            if ($assignments->count() >= 1) {
                // اپراتور فقط در یک کافی‌نت فعالیت می‌کند؛ بدون صفحهٔ انتخاب —
                // اولین انتصاب فعال به‌صورت خودکار زمینه می‌شود (انتقال فقط توسط مدیر کل)
                $assignment = $assignments->first();
                $request->session()->put(self::SESSION_KEY, $assignment->coffeenet_id);
            } else {
                auth()->logout();
                $request->session()->invalidate();

                return redirect()->route('operator.login')
                    ->withErrors(['login' => 'حساب شما اپراتورِ فعال هیچ کافی‌نتی نیست. با مدیر کافی‌نت خود تماس بگیرید.']);
            }
        }

        view()->share('coffeenet', $assignment->coffeenet);
        view()->share('operatorAssignment', $assignment);

        $request->attributes->set('current_coffeenet', $assignment->coffeenet);
        $request->attributes->set('operator_assignment', $assignment);

        return $next($request);
    }

    /**
     * انتصاب‌های فعالِ اپراتوری کاربر در کافی‌نت‌های تأییدشده
     *
     * @return \Illuminate\Database\Eloquent\Builder<StaffAssignment>
     */
    public static function assignmentsQuery($user)
    {
        return StaffAssignment::query()
            ->where('user_id', $user->getKey())
            ->where('position', StaffPosition::Operator->value)
            ->where('is_active', true)
            ->whereHas('coffeenet', fn ($q) => $q->where('status', CoffeenetStatus::Approved->value))
            ->orderBy('coffeenet_id');
    }
}
