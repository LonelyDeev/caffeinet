<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- تم ذخیره‌شده قبل از رندر اعمال می‌شود (ضد-FOUC) --}}
    <script src="{{ asset('assets/js/theme-boot.js') }}?v=1"></script>

    <title>@yield('title', 'پنل سازمان') — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap">

    {{-- استایل و اسکریپت — کاملاً بدون Node / بدون بیلد --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=10">
    <link rel="stylesheet" href="{{ asset('assets/css/panel-ui.css') }}?v=14">
    <link rel="stylesheet" href="{{ asset('assets/css/pages/net-org.css') }}?v=10">
    {{-- زنگ اعلان (فاز ۱۰) — قبل از theme --}}
    <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}?v=13">
    {{-- سیستم تم روشن/تاریک (فاز ۱۰) — باید آخرین CSS باشد تا برنده بماند --}}
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v=10">
    {{-- تقویم/دیت‌پیکر شمسی (CNJdp) --}}
    <link rel="stylesheet" href="{{ asset('assets/css/jalali-datepicker.css') }}?v=2">
    {{-- استایل‌های اختصاصی صفحات (push با @push('styles')) --}}
    @stack('styles')
</head>
<body class="font-sans antialiased bg-stone-100 text-stone-800 selection:bg-amber-200 selection:text-amber-950"
      data-logout-url="/organization/logout" data-login-url="/organization/login"
      data-nb-badge="{{ route('org.notifications.badge') }}"
      data-nb-data="{{ route('org.notifications.data') }}"
      data-nb-read="{{ route('org.notifications.read') }}">

<div class="min-h-screen flex">

    {{-- ================== سایدبار ================== --}}
    <aside id="panel-sidebar" class="fixed lg:sticky top-0 h-screen w-72 shrink-0 z-40 -translate-x-full lg:translate-x-0 transition-transform duration-300 bg-gradient-to-b from-[#1a2e28] via-[#152620] to-[#0f1c17] text-stone-200 flex flex-col">

        <div class="px-5 py-5 border-b border-white/10 flex items-center gap-3">
            <span class="grid place-items-center size-10 rounded-2xl bg-gradient-to-br from-teal-400 to-teal-700 shadow-lg shadow-black/30 shrink-0">
                <svg class="size-5 text-teal-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect width="4" height="16" x="4" y="2" rx="1"/><rect width="4" height="16" x="10" y="2" rx="1"/><path d="M20 4v14"/><path d="M2 20h20"/>
                </svg>
            </span>
            <div class="leading-tight min-w-0">
                <strong class="block text-sm font-extrabold tracking-tight text-teal-50 truncate">{{ $organization->name }}</strong>
                <span class="block text-[11px] text-teal-200/60 font-medium">پنل سازمان</span>
            </div>
        </div>

        <nav class="no-nav flex-1 overflow-y-auto px-3 py-4 space-y-1" data-tone="org" aria-label="ناوبری پنل سازمان">
            @php
                $nav = [
                    ['route' => 'org.dashboard', 'label' => 'داشبورد', 'icon' => 'grid', 'active' => request()->routeIs('org.dashboard')],
                    ['route' => 'org.wallet.index', 'label' => 'کیف پول', 'icon' => 'wallet', 'active' => request()->routeIs('org.wallet.*')],
                    ['route' => 'org.withdrawals.index', 'label' => 'برداشت‌ها', 'icon' => 'withdraw', 'active' => request()->routeIs('org.withdrawals.*')],
                    ['route' => 'org.coffeenets.index', 'label' => 'کافی‌نت‌های من', 'icon' => 'store', 'active' => request()->routeIs('org.coffeenets.*')],
                    ['route' => 'org.guide.index', 'label' => 'راهنمای پنل', 'icon' => 'guide', 'active' => request()->routeIs('org.guide.*')],
                ];
            @endphp

            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   class="nav-link no-nav-link {{ $item['active'] ? 'no-nav-link--on' : '' }}">
                    @if ($item['icon'] === 'grid')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                    @elseif ($item['icon'] === 'wallet')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                    @elseif ($item['icon'] === 'withdraw')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                    @elseif ($item['icon'] === 'guide')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    @else
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/></svg>
                    @endif
                    {{ $item['label'] }}
                </a>
            @endforeach

            <div class="pt-4 px-3.5 pb-2">
                <p class="text-[10px] font-bold text-stone-500 tracking-wider">فازهای بعدی</p>
            </div>

            @foreach ([
                ['label' => 'گزارش‌های مالی', 'phase' => 'فاز ۸'],
                ['label' => 'تیکت پشتیبانی', 'phase' => 'فاز ۱۰'],
            ] as $soon)
                <div class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm text-stone-500 cursor-not-allowed" title="{{ $soon['phase'] }}">
                    <span class="size-2 rounded-full bg-stone-600 shrink-0"></span>
                    {{ $soon['label'] }}
                    <span class="ms-auto badge bg-white/5 text-stone-500 border border-white/5">{{ $soon['phase'] }}</span>
                </div>
            @endforeach
        </nav>

        <div class="p-3 border-t border-white/10">
            <div class="rounded-2xl bg-white/5 p-3.5 flex items-center gap-3">
                <span class="grid place-items-center size-10 rounded-xl bg-gradient-to-br from-teal-400/80 to-teal-700/80 text-teal-50 font-bold text-sm shrink-0">
                    {{ mb_substr(auth()->user()->name ?? 'س', 0, 1) }}
                </span>
                <div class="min-w-0 flex-1">
                    <strong class="block text-xs font-bold text-teal-50 truncate">{{ auth()->user()->full_name }}</strong>
                    <span class="block text-[10px] text-stone-400 truncate" dir="ltr">{{ auth()->user()->email }}</span>
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

        <header class="p-topbar p-topbar--org sticky top-0 z-20 bg-white/85 backdrop-blur border-b border-stone-200/80">
            <div class="px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" id="sidebar-toggle" class="lg:hidden grid place-items-center size-10 rounded-xl border border-stone-200 text-stone-500 hover:bg-stone-50" aria-label="باز کردن منو">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </button>
                    <div class="min-w-0">
                        <h1 class="p-page-title text-base font-extrabold tracking-tight truncate">@yield('page-title', 'داشبورد')</h1>
                        <nav class="p-crumb mt-0.5" aria-label="مسیر">@yield('breadcrumb', 'پنل سازمان')</nav>
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
                    <span class="no-live-pill hidden sm:inline-flex" @if ($organization->status !== \App\Enums\OrganizationStatus::Approved) data-tone="warn" @endif>
                        <span class="ui-dot {{ $organization->status === \App\Enums\OrganizationStatus::Approved ? 'text-emerald-500' : 'text-amber-500' }}"></span>
                        {{ $organization->status === \App\Enums\OrganizationStatus::Approved ? 'سیستم آنلاین' : $organization->status->label() }}
                    </span>
                </div>
            </div>
        </header>

        @if ($organization->status !== \App\Enums\OrganizationStatus::Approved)
            <div class="px-4 sm:px-6 pt-4">
                <div class="ui-note animate-fade-up">
                    <strong>توجه:</strong> وضعیت سازمان شما «{{ $organization->status->label() }}» است.
                    تا تأیید/فعال‌سازی توسط مدیریت کل، برخی امکانات (از جمله برداشت) ممکن است محدود باشد.
                    @if ($organization->note)<span class="block mt-1">یادداشت مدیریت: {{ $organization->note }}</span>@endif
                </div>
            </div>
        @endif

        <main class="flex-1 px-4 sm:px-6 py-6">
            @yield('content')
        </main>

        <footer class="mt-auto border-t border-stone-200/80 bg-white/60">
            <div class="px-6 h-12 flex items-center justify-between text-[11px] text-stone-400">
                <span class="font-medium">© {{ jdate(now())->format('Y') }} کافی‌نت آنلاین — پنل سازمان</span>
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
<script src="{{ asset('back/assets/js/pages/layout.js') }}?v=11"></script>
<script src="{{ asset('back/assets/js/pages/notifications.js') }}?v=14"></script>

{{-- دیت‌پیکر شمسی — بدون وابستگی (vanilla) --}}
<script src="{{ asset('assets/js/jalali-datepicker.js') }}?v=2"></script>

{{-- اسکریپت‌های اختصاصی هر صفحه --}}
@stack('scripts')
</body>
</html>
