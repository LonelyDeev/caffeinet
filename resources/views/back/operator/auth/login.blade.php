<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- تم ذخیره‌شده قبل از رندر اعمال می‌شود (ضد-FOUC) --}}
    <script src="{{ asset('assets/js/theme-boot.js') }}?v=1"></script>

    <title>ورود اپراتور — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap">

    {{-- استایل و اسکریپت — کاملاً بدون Node / بدون بیلد --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=10">
    <link rel="stylesheet" href="{{ asset('assets/css/panel-ui.css') }}?v=10">
    <link rel="stylesheet" href="{{ asset('assets/css/pages/operator.css') }}?v=10">
    {{-- سیستم تم روشن/تاریک (فاز ۱۰) — باید آخرین CSS باشد تا برنده بماند --}}
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v=10">
</head>
<body class="font-sans antialiased selection:bg-amber-200 selection:text-amber-950">

{{-- سوییچ تم روشن/تاریک — گوشهٔ بالا-چپ (در RTL دور از محتوا) --}}
<button type="button" class="theme-toggle" data-theme-toggle aria-label="تغییر تم روشن/تاریک" title="حالت روشن/تاریک" style="position:fixed;top:1rem;left:1rem;z-index:50">
    <svg class="tt-icon tt-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
    <svg class="tt-icon tt-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
</button>

<main class="relative min-h-screen grid place-items-center overflow-hidden bg-[#171009] px-4">

    {{-- پس‌زمینه --}}
    <div class="pointer-events-none fixed inset-0" aria-hidden="true">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_45%_at_50%_-10%,rgba(196,127,61,0.25),transparent)]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_35%_28%_at_10%_85%,rgba(211,156,92,0.08),transparent)]"></div>
        <div class="absolute inset-0 opacity-[0.04] bg-[repeating-linear-gradient(135deg,transparent_0_9px,rgba(226,186,133,0.5)_9px_10px)]"></div>
        {{-- حباب‌های شناور گرم --}}
        <span class="ui-blob" data-tone="amber" data-pos="1"></span>
        <span class="ui-blob" data-tone="gold" data-pos="2"></span>
        <span class="ui-blob" data-tone="deep" data-pos="3"></span>
    </div>

    <div class="relative w-full max-w-md py-10">

        <div class="text-center mb-8 animate-fade-up delay-1">
            <span class="inline-grid place-items-center size-16 rounded-3xl bg-gradient-to-br from-amber-400 to-amber-700 shadow-2xl shadow-amber-950/50 mb-4 animate-float-soft">
                <svg class="size-8 text-amber-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>
                </svg>
            </span>
            <h1 class="text-2xl font-extrabold text-amber-50 tracking-tight">کافی‌نت آنلاین</h1>
            <p class="mt-1.5 text-sm text-amber-200/60 font-light">ورود به پنل اپراتور / کارمند</p>
        </div>

        {{-- کارت ورود --}}
        <form id="login-form" class="animate-fade-up delay-2 ui-auth-card rounded-3xl glass-warm p-7 space-y-5" novalidate>

            @if ($errors->has('login'))
                <p class="text-xs text-rose-300 bg-rose-500/10 border border-rose-400/20 rounded-xl px-3.5 py-2.5 leading-6">
                    {{ $errors->first('login') }}
                </p>
            @endif

            <div>
                <label class="lbl !text-amber-200/80" for="email">ایمیل</label>
                <div class="relative">
                    <input id="email" name="email" type="email" dir="ltr" autocomplete="username"
                           class="field !bg-white/10 !border-white/15 !text-amber-50 placeholder:!text-stone-400 pl-10" placeholder="operator@coffeenet.ir" required>
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 size-4.5 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </div>
                <p class="err text-[11px] text-rose-300 mt-1.5 hidden" data-for="email"></p>
            </div>

            <div>
                <label class="lbl !text-amber-200/80" for="password">رمز عبور</label>
                <div class="relative">
                    <input id="password" name="password" type="password" dir="ltr" autocomplete="current-password"
                           class="field !bg-white/10 !border-white/15 !text-amber-50 placeholder:!text-stone-400 pl-10 pr-10" placeholder="••••••••" required>
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 size-4.5 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 21v-2a4 4 0 0 1 4-4h2"/><path d="M9.5 3.94a5 5 0 1 0-3.47 8.56 5 5 0 1 0 3.47-8.56z"/></svg>
                    <button type="button" id="toggle-pass" class="absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 hover:text-amber-200 transition-colors" aria-label="نمایش رمز">
                        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                    </button>
                </div>
                <p class="err text-[11px] text-rose-300 mt-1.5 hidden" data-for="password"></p>
            </div>

            <p id="form-error" class="hidden text-xs text-rose-300 bg-rose-500/10 border border-rose-400/20 rounded-xl px-3.5 py-2.5 leading-6"></p>

            <button type="submit" id="login-btn" class="btn-primary btn-shine ui-press w-full !py-3.5 !text-[15px]">
                <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                ورود به پنل اپراتور
            </button>

            <p class="text-center text-[11px] text-stone-400 leading-5">
                حساب اپراتور شما توسط مدیر کافی‌نت ساخته می‌شود؛
                در صورت مشکل با وی تماس بگیرید.
            </p>
        </form>

        <p class="animate-fade-up delay-3 text-center mt-6 text-[11px] text-stone-400">
            <a href="{{ url('/') }}" class="hover:text-amber-200 transition-colors">→ بازگشت به صفحه اصلی</a>
        </p>
    </div>
</main>

<script src="{{ asset('assets/js/vendor/jquery.min.js') }}?v=10"></script>
<script src="{{ asset('back/assets/js/core.js') }}?v=10"></script>
<script src="{{ asset('back/assets/js/ui.js') }}?v=10"></script>
<script src="{{ asset('back/assets/js/pages/operator/auth/login.js') }}?v=10"></script>
</body>
</html>
