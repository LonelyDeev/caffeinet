<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>@yield('title', 'کافی‌نت آنلاین') | کافی‌نت آنلاین</title>

    {{-- PWA: مانیفست + آیکون‌ها + ثبت Service Worker (فاز ۱۴) --}}
    @include('partials.pwa', ['panel' => 'app'])
    @include('partials.vpn-modal')

    {{-- بوت تم شب/روز (ضد-FOUC) — قبل از استایل‌ها؛ کلید ذخیره مشترک با پنل‌ها --}}
    <script src="{{ asset('assets/js/theme-boot.js') }}"></script>

    {{-- فونت وزیرمتن --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap">

    {{-- استایل مستقل (بدون نیاز به بیلد Node) --}}
    <link rel="stylesheet" href="{{ asset('front/assets/css/app.css') }}?v=18">
    {{-- تقویم/دیت‌پیکر شمسی (CNJdp) --}}
    <link rel="stylesheet" href="{{ asset('assets/css/jalali-datepicker.css') }}?v=3">
    @stack('styles')
</head>
<body>
<div class="app-shell @yield('shell-class')">

    @php
    $chrome = trim($__env->yieldContent('no-chrome')) !== '1';
@endphp

    @if ($chrome)
        {{-- هدر --}}
        <header class="app-header">
            <a class="brand" href="{{ route('app.home') }}">
                <span class="brand-mark" aria-hidden="true">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>
                    </svg>
                </span>
                <span>
                    <span class="brand-name">کافی‌نت آنلاین</span>
                    <span class="brand-sub">خدمات آنلاین</span>
                </span>
            </a>

            <div class="actions">
                {{-- سوییچ شب/روز (CN.theme در core.js — کلید مشترک پنل‌ها) --}}
                <button type="button" class="theme-btn" data-theme-toggle id="appThemeBtn"
                        aria-label="تغییر حالت شب و روز" title="حالت شب/روز">
                    <svg class="tt-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                    <svg class="tt-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                </button>
                {{-- زنگ اعلان (فاز ۱۰) --}}
                <button type="button" class="bell-btn" id="appBell" aria-label="اعلان‌ها" aria-expanded="false">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    <span class="bell-badge" id="appBellBadge" aria-hidden="true"></span>
                </button>
                {{-- کیف پول: موجودی از هدر حذف شد (باعث بهم‌ریختگی هدر با مبالغ بزرگ می‌شد)؛
                     دسترسی از ناوبری پایین و صفحهٔ پروفایل --}}
                <a class="avatar-btn" href="{{ route('app.profile') }}" id="headerAvatar" title="پروفایل">؟</a>
            </div>
        </header>
    @endif

    {{-- محتوا --}}
    <main class="app-main" id="appMain">
        @yield('content')
    </main>

    @if ($chrome)
        {{-- ناوبری پایین (چسبان — نقش فوتر) --}}
        <nav class="bottom-nav" aria-label="ناوبری اصلی">
            <div class="nav-inner">
                @php
                    $active = trim($__env->yieldContent('active-nav'));
                    $nav = [
                        'home' => [route('app.home'), 'خانه', '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>'],
                        'services' => [route('app.services'), 'خدمات', '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
                        'orders' => [route('app.orders'), 'سفارش‌ها', '<path d="M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M9 12h6"/><path d="M9 16h4"/>'],
                        'support' => [route('app.support'), 'پشتیبانی', '<path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Z"/><path d="M18 11h3v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2Z"/><path d="M21 11a9 9 0 0 0-18 0"/>'],
                        'wallet' => [route('app.wallet'), 'کیف پول', '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>'],
                        'profile' => [route('app.profile'), 'پروفایل', '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
                    ];
                @endphp
                @foreach($nav as $key => $item)
                    <a href="{{ $item[0] }}" class="{{ $active === $key ? 'active' : '' }}" @if($active === $key) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item[2] !!}</svg>
                        <span>{{ $item[1] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    @endif
</div>

{{-- توست‌ها --}}
<div class="toast-wrap" id="toastWrap" aria-live="polite"></div>

{{-- شیت اعلان‌ها (فاز ۱۰) — v25: ردیف نوتیف دستگاه در پایین شیت --}}
<div class="notif-overlay" id="appNotifOverlay" aria-hidden="true"></div>
<div class="notif-sheet" id="appNotifSheet" role="dialog" aria-modal="true" aria-labelledby="appNotifTitle">
    <div class="sheet-grip" aria-hidden="true"></div>
    <div class="ns-head">
        <h2 id="appNotifTitle">اعلان‌ها</h2>
        <span class="ns-count" id="appNotifCount"></span>
        <button type="button" class="ns-markall" id="appNotifMarkAll">خواندم ✓</button>
    </div>
    <div class="ns-list" id="appNotifList">
        <div class="ns-loading"><span class="spinner"></span></div>
    </div>
    <div class="ns-push-row">
        <button type="button" class="ns-push-btn" id="appPushBtn"></button>
    </div>
</div>

{{-- اسکریپت‌ها: jQuery (vendor استاتیک) + هسته مشترک + اسکریپت صفحه (فایل جدا) --}}
{{-- Realtime پوشر (فاز ۱۳): پیکربندی عمومی CSP-safe؛ کانال شخصی کاربر از API /realtime/config --}}
<script src="{{ asset('assets/js/vendor/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/vendor/pusher.min.js') }}?v=1"></script>
<script src="{{ asset('assets/js/realtime.js') }}?v=2" data-rt-config='@json(app(\App\Services\Realtime\PusherService::class)->clientConfig(null))'></script>
<script src="{{ asset('front/assets/js/core.js') }}?v=3" defer></script>
<script src="{{ asset('assets/js/jalali-datepicker.js') }}?v=2" defer></script>
{{-- نوتیف دستگاه (v26: پیش‌فرض/پوشر/فایربیس) — پیکربندی از PushManager؛ اپ مشتری از CN.api برای ثبت استفاده می‌کند --}}
<script src="{{ asset('assets/js/push/push-client.js') }}?v=4" defer data-push-config='@json(app(\App\Services\Push\PushManager::class)->clientConfig(auth()->user()))'></script>
<script src="{{ asset('front/assets/js/pages/notifications.js') }}?v=3" defer></script>
<script src="{{ asset('front/assets/js/pages/announcements.js') }}?v=15" defer></script>
@stack('page')
</body>
</html>
