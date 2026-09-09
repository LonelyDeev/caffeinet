<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- تم ذخیره‌شده قبل از رندر اعمال می‌شود (ضد-FOUC) --}}
    <script src="{{ asset('assets/js/theme-boot.js') }}?v=1"></script>

    <title>@yield('title', 'پنل کافی‌نت') — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap">

    {{-- استایل و اسکریپت — کاملاً بدون Node / بدون بیلد --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=10">
    <link rel="stylesheet" href="{{ asset('assets/css/panel-ui.css') }}?v=14">
    <link rel="stylesheet" href="{{ asset('assets/css/pages/net-org.css') }}?v=10">
    {{-- زنگ اعلان (فاز ۱۰) — باید قبل از theme باشد --}}
    <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}?v=13">
    {{-- سیستم تم روشن/تاریک (فاز ۱۰) — باید آخرین CSS باشد تا برنده بماند --}}
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v=10">
    {{-- تقویم/دیت‌پیکر شمسی (CNJdp) --}}
    <link rel="stylesheet" href="{{ asset('assets/css/jalali-datepicker.css') }}?v=2">
    {{-- استایل‌های اختصاصی صفحات (push با @push('styles')) --}}
    @stack('styles')
</head>
<body class="font-sans antialiased bg-stone-100 text-stone-800 selection:bg-amber-200 selection:text-amber-950"
      data-logout-url="/coffeenet/logout" data-login-url="/coffeenet/login"
      data-nb-badge="{{ route('coffeenet.notifications.badge') }}"
      data-nb-data="{{ route('coffeenet.notifications.data') }}"
      data-nb-read="{{ route('coffeenet.notifications.read') }}">

<div class="min-h-screen flex">

    {{-- ================== سایدبار ================== --}}
    <aside id="panel-sidebar" class="fixed lg:sticky top-0 h-screen w-72 shrink-0 z-40 -translate-x-full lg:translate-x-0 transition-transform duration-300 bg-gradient-to-b from-[#2b1a0e] via-[#241509] to-[#170d05] text-stone-200 flex flex-col">

        <div class="px-5 py-5 border-b border-white/10 flex items-center gap-3">
            <span class="grid place-items-center size-10 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-700 shadow-lg shadow-black/40 shrink-0">
                <svg class="size-5 text-amber-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><line x1="6" x2="6" y1="2" y2="4"/><line x1="10" x2="10" y1="2" y2="4"/><line x1="14" x2="14" y1="2" y2="4"/>
                </svg>
            </span>
            <div class="leading-tight min-w-0">
                <strong class="block text-sm font-extrabold tracking-tight text-amber-50 truncate">{{ $coffeenet->name }}</strong>
                <span class="block text-[11px] text-amber-200/60 font-medium">
                    {{ $coffeenet->organization?->name ? 'زیرمجموعه '.$coffeenet->organization->name : 'کافی‌نت مستقل' }}
                </span>
            </div>
        </div>

        @php
            $base = ['coffeenet' => $coffeenet->id];
            $nav = [
                ['route' => 'coffeenet.dashboard', 'params' => $base, 'label' => 'داشبورد', 'icon' => 'grid', 'match' => 'coffeenet.dashboard'],
                ['route' => 'coffeenet.orders.index', 'params' => $base, 'label' => 'سفارش‌ها', 'icon' => 'orders', 'match' => 'coffeenet.orders.index|coffeenet.orders.data|coffeenet.orders.broadcast.data|coffeenet.orders.accept|coffeenet.orders.operators|coffeenet.orders.operator'],
                ['route' => 'coffeenet.chats.index', 'params' => $base, 'label' => 'گفتگوها', 'icon' => 'chat', 'match' => 'coffeenet.chats.*|coffeenet.orders.chat*'],
                ['route' => 'coffeenet.staff.index', 'params' => $base, 'label' => 'کارمندان', 'icon' => 'users', 'match' => 'coffeenet.staff.*'],
                ['route' => 'coffeenet.salaries.index', 'params' => $base, 'label' => 'حقوق و دستمزد', 'icon' => 'coins', 'match' => 'coffeenet.salaries.*'],
                ['route' => 'coffeenet.wallet.index', 'params' => $base, 'label' => 'کیف پول', 'icon' => 'wallet', 'match' => 'coffeenet.wallet.*'],
                ['route' => 'coffeenet.withdrawals.index', 'params' => $base, 'label' => 'برداشت‌ها', 'icon' => 'withdraw', 'match' => 'coffeenet.withdrawals.*'],
                ['route' => 'coffeenet.tickets.index', 'params' => $base, 'label' => 'تیکت‌های پشتیبانی', 'icon' => 'tickets', 'match' => 'coffeenet.tickets.*'],
                ['route' => 'coffeenet.settings.index', 'params' => $base, 'label' => 'تنظیمات', 'icon' => 'cog', 'match' => 'coffeenet.settings.*'],
                ['route' => 'coffeenet.guide.index', 'params' => $base, 'label' => 'راهنمای پنل', 'icon' => 'guide', 'match' => 'coffeenet.guide.*'],
            ];
        @endphp

        <nav class="no-nav flex-1 overflow-y-auto px-3 py-4 space-y-1" data-tone="net" aria-label="ناوبری پنل کافی‌نت">
            @foreach ($nav as $item)
                <a href="{{ route($item['route'], $item['params']) }}"
                   class="no-nav-link {{ request()->routeIs($item['match']) ? 'no-nav-link--on' : '' }}">
                    @if ($item['icon'] === 'orders')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
                    @elseif ($item['icon'] === 'grid')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                    @elseif ($item['icon'] === 'users')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    @elseif ($item['icon'] === 'coins')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
                    @elseif ($item['icon'] === 'wallet')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                    @elseif ($item['icon'] === 'chat')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
                    @elseif ($item['icon'] === 'tickets')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 13a9 9 0 0 1 18 0"/><path d="M21 17v2a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Zm-18 0v2a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3Z"/></svg>
                    @elseif ($item['icon'] === 'withdraw')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                    @elseif ($item['icon'] === 'guide')
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    @else
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                    @endif
                    {{ $item['label'] }}
                </a>
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

        <header class="p-topbar p-topbar--net sticky top-0 z-20 bg-white/85 backdrop-blur border-b border-stone-200/80">
            <div class="px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" id="sidebar-toggle" class="lg:hidden grid place-items-center size-10 rounded-xl border border-stone-200 text-stone-500 hover:bg-stone-50" aria-label="باز کردن منو">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </button>
                    <div class="min-w-0">
                        <h1 class="p-page-title text-base font-extrabold tracking-tight truncate">@yield('page-title', 'داشبورد')</h1>
                        <nav class="p-crumb mt-0.5" aria-label="مسیر">@yield('breadcrumb', 'پنل کافی‌نت')</nav>
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
                    @php $netOk = $coffeenet->status === \App\Enums\CoffeenetStatus::Approved; @endphp
                    <span class="no-live-pill hidden sm:inline-flex" @if (! $netOk) data-tone="warn" @endif>
                        <span class="ui-dot {{ $netOk ? 'text-emerald-500' : 'text-amber-500' }}"></span>
                        {{ $netOk ? 'سیستم آنلاین' : $coffeenet->status->label() }}
                    </span>
                    @if (session()->has('coffeenet_id') && auth()->user()->staffAssignments()->where('is_active', true)->count() > 1)
                        <a href="{{ route('coffeenet.choose') }}" class="badge bg-stone-50 text-stone-500 border border-stone-200 hover:bg-stone-100 transition-colors" title="جابجایی بین کافی‌نت‌ها">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
                            تغییر کافی‌نت
                        </a>
                    @endif
                </div>
            </div>
        </header>

        @if ($coffeenet->status !== \App\Enums\CoffeenetStatus::Approved)
            <div class="px-4 sm:px-6 pt-4">
                <div class="ui-note animate-fade-up">
                    <strong>توجه:</strong> وضعیت کافی‌نت «{{ $coffeenet->status->label() }}» است؛ تا تعیین وضعیت نهایی توسط مدیریت، امکانات پنل محدود است.
                </div>
            </div>
        @endif

        <main class="flex-1 px-4 sm:px-6 py-6">
            @yield('content')
        </main>

        <footer class="mt-auto border-t border-stone-200/80 bg-white/60">
            <div class="px-6 h-12 flex items-center justify-between text-[11px] text-stone-400">
                <span class="font-medium">© {{ jdate(now())->format('Y') }} کافی‌نت آنلاین — پنل کافی‌نت</span>
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
