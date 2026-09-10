<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- تم ذخیره‌شده قبل از رندر اعمال می‌شود (ضد-FOUC) --}}
    <script src="{{ asset('assets/js/theme-boot.js') }}?v=1"></script>

    <title>@yield('title', 'پنل مدیریت') — {{ config('app.name') }}</title>

    {{-- PWA: مانیفست + آیکون‌ها + ثبت Service Worker (فاز ۱۴) --}}
    @include('partials.pwa', ['panel' => 'admin'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap">

    {{-- استایل و اسکریپت — کاملاً بدون Node / بدون بیلد --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=12">
    <link rel="stylesheet" href="{{ asset('assets/css/panel-ui.css') }}?v=14">
    <link rel="stylesheet" href="{{ asset('assets/css/pages/admin.css') }}?v=10">
    {{-- زنگ اعلان (فاز ۱۰) — قبل از theme --}}
    <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}?v=13">
    {{-- مودال اطلاعیه‌های سامانه (فاز ۱۵) --}}
    <link rel="stylesheet" href="{{ asset('assets/css/panel-announcements.css') }}?v=15">
    {{-- سیستم تم روشن/تاریک (فاز ۱۰) — باید آخرین CSS باشد تا برنده بماند --}}
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v=10">
    {{-- تقویم/دیت‌پیکر شمسی (CNJdp) --}}
    <link rel="stylesheet" href="{{ asset('assets/css/jalali-datepicker.css') }}?v=3">
    {{-- استایل‌های اختصاصی صفحات (push با @push('styles')) --}}
    @stack('styles')
</head>
<body class="font-sans antialiased bg-stone-100 text-stone-800 selection:bg-amber-200 selection:text-amber-950"
      data-logout-url="/admin/logout" data-login-url="/admin/login"
      data-nb-badge="{{ route('admin.notifications.badge') }}"
      data-nb-data="{{ route('admin.notifications.data') }}"
      data-nb-read="{{ route('admin.notifications.read') }}" data-ann-pending="{{ route('admin.announcements.pending') }}" data-ann-read="/admin/announcements/__ID__/read">

<div class="min-h-screen flex">

    {{-- ================== سایدبار ================== --}}
    <aside id="panel-sidebar" class="fixed lg:sticky top-0 h-screen w-72 shrink-0 z-40 translate-x-full lg:translate-x-0 transition-transform duration-300 bg-gradient-to-b from-[#241608] via-[#2e1c0a] to-[#1d1206] text-stone-200 flex flex-col">

        <div class="px-5 py-5 border-b border-white/10 flex items-center gap-3">
            <span class="grid place-items-center size-10 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-700 shadow-lg shadow-black/30 shrink-0">
                <svg class="size-5 text-amber-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>
                </svg>
            </span>
            <div class="leading-tight min-w-0">
                <strong class="block text-sm font-extrabold tracking-tight text-amber-50 truncate">کافی‌نت آنلاین</strong>
                <span class="block text-[11px] text-amber-200/60 font-medium">پنل مدیریت کل</span>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1" aria-label="ناوبری پنل">
            @php
                // فیلتر مجوزمحور منو (درخواست بازخوردی ۶-۴) — مدیر کل همه را می‌بیند
                $nav = array_values(array_filter([
                    ['route' => 'admin.dashboard', 'label' => 'داشبورد', 'icon' => 'grid', 'section' => 'dashboard', 'active' => request()->routeIs('admin.dashboard')],
                    ['route' => 'admin.orders.index', 'label' => 'سفارش‌ها', 'icon' => 'orders', 'section' => 'orders', 'active' => request()->routeIs('admin.orders.*') && ! request()->routeIs('admin.orders.chat*')],
                    ['route' => 'admin.chats.index', 'label' => 'گفتگوها', 'icon' => 'chat', 'section' => 'chats', 'active' => request()->routeIs('admin.chats.*') || request()->routeIs('admin.orders.chat*')],
                    ['route' => 'admin.services.index', 'label' => 'خدمات و فرم‌ساز', 'icon' => 'layers', 'section' => 'services', 'active' => request()->routeIs('admin.services.*')],
                    ['route' => 'admin.service-categories.index', 'label' => 'دسته‌بندی خدمات', 'icon' => 'folder', 'section' => 'service-categories', 'active' => request()->routeIs('admin.service-categories.*')],
                    ['route' => 'admin.admins.index', 'label' => 'مدیران سیستم', 'icon' => 'users', 'section' => 'admins', 'active' => request()->routeIs('admin.admins.*')],
                    ['route' => 'admin.organizations.index', 'label' => 'سازمان‌ها', 'icon' => 'building', 'section' => 'organizations', 'active' => request()->routeIs('admin.organizations.*')],
                    ['route' => 'admin.coffeenets.index', 'label' => 'کافی‌نت‌ها', 'icon' => 'store', 'section' => 'coffeenets', 'active' => request()->routeIs('admin.coffeenets.*')],
                    ['route' => 'admin.operators.index', 'label' => 'کارکنان و اپراتورها', 'icon' => 'headset', 'section' => 'operators', 'active' => request()->routeIs('admin.operators.*')],
                    ['route' => 'admin.customers.index', 'label' => 'مشتریان', 'icon' => 'idcard', 'section' => 'customers', 'active' => request()->routeIs('admin.customers.*')],
                    ['route' => 'admin.withdrawals.index', 'label' => 'برداشت‌ها', 'icon' => 'wallet', 'section' => 'withdrawals', 'active' => request()->routeIs('admin.withdrawals.*')],
                    ['route' => 'admin.commissions.index', 'label' => 'قواعد کمیسیون', 'icon' => 'percent', 'section' => 'commissions', 'active' => request()->routeIs('admin.commissions.*')],
                    ['route' => 'admin.settlements.index', 'label' => 'تسویه‌ها', 'icon' => 'coins', 'section' => 'settlements', 'active' => request()->routeIs('admin.settlements.*')],
                    ['route' => 'admin.finance.index', 'label' => 'گزارش مالی', 'icon' => 'chart', 'section' => 'finance', 'active' => request()->routeIs('admin.finance.*')],
                    ['route' => 'admin.analytics.index', 'label' => 'گزارش تحلیلی', 'icon' => 'trend', 'section' => 'analytics', 'active' => request()->routeIs('admin.analytics.*')],
                    ['route' => 'admin.tickets.index', 'label' => 'تیکت‌های پشتیبانی', 'icon' => 'tickets', 'section' => 'tickets', 'active' => request()->routeIs('admin.tickets.*')],
                    ['route' => 'admin.settings.edit', 'label' => 'تنظیمات', 'icon' => 'settings', 'section' => 'settings', 'active' => request()->routeIs('admin.settings.*')],
                    ['route' => 'admin.announcements.index', 'label' => 'اطلاعیه‌ها', 'icon' => 'megaphone', 'section' => 'announcements', 'active' => request()->routeIs('admin.announcements.*')],
                    ['route' => 'admin.sms-templates.index', 'label' => 'قالب‌های پیامک', 'icon' => 'sms', 'section' => 'sms-templates', 'active' => request()->routeIs('admin.sms-templates.*')],
                    ['route' => 'admin.sms-logs.index', 'label' => 'لاگ پیامک‌ها', 'icon' => 'smslog', 'section' => 'sms-logs', 'active' => request()->routeIs('admin.sms-logs.*')],
                    ['route' => 'admin.audit.index', 'label' => 'لاگ فعالیت', 'icon' => 'history', 'section' => 'audit', 'active' => request()->routeIs('admin.audit.*')],
                    ['route' => 'admin.system.index', 'label' => 'وضعیت سیستم', 'icon' => 'shield', 'section' => 'system', 'active' => request()->routeIs('admin.system.*')],
                    ['route' => 'admin.api-docs.index', 'label' => 'مستندات API', 'icon' => 'book', 'section' => 'api-docs', 'active' => request()->routeIs('admin.api-docs.*')],
                    ['route' => 'admin.guide.index', 'label' => 'راهنمای پنل', 'icon' => 'guide', 'section' => 'guide', 'active' => request()->routeIs('admin.guide.*')],
                ], fn ($item) => \App\Policies\AdminAccessPolicy::canSection($user ?? auth()->user(), $item['section'])));
            @endphp

            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   class="nav-link flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-semibold transition-all duration-200
                          {{ $item['active'] ? 'is-active bg-amber-400/15 text-amber-200 shadow-inner' : 'text-stone-400 hover:bg-white/5 hover:text-amber-100' }}">
                    @if ($item['icon'] === 'orders')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
                    @elseif ($item['icon'] === 'layers')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12.83 2.35a2 2 0 0 0-1.66 0L2.6 7.5a1 1 0 0 0 0 1.8l8.58 5.15a2 2 0 0 0 1.66 0l8.58-5.15a1 1 0 0 0 0-1.8Z"/><path d="m6.08 10.62-3.5 2.1a1 1 0 0 0 0 1.8l8.58 5.15a2 2 0 0 0 1.66 0l8.58-5.15a1 1 0 0 0 0-1.8l-3.5-2.1"/><path d="m6.08 15.12-3.5 2.1a1 1 0 0 0 0 1.8l8.58 5.15a2 2 0 0 0 1.66 0l8.58-5.15a1 1 0 0 0 0-1.8l-3.5-2.1"/></svg>
                    @elseif ($item['icon'] === 'folder')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>
                    @elseif ($item['icon'] === 'building')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    @elseif ($item['icon'] === 'store')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M5 7V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2"/></svg>
                    @elseif ($item['icon'] === 'percent')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                    @elseif ($item['icon'] === 'coins')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
                    @elseif ($item['icon'] === 'chart')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 16h.01"/><path d="M11 12h.01"/><path d="M15 8h.01"/><path d="M19 4h.01"/></svg>
                    @elseif ($item['icon'] === 'wallet')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                    @elseif ($item['icon'] === 'trend')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                    @elseif ($item['icon'] === 'grid')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                    @elseif ($item['icon'] === 'users')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    @elseif ($item['icon'] === 'settings')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                    @elseif ($item['icon'] === 'megaphone')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                    @elseif ($item['icon'] === 'sms')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
                    @elseif ($item['icon'] === 'smslog')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 8h8"/><path d="M8 12h4"/><path d="M8 16h2"/></svg>
                    @elseif ($item['icon'] === 'tickets')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 13a9 9 0 0 1 18 0"/><path d="M21 17v2a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Zm-18 0v2a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3Z"/></svg>
                    @elseif ($item['icon'] === 'history')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    @elseif ($item['icon'] === 'shield')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
                    @elseif ($item['icon'] === 'book')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                    @elseif ($item['icon'] === 'chat')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
                    @elseif ($item['icon'] === 'idcard')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 10h2"/><path d="M16 14h2"/><path d="M6.17 15a3 3 0 0 1 5.66 0"/><circle cx="9" cy="11" r="2"/><rect x="2" y="5" width="20" height="14" rx="2"/></svg>
                    @elseif ($item['icon'] === 'headset')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm18 0h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-5Z"/><path d="M21 11a9 9 0 0 0-18 0"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/></svg>
                    @elseif ($item['icon'] === 'guide')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    @endif
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="p-3 border-t border-white/10">
            <div class="rounded-2xl bg-white/5 p-3.5 flex items-center gap-3">
                <span class="grid place-items-center size-10 rounded-xl bg-gradient-to-br from-amber-400/80 to-amber-700/80 text-amber-50 font-bold text-sm shrink-0">
                    {{ mb_substr($user->name ?? 'A', 0, 1) }}
                </span>
                <div class="min-w-0 flex-1">
                    <strong class="block text-xs font-bold text-amber-50 truncate">{{ $user->name ?? '' }}</strong>
                    <span class="block text-[10px] text-stone-400 truncate">{{ $user->email ?? '' }}</span>
                </div>
                <button type="button" class="logout-btn grid place-items-center size-9 rounded-xl text-stone-400 hover:text-rose-300 hover:bg-rose-500/10 transition-colors" title="خروج" aria-label="خروج از حساب">
                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                </button>
            </div>
        </div>
    </aside>

    {{-- پوشش موبایل --}}
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-30 hidden lg:hidden" aria-hidden="true"></div>

    {{-- ================== محتوا ================== --}}
    <div class="flex-1 flex flex-col min-w-0">

        <header class="adm-topbar sticky top-0 z-20 bg-white/85 backdrop-blur border-b border-stone-200/80">
            <div class="px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" id="sidebar-toggle" class="lg:hidden grid place-items-center size-10 rounded-xl border border-stone-200 text-stone-500 hover:bg-stone-50" aria-label="باز کردن منو">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </button>
                    <div class="min-w-0">
                        <h1 class="adm-page-title text-base font-extrabold tracking-tight truncate">@yield('page-title', 'داشبورد')</h1>
                        <nav class="adm-crumb mt-0.5" aria-label="مسیر">@yield('breadcrumb', 'پنل مدیریت کل')</nav>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    {{-- زنگ اعلان (فاز ۱۰) --}}
                    @include('back.partials.notif-bell')
                    {{-- سوییچ تم روشن/تاریک --}}
                    <button type="button" class="theme-toggle" data-theme-toggle aria-label="تغییر تم روشن/تاریک" title="حالت روشن/تاریک">
                        <svg class="tt-icon tt-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                        <svg class="tt-icon tt-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                    </button>
                    <span class="hidden sm:inline-flex items-center gap-2 badge bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="relative flex size-2"><span class="absolute inline-flex size-full rounded-full bg-emerald-400 opacity-60 animate-ping"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span></span>
                        سیستم آنلاین
                    </span>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 sm:px-6 py-6">
            @yield('content')
        </main>

        <footer class="mt-auto border-t border-stone-200/80 bg-white/60">
            <div class="px-6 h-12 flex items-center justify-between text-[11px] text-stone-400">
                <span class="font-medium">© {{ jdate(now())->format('Y') }} کافی‌نت آنلاین — پنل مدیریت کل</span>
                <span class="font-mono" dir="ltr">Laravel {{ app()->version() }}</span>
            </div>
        </footer>
    </div>
</div>

{{-- اسکریپت‌های پایه پنل (فایل‌های جدا — بدون Node) --}}
<script src="{{ asset('assets/js/vendor/jquery.min.js') }}?v=10"></script>
<script src="{{ asset('assets/js/vendor/pusher.min.js') }}?v=1"></script>
<script src="{{ asset('assets/js/realtime.js') }}?v=2" data-rt-config='@json(app(\App\Services\Realtime\PusherService::class)->clientConfig(auth()->user()))'></script>
<script src="{{ asset('back/assets/js/core.js') }}?v=12"></script>
<script src="{{ asset('back/assets/js/ui.js') }}?v=10"></script>
<script src="{{ asset('back/assets/js/pages/layout.js') }}?v=12"></script>
{{-- اطلاعیه‌های پنل (فاز ۱۵) --}}
<script src="{{ asset('back/assets/js/pages/panel-announcements.js') }}?v=15"></script>
<script src="{{ asset('back/assets/js/pages/notifications.js') }}?v=14"></script>

{{-- دیت‌پیکر شمسی — بدون وابستگی (vanilla) --}}
<script src="{{ asset('assets/js/jalali-datepicker.js') }}?v=2"></script>

{{-- اسکریپت‌های اختصاصی هر صفحه --}}
@stack('scripts')
</body>
</html>
