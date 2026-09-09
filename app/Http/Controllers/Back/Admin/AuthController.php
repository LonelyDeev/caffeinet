<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /** فرم ورود مدیران پنل مدیریت کل */
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->hasAnyRole(['super_admin', 'admin'])) {
            return redirect()->route('admin.dashboard');
        }

        return view('back.admin.auth.login');
    }

    /** تلاش ورود (AJAX — JSON) */
    public function login(Request $request): JsonResponse
    {
        $key = 'admin-login:'.($request->ip() ?? 'cli');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "تلاش‌های بیش از حد؛ {$seconds} ثانیه دیگر امتحان کنید.",
            ], 429);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ], [
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'فرمت ایمیل معتبر نیست.',
            'password.required' => 'رمز عبور الزامی است.',
        ]);

        $credentials = [
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, (bool) ($data['remember'] ?? false))) {
            RateLimiter::hit($key, 60);

            AuditLogger::log('auth.login_failed', null, null,
                ['email' => $data['email']], 'تلاش ناموفق ورود مدیر کل');

            return response()->json(['message' => 'ایمیل یا رمز عبور اشتباه است.'], 422);
        }

        $user = Auth::user();

        // مدیران پنل: مدیر کل یا مدیر دستیار (درخواست بازخوردی ۶-۴)
        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            Auth::logout();

            return response()->json(['message' => 'شما به پنل مدیریت دسترسی ندارید.'], 403);
        }

        // مدیر دستیارِ بدون هیچ مجوزی — حداقل داشبورد لازم است
        if (! $user->hasRole('super_admin') && ! $user->can('dashboard.access')) {
            Auth::logout();

            return response()->json(['message' => 'هیچ بخشی از پنل برای شما فعال نشده است؛ با مدیر کل تماس بگیرید.'], 403);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLogger::log('auth.login', $user, null, null, 'ورود موفق مدیر کل');

        return response()->json([
            'message' => 'خوش آمدید!',
            'redirect' => route('admin.dashboard'),
        ]);
    }

    /** خروج */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLogger::log('auth.logout', $user, null, null, 'خروج مدیر کل');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'با موفقیت خارج شدید.',
                'redirect' => route('admin.login'),
            ]);
        }

        return redirect()->route('admin.login');
    }
}
