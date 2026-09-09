<?php

namespace App\Http\Controllers\Back\Coffeenet;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** فرم ورود مدیر کافی‌نت */
    public function showLogin(): View
    {
        if (Auth::check() && Auth::user()->hasRole('coffeenet_manager')) {
            return redirect()->route('coffeenet.dashboard');
        }

        return view('back.coffeenet.auth.login');
    }

    /** تلاش ورود (AJAX — JSON) */
    public function login(Request $request): JsonResponse
    {
        $key = 'net-login:'.($request->ip() ?? 'cli');

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

            AuditLogger::log('coffeenet.auth.login_failed', null, null,
                ['email' => $data['email']], 'تلاش ناموفق ورود مدیر کافی‌نت');

            return response()->json(['message' => 'ایمیل یا رمز عبور اشتباه است.'], 422);
        }

        $user = Auth::user();

        if (! $user->hasRole('coffeenet_manager')) {
            Auth::logout();

            return response()->json(['message' => 'این حساب برای ورود به پنل کافی‌نت مجاز نیست.'], 403);
        }

        $managed = \App\Http\Middleware\EnsureCoffeenetContext::managedQuery($user)->get();

        if ($managed->isEmpty()) {
            Auth::logout();

            return response()->json([
                'message' => 'حساب شما مدیریت هیچ کافی‌نت تأییدشده‌ای را بر عهده ندارد. با پشتیبانی تماس بگیرید.',
            ], 403);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLogger::log('coffeenet.auth.login', $user, null, null, 'ورود مدیر کافی‌نت');

        // یک کافی‌نت → انتخاب خودکار؛ چند کافی‌نت → صفحه انتخاب
        if ($managed->count() === 1) {
            $request->session()->put('coffeenet_id', $managed->first()->id);

            return response()->json([
                'message' => 'خوش آمدید!',
                'redirect' => route('coffeenet.dashboard', $managed->first()),
            ]);
        }

        $request->session()->forget('coffeenet_id');

        return response()->json([
            'message' => 'شما مدیر چند کافی‌نت هستید، یکی را انتخاب کنید.',
            'redirect' => route('coffeenet.choose'),
        ]);
    }

    /** صفحه انتخاب کافی‌نت (برای مدیرانی که چند کافی‌نت دارند) */
    public function choose(): View
    {
        $coffeenets = \App\Http\Middleware\EnsureCoffeenetContext::managedQuery(auth()->user())
            ->with('province:id,name', 'city:id,name', 'organization:id,name')
            ->orderBy('name')
            ->get();

        return view('back.coffeenet.auth.choose', ['coffeenets' => $coffeenets]);
    }

    /** ثبت کافی‌نت انتخابی در session (AJAX) */
    public function select(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coffeenet_id' => ['required', 'integer'],
        ]);

        $net = \App\Http\Middleware\EnsureCoffeenetContext::managedQuery($request->user())
            ->where('coffeenets.id', $data['coffeenet_id'])
            ->first();

        if (! $net) {
            return response()->json(['message' => 'کافی‌نت یافت نشد یا به شما تعلق ندارد.'], 422);
        }

        $request->session()->put('coffeenet_id', $net->id);

        return response()->json([
            'message' => 'کافی‌نت «'.$net->name.'» انتخاب شد.',
            'redirect' => route('coffeenet.dashboard', ['coffeenet' => $net->id]),
        ]);
    }

    /** خروج */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLogger::log('coffeenet.auth.logout', $user, null, null, 'خروج مدیر کافی‌نت');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'با موفقیت خارج شدید.',
                'redirect' => route('coffeenet.login'),
            ]);
        }

        return redirect()->route('coffeenet.login');
    }
}
