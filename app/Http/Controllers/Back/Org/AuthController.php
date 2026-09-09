<?php

namespace App\Http\Controllers\Back\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** فرم ورود مدیر سازمان */
    public function showLogin(): View
    {
        if (Auth::check() && Auth::user()->hasRole('org_manager')) {
            return redirect()->route('org.dashboard');
        }

        return view('back.org.auth.login');
    }

    /** تلاش ورود (AJAX — JSON) */
    public function login(Request $request): JsonResponse
    {
        $key = 'org-login:'.($request->ip() ?? 'cli');

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

            AuditLogger::log('org.auth.login_failed', null, null,
                ['email' => $data['email']], 'تلاش ناموفق ورود مدیر سازمان');

            return response()->json(['message' => 'ایمیل یا رمز عبور اشتباه است.'], 422);
        }

        $user = Auth::user();

        if (! $user->hasRole('org_manager')) {
            Auth::logout();

            return response()->json(['message' => 'این حساب برای ورود به پنل سازمان مجاز نیست.'], 403);
        }

        $owned = Organization::query()->where('owner_id', $user->id)->orderBy('id')->get();

        if ($owned->isEmpty()) {
            Auth::logout();

            return response()->json([
                'message' => 'حساب شما به هیچ سازمانی متصل نیست. با پشتیبانی تماس بگیرید.',
            ], 403);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLogger::log('org.auth.login', $user, null, null, 'ورود مدیر سازمان');

        // یک سازمان → انتخاب خودکار؛ چند سازمان → صفحه انتخاب
        if ($owned->count() === 1) {
            $request->session()->put('org_id', $owned->first()->id);

            return response()->json([
                'message' => 'خوش آمدید!',
                'redirect' => route('org.dashboard'),
            ]);
        }

        $request->session()->forget('org_id');

        return response()->json([
            'message' => 'چند سازمان برای شما ثبت شده است، یکی را انتخاب کنید.',
            'redirect' => route('org.choose'),
        ]);
    }

    /** صفحه انتخاب سازمان (برای کاربرانی که چند سازمان دارند) */
    public function choose(): View
    {
        $organizations = Organization::query()
            ->where('owner_id', auth()->id())
            ->with('province:id,name')
            ->orderBy('name')
            ->get();

        return view('back.org.auth.choose', ['organizations' => $organizations]);
    }

    /** ثبت سازمان انتخابی در session (AJAX) */
    public function select(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer'],
        ]);

        $org = Organization::query()
            ->where('id', $data['organization_id'])
            ->where('owner_id', $request->user()->id)
            ->first();

        if (! $org) {
            return response()->json(['message' => 'سازمان یافت نشد.'], 422);
        }

        $request->session()->put('org_id', $org->id);

        return response()->json([
            'message' => 'سازمان «'.$org->name.'» انتخاب شد.',
            'redirect' => route('org.dashboard'),
        ]);
    }

    /** خروج */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLogger::log('org.auth.logout', $user, null, null, 'خروج مدیر سازمان');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'با موفقیت خارج شدید.',
                'redirect' => route('org.login'),
            ]);
        }

        return redirect()->route('org.login');
    }
}
