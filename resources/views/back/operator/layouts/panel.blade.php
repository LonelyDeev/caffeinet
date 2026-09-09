<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- تم ذخیره‌شده قبل از رندر اعمال می‌شود (ضد-FOUC) --}}
    <script src="{{ asset('assets/js/theme-boot.js') }}?v=1"></script>

    <title>@yield('title', 'پنل اپراتور') — {{ config('app.name') }}</title>

    {{-- PWA: مانیفست + آیکون‌ها + ثبت Service Worker (فاز ۱۴) --}}
    @include('partials.pwa')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap">

    {{-- استایل و اسکریپت — کاملاً بدون Node / بدون بیلد --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=12">
    <link rel="stylesheet" href="{{ asset('assets/css/panel-ui.css') }}?v=14">
    <link rel="stylesheet" href="{{ asset('assets/css/pages/operator.css') }}?v=10">
    {{-- زنگ اعلان (فاز ۱۰) — قبل از theme --}}
    <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}?v=13">
    {{-- سیستم تم روشن/تاریک (فاز ۱۰) — باید آخرین CSS باشد تا برنده بماند --}}
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v=10">
    {{-- تقویم/دیت‌پیکر شمسی (CNJdp) --}}
    <link rel="stylesheet" href="{{ asset('assets/css/jalali-datepicker.css') }}?v=2">
    {{-- استایل‌های اختصاصی صفحات (push با @push('styles')) --}}
    @stack('styles')

    @php
        // دسترسی‌های این اپراتور در کافی‌نت جاری (از middleware شیر شده)
        $canAll = $operatorAssignment->hasOperatorPermission('orders.view');
        $canOwn = $operatorAssignment->hasOperatorPermission('orders.view.own');
        $canViewOrders = $canAll || $canOwn;
        $canAccept = $operatorAssignment->hasOperatorPermission('orders.accept');
        $canDashboard = $operatorAssignment->hasOperatorPermission('dashboard.access');
        $ticketsAllowed = auth()->user()->can('tickets.view');
    @endphp
</head>
<body class="font-sans antialiased bg-stone-100 text-stone-800 selection:bg-amber-200 selection:text-amber-950"
      data-logout-url="/operator/logout" data-login-url="/operator/login"
      data-chat-badge-url="{{ route('operator.chat.badge') }}"
      data-nb-badge="{{ route('operator.notifications.badge') }}"
      data-nb-data="{{ route('operator.notifications.data') }}"
      data-nb-read="{{ route('operator.notifications.read') }}"
      @if ($canAccept) data-requests-badge-url="{{ route('operator.requests.badge') }}" @endif>

<div class="min-h-screen flex">

    {{-- ================== سایدبار ================== --}}
    <aside id="panel-sidebar" class="fixed lg:sticky top-0 h-screen w-72 shrink-0 z-40 translate-x-full lg:translate-x-0 transition-transform duration-300 bg-gradient-to-b from-[#2b1a0e] via-[#241509] to-[#170d05] text-stone-200 flex flex-col">

        <div class="px-5 py-5 border-b border-white/10 flex items-center gap-3">
            <span class="grid place-items-center size-10 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-700 shadow-lg shadow-black/40 shrink-0">
                <svg class="size-5 text-amber-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>
                </svg>
            </span>
            <div class="leading-tight min-w-0">
                <strong class="block text-sm font-extrabold tracking-tight text-amber-50 truncate">{{ $coffeenet->name }}</strong>
                <span class="block text-[11px] text-amber-200/60 font-medium">پنل اپراتور</span>
            </div>
        </div>

        @php
            $operatorNav = [
                ['route' => 'operator.dashboard', 'label' => 'داشبورد', 'icon' => 'grid', 'match' => 'operator.dashboard', 'show' => true],
                ['route' => 'operator.requests.index', 'label' => 'درخواست‌های مشتری', 'icon' => 'inbox', 'match' => 'operator.requests.*', 'show' => $canAccept],
                ['route' => 'operator.orders.index', 'label' => $canAll ? 'سفارش‌های کافی‌نت' : 'سفارش‌های من', 'icon' => 'orders', 'match' => 'operator.orders.*', 'show' => $canViewOrders],
                ['route' => 'operator.chat.index', 'label' => 'گفتگوها', 'icon' => 'chat', 'match' => 'operator.chat.*,operator.orders.chat', 'show' => $canViewOrders, 'badge' => true],
                ['route' => 'operator.earnings.index', 'label' => 'درآمد و کیف پول', 'icon' => 'wallet', 'match' => 'operator.earnings.*', 'show' => true],
                ['route' => 'operator.tickets.index', 'label' => 'تیکت‌های پشتیبانی', 'icon' => 'tickets', 'match' => 'operator.tickets.*', 'show' => $ticketsAllowed],
                ['route' => 'operator.guide.index', 'label' => 'راهنمای پنل', 'icon' => 'guide', 'match' => 'operator.guide.*', 'show' => true],
            ];
        @endphp

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1" aria-label="ناوبری پنل اپراتور">
            @foreach ($operatorNav as $item)
                @if ($item['show'])
                    <a href="{{ route($item['route']) }}"
                       class="op-nav-link {{ request()->routeIs($item['match']) ? 'op-nav-active' : '' }} flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-semibold">
                        @if ($item['icon'] === 'orders')
                            <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
                        @elseif ($item['icon'] === 'inbox')
                            <span class="relative flex size-4.5 shrink-0 items-center justify-center">
                                <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                                <span class="hidden absolute -top-1 -left-1 flex size-2.5 rounded-full bg-amber-400 animate-pulse" id="requestsPulseDot" aria-hidden="true"></span>
                            </span>
                        @elseif ($item['icon'] === 'chat')
                            <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                        @elseif ($item['icon'] === 'tickets')
                            <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 13a9 9 0 0 1 18 0"/><path d="M21 17v2a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Zm-18 0v2a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3Z"/></svg>
                        @elseif ($item['icon'] === 'wallet')
                            <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                        @elseif ($item['icon'] === 'guide')
                            <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        @else
                            <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                        @endif
                        {{ $item['label'] }}
                        @if ($item['icon'] === 'inbox')
                            <span class="cchat-badge ms-auto" id="requestsCountBadge" aria-label="درخواست‌های در انتظار پذیرش"></span>
                        @endif
                        @if (($item['badge'] ?? false))
                            <span class="cchat-badge ms-auto" id="chatUnreadBadge" aria-label="پیام‌های خوانده‌نشده"></span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="p-3 border-t border-white/10">
            <div class="rounded-2xl bg-white/5 p-3.5 flex items-center gap-3">
                <span class="grid place-items-center size-10 rounded-xl bg-gradient-to-br from-amber-400/80 to-amber-700/80 text-amber-50 font-bold text-sm shrink-0">
                    {{ mb_substr(auth()->user()->name ?? 'ک', 0, 1) }}
                </span>
                <div class="min-w-0 flex-1">
                    <strong class="block text-xs font-bold text-amber-50 truncate">{{ auth()->user()->full_name }}</strong>
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

        <header class="op-topbar sticky top-0 z-20 bg-white/85 backdrop-blur border-b border-stone-200/80">
            <div class="px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" id="sidebar-toggle" class="lg:hidden grid place-items-center size-10 rounded-xl border border-stone-200 text-stone-500 hover:bg-stone-50" aria-label="باز کردن منو">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </button>
                    <div class="min-w-0">
                        <h1 class="op-page-title text-base font-extrabold tracking-tight truncate">@yield('page-title', 'داشبورد')</h1>
                        <nav class="op-crumb mt-0.5" aria-label="مسیر">@yield('breadcrumb', 'پنل اپراتور')</nav>
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
                    <span class="hidden sm:inline-flex items-center gap-2 op-status-pill" title="وضعیت اتصال">
                        <span class="relative flex size-2"><span class="absolute inline-flex size-full rounded-full bg-emerald-400 opacity-60 animate-ping"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span></span>
                        {{ $operatorAssignment->position->label() }} آنلاین
                    </span>
                </div>
            </div>
        </header>

        @if (! $canDashboard)
            <div class="px-4 sm:px-6 pt-4">
                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-xs leading-6 text-amber-800">
                    <strong>توجه:</strong> دسترسی «داشبورد» برای شما فعال نیست؛ نمای زیر محدود است.
                </div>
            </div>
        @endif

        <main class="flex-1 px-4 sm:px-6 py-6">
            @yield('content')
        </main>

        <footer class="mt-auto border-t border-stone-200/80 bg-white/60">
            <div class="px-6 h-12 flex items-center justify-between text-[11px] text-stone-400">
                <span class="font-medium">© {{ jdate(now())->format('Y') }} کافی‌نت آنلاین — پنل اپراتور</span>
                <span class="font-mono" dir="ltr">Laravel {{ app()->version() }}</span>
            </div>
        </footer>
    </div>
</div>

{{-- استایل گفتگو (فاز ۷ — مشترک پنل و اپ) --}}
<link rel="stylesheet" href="{{ asset('assets/css/chat.css') }}?v=14">

{{-- اسکریپت‌های پایه پنل (فایل‌های جدا — بدون Node) --}}
<script src="{{ asset('assets/js/vendor/jquery.min.js') }}?v=10"></script>
<script src="{{ asset('assets/js/vendor/pusher.min.js') }}?v=1"></script>
<script src="{{ asset('assets/js/realtime.js') }}?v=2" data-rt-config='@json(app(\App\Services\Realtime\PusherService::class)->clientConfig(auth()->user()))'></script>
<script src="{{ asset('back/assets/js/core.js') }}?v=12"></script>
<script src="{{ asset('back/assets/js/ui.js') }}?v=10"></script>
<script src="{{ asset('back/assets/js/pages/layout.js') }}?v=12"></script>
<script src="{{ asset('back/assets/js/pages/notifications.js') }}?v=14"></script>

{{-- دیت‌پیکر شمسی — بدون وابستگی (vanilla) --}}
<script src="{{ asset('assets/js/jalali-datepicker.js') }}?v=2"></script>

{{-- اسکریپت‌های اختصاصی هر صفحه --}}
@stack('scripts')
</body>
</html>
