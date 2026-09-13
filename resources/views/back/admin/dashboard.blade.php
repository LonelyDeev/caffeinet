@extends('back.layouts.panel', ['user' => $user])

@section('title', 'داشبورد')
@section('page-title', 'داشبورد مدیریت')
@section('breadcrumb', 'داشبورد')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/analytics.css') }}?v=9">
@endpush

@section('content')

    {{-- دادهٔ نمودارها برای JS --}}
    <div id="page-data" hidden data-payload="{{ json_encode($chartData) }}"></div>

    {{-- کارت‌های آماری --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        @php
            $cards = [
                ['label' => 'کاربران کل', 'value' => $stats['users_total'], 'hint' => '+'.$stats['users_new_week'].' این هفته', 'icon' => 'users', 'tone' => '', 'orb' => 'amber', 'hint_cls' => 'text-amber-600'],
                ['label' => 'سازمان‌ها', 'value' => $stats['organizations_total'], 'hint' => $stats['organizations_pending'].' در انتظار بررسی', 'icon' => 'building', 'tone' => 'teal', 'orb' => 'teal', 'hint_cls' => 'text-teal-600'],
                ['label' => 'کافی‌نت‌ها', 'value' => $stats['coffeenets_total'], 'hint' => $stats['coffeenets_pending'].' در انتظار تأیید', 'icon' => 'store', 'tone' => 'rose', 'orb' => 'rose', 'hint_cls' => 'text-rose-500'],
                ['label' => 'خدمات فعال', 'value' => $stats['services_active'], 'hint' => 'کاتالوگ و فرم‌ساز', 'icon' => 'layers', 'tone' => 'sky', 'orb' => 'teal', 'hint_cls' => 'text-sky-600'],
            ];
        @endphp

        @foreach ($cards as $i => $card)
            <div class="card ui-lift adm-stat p-5 animate-fade-up delay-{{ $i + 1 }}">
                <span class="ui-orb" data-tone="{{ $card['orb'] }}" data-pos="tr" aria-hidden="true"></span>
                <div class="flex items-start justify-between">
                    <div class="adm-stat-chip-wrap">
                        <p class="text-xs font-semibold text-stone-500">{{ $card['label'] }}</p>
                        <p class="adm-stat-value mt-2 text-3xl font-extrabold tabular-nums text-stone-800" data-count="{{ (int) $card['value'] }}">۰</p>
                        <p class="adm-stat-hint mt-1.5 text-[11px] {{ $card['hint_cls'] }}">{{ $card['hint'] }}</p>
                    </div>
                    <span class="ui-chip" data-tone="{{ $card['tone'] }}" aria-hidden="true">
                        @if ($card['icon'] === 'users')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        @elseif ($card['icon'] === 'building')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="4" height="16" x="4" y="2" rx="1"/><rect width="4" height="16" x="10" y="2" rx="1"/><path d="M20 4v14"/><path d="M2 20h20"/></svg>
                        @elseif ($card['icon'] === 'store')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 12.18-9.17 4.16a2 2 0 0 1-1.66 0L2 12.18"/><path d="m22 17.18-9.17 4.16a2 2 0 0 1-1.66 0L2 17.18"/></svg>
                        @endif
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- در انتظار تعیین‌تکلیف (فاز ۲ + فاز ۶) --}}
    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ($pendingItems as $i => $item)
            <a href="{{ $item['url'] }}" data-hot="{{ $item['count'] > 0 ? '1' : '0' }}"
               class="card ui-lift adm-pending p-4 flex items-center gap-3.5 animate-fade-up delay-{{ min($i + 1, 3) }} group">
                <span class="ui-chip {{ $item['count'] > 0 ? '' : 'opacity-70' }}" data-tone="{{ $item['count'] > 0 ? 'ok' : 'stone' }}" aria-hidden="true">
                    @if ($item['icon'] === 'orders')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
                    @elseif ($item['icon'] === 'building')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="4" height="16" x="4" y="2" rx="1"/><rect width="4" height="16" x="10" y="2" rx="1"/><path d="M20 4v14"/><path d="M2 20h20"/></svg>
                    @elseif ($item['icon'] === 'store')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-stone-500 truncate">{{ $item['title'] }}</p>
                    <p class="mt-1 text-2xl font-extrabold tabular-nums {{ $item['count'] > 0 ? 'text-amber-600' : 'text-stone-300' }}">{{ $item['count'] }}</p>
                </div>
                <svg class="adm-pending-arrow size-4 text-stone-300 group-hover:text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            </a>
        @endforeach
    </div>

    {{-- نمای تحلیلی ۱۴ روز اخیر (فاز ۹) --}}
    <div class="mt-6 grid grid-cols-1 xl:grid-cols-3 gap-5">

        <section class="card ui-lift an-card animate-fade-up delay-3 xl:col-span-2">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                        </span>
                        روند ۱۴ روز اخیر
                    </h2>
                    <p class="an-card-sub">سفارش‌های ثبت‌شده و حجم پرداخت — روزهای شمسی</p>
                </div>
                <div class="an-card-tools">
                    <a href="{{ route('admin.analytics.index') }}" class="an-export">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                        گزارش تحلیلی کامل
                    </a>
                </div>
            </div>
            <div class="an-chart">
                <canvas id="dash-daily" role="img" aria-label="نمودار روند ۱۴ روز اخیر سفارش‌ها"></canvas>
            </div>
        </section>

        <section class="card ui-lift an-card animate-fade-up delay-4">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12A9 9 0 1 1 12 3a9 9 0 0 1 9 9Z"/><path d="M12 3v9l6 4"/></svg>
                        </span>
                        وضعیت سفارش‌ها
                    </h2>
                    <p class="an-card-sub">۱۴ روز اخیر — {{ fa_number($chartData['paid_total']) }} پرداخت موفق</p>
                </div>
            </div>
            <div class="an-chart">
                <canvas id="dash-status" role="img" aria-label="نمودار وضعیت سفارش‌های ۱۴ روز اخیر"></canvas>
            </div>
        </section>
    </div>

    <div class="mt-6 grid lg:grid-cols-3 gap-5">

        {{-- آخرین فعالیت‌ها --}}
        <div class="lg:col-span-2 card ui-lift animate-fade-up delay-3 overflow-hidden">
            <div class="adm-card-head">
                <h2 class="adm-card-title">
                    <span class="adm-card-chip" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    </span>
                    آخرین فعالیت‌ها
                </h2>
                <a href="{{ route('admin.audit.index') }}" class="adm-link-more">مشاهده همه ←</a>
            </div>
            <ul class="adm-feed ui-stagger divide-y divide-stone-100 max-h-96 overflow-y-auto">
                @forelse ($recentAudits as $log)
                    <li class="px-5 py-3.5 flex items-center gap-3.5 hover:bg-stone-50/60 transition-colors">
                        <span class="grid place-items-center size-9 rounded-xl bg-gradient-to-br from-amber-400/70 to-amber-600/70 text-white font-bold text-xs shrink-0">
                            {{ mb_substr($log->user?->name ?? 'س', 0, 1) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-stone-700">
                                {{ $log->user?->full_name ?? 'سیستم' }}
                                <span class="font-mono text-[10px] text-stone-400" dir="ltr">{{ $log->action }}</span>
                            </p>
                            <p class="text-[11px] text-stone-500 truncate mt-0.5">{{ $log->description ?? '—' }}</p>
                        </div>
                        <time class="text-[10px] text-stone-400 shrink-0">{{ $log->created_at?->diffForHumans(now(), ['locale' => 'fa']) }}</time>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-xs text-stone-400">هنوز فعالیتی ثبت نشده است.</li>
                @endforelse
            </ul>
        </div>

        {{-- وضعیت سیستم --}}
        <div class="card ui-lift p-5 animate-fade-up delay-4 space-y-4 relative overflow-hidden">
            <span class="ui-orb" data-tone="amber" data-pos="bl" aria-hidden="true"></span>
            <h2 class="text-sm font-extrabold text-stone-700">وضعیت زیرساخت</h2>

            @php
                $sys = [
                    ['نسخه PHP', 'PHP '.PHP_VERSION, true],
                    ['فریمورک', 'Laravel '.app()->version(), true],
                    ['دیتابیس', config('database.default') === 'sqlite' ? 'SQLite (توسعه) — آماده MySQL' : ucfirst(config('database.default')), true],
                    ['پکیج پرداخت', 'shetabit/payment ✓', true],
                    ['پکیج پیامک', 'درایور log + فراز اس‌ام‌اس', true],
                    ['دیتای جغرافیا', \App\Models\Province::count().' استان · '.\App\Models\City::count().' شهرستان', true],
                ];
            @endphp

            <div class="ui-stagger space-y-2.5 relative">
                @foreach ($sys as $row)
                    <div class="flex items-center justify-between text-xs rounded-xl bg-stone-50/70 border border-stone-100 px-3.5 py-2.5">
                        <span class="text-stone-500 font-semibold">{{ $row[0] }}</span>
                        <span class="text-stone-700 font-medium" dir="auto">{{ $row[1] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="pt-2 border-t border-stone-100 relative">
                <p class="ui-note">
                    پنل‌های سازمان، کافی‌نت و اپراتور + چت زنده مشتری↔اپراتور فعال است.
                    تنظیمات پیامک و پخش سفارش از
                    <a href="{{ route('admin.settings.edit') }}" class="text-amber-600 font-bold hover:underline">تنظیمات</a>
                    قابل تغییر است.
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/vendor/chart.umd.min.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/charts.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/pages/admin/dashboard.js') }}?v=13"></script>
@endpush
