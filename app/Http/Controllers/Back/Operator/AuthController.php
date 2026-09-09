<?php

namespace App\Http\Controllers\Back\Operator;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * احراز هویت پنل اپراتور (فاز ۷ — زیرساخت).
 *
 * اپراتور = کاربر نقش operator با انتصاب فعال (StaffAssignment) در کافی‌نت تأییدشده.
 * هر اپراتور فقط در یک کافی‌نت فعالیت می‌کند؛ کافی‌نت پس از ورود به‌صورت خودکار
 * انتخاب می‌شود (بدون صفحهٔ انتخاب) و انتقال آن فقط توسط مدیر کل انجام می‌شود.
 */
class AuthController extends Controller
{
    /** فرم ورود اپراتور */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->hasRole('operator')) {
            return redirect()->route('operator.dashboard');
        }

        return view('back.operator.auth.login');
    }

    /** تلاش ورود (AJAX — JSON) */
    public function login(Request $request): JsonResponse
    {
        $key = 'op-login:'.($request->ip() ?? 'cli');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "تلاش‌های بیش از حد؛ {$seconds} ثانیه دیگر امتحان کنید.",
            ], 429);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'فرمت ایمیل معتبر نیست.',
            'password.required' => 'رمز عبور الزامی است.',
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'is_active' => true])) {
            RateLimiter::hit($key, 60);

            AuditLogger::log('operator.auth.login_failed', null, null,
                ['email' => $data['email']], 'تلاش ناموفق ورود اپراتور');

            return response()->json(['message' => 'ایمیل یا رمز عبور اشتباه است.'], 422);
        }

        $user = Auth::user();

        if (! $user->hasRole('operator')) {
            Auth::logout();

            return response()->json(['message' => 'این حساب برای ورود به پنل اپراتور مجاز نیست.'], 403);
        }

        $assignments = \App\Http\Middleware\EnsureOperatorContext::assignmentsQuery($user)
            ->with('coffeenet:id,name,status')
            ->get();

        if ($assignments->isEmpty()) {
            Auth::logout();

            return response()->json([
                'message' => 'حساب شما اپراتورِ فعال هیچ کافی‌نت تأییدشده‌ای نیست. با مدیر کافی‌نت خود تماس بگیرید.',
            ], 403);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLogger::log('operator.auth.login', $user, null, null, 'ورود اپراتور');

        // اپراتور فقط در یک کافی‌نت فعالیت می‌کند — انتخاب خودکار، بدون صفحهٔ انتخاب
        // (انتقال بین کافی‌نت‌ها فقط توسط مدیر کل انجام می‌شود)
        $request->session()->put(\App\Http\Middleware\EnsureOperatorContext::SESSION_KEY, $assignments->first()->coffeenet_id);

        return response()->json([
            'message' => 'خوش آمدید!',
            'redirect' => route('operator.dashboard'),
        ]);
    }

    /** خروج */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLogger::log('operator.auth.logout', $user, null, null, 'خروج اپراتور');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'با موفقیت خارج شدید.',
                'redirect' => route('operator.login'),
            ]);
        }

        return redirect()->route('operator.login');
    }
}
