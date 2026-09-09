@extends('back.coffeenet.layouts.panel')

@section('title', 'داشبورد')
@section('page-title', 'داشبورد')
@section('breadcrumb', 'پنل کافی‌نت ← داشبورد')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/analytics.css') }}?v=9">
@endpush

@section('content')

    {{-- دادهٔ نمودارها برای JS (فاز ۹) --}}
    <div id="page-data" hidden data-payload="{{ json_encode($chartData) }}"></div>

    {{-- فاز ۶ — بنر زندهٔ سفارش‌های در انتظار پذیرش --}}
    @if ($stats['orders_broadcasting'] > 0)
        <a href="{{ route('coffeenet.orders.index', ['coffeenet' => $coffeenet->id]) }}"
           class="no-live-edge card ui-lift p-4 mb-4 flex items-center gap-3 border-sky-200 animate-fade-up group relative overflow-hidden">
            <span class="ui-orb" data-tone="teal" data-pos="tr" aria-hidden="true"></span>
            <span class="grid place-items-center size-11 rounded-2xl bg-sky-50 text-sky-600 shrink-0 relative">
                <span class="absolute inset-0 rounded-2xl bg-sky-50 animate-ping" aria-hidden="true"></span>
                <svg class="size-5.5 relative" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
            </span>
            <div class="min-w-0 flex-1 relative">
                <p class="text-sm font-extrabold text-sky-800 flex items-center gap-2">
                    <span class="ui-dot text-emerald-500"></span>
                    {{ fa_number($stats['orders_broadcasting']) }} سفارش جدید در صندوق پخش!
                </p>
                <p class="text-[11px] text-stone-500 mt-0.5">مهلت پذیرش در حال پایان است — همین حالا مشاهده و پذیرش کنید.</p>
            </div>
            <span class="btn-primary btn-shine !py-2.5 !text-xs shrink-0 relative">
                مشاهده صندوق پخش
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            </span>
        </a>
    @endif

    {{-- کارت‌های آماری --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

        <div class="card ui-lift p-5 animate-fade-up bg-gradient-to-l from-amber-50 to-white overflow-hidden relative">
            <span class="ui-orb" data-tone="amber" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip" data-tone="stone">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-stone-500">کارمندان فعال</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums text-amber-700 count-up" data-value="{{ $stats['staff_total'] }}">۰</p>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-stone-400 relative">
                <span class="font-bold text-stone-500">{{ fa_number($stats['operators']) }}</span> اپراتور ·
                <span class="font-bold text-stone-500">{{ fa_number($stats['managers']) }}</span> مدیر
            </p>
        </div>

        <div class="card ui-lift p-5 animate-fade-up delay-1 overflow-hidden relative">
            <span class="ui-orb" data-tone="emerald" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip" data-tone="ok">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-stone-500">حقوق پرداختی {{ $currentPeriodLabel }}</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums text-emerald-600">{{ fa_money($stats['salary_current']) }}</p>
                </div>
            </div>
        </div>

        <div class="card ui-lift p-5 animate-fade-up delay-2 overflow-hidden relative">
            <span class="ui-orb" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip" data-tone="stone">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-stone-500">حقوق ماه گذشته</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums text-stone-500">{{ fa_money($stats['salary_last']) }}</p>
                </div>
            </div>
        </div>

        <div class="card ui-lift p-5 animate-fade-up delay-3 overflow-hidden relative">
            <span class="ui-orb" data-tone="teal" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip" data-tone="teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-stone-500">موجودی کیف پول کافی‌نت</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums text-teal-600">{{ fa_money($stats['wallet_balance']) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- نمای تحلیلی ۱۴ روز اخیر (فاز ۹) --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-4">

        <section class="card ui-lift an-card animate-fade-up delay-3 xl:col-span-2">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                        </span>
                        روند ۱۴ روز اخیر
                    </h2>
                    <p class="an-card-sub">سفارش‌های این کافی‌نت و واریزی کیف پول — روزهای شمسی</p>
                    </div>
                    <div class="an-card-tools">
                    <a href="{{ route('coffeenet.wallet.index', ['coffeenet' => $coffeenet->id]) }}" class="an-export">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                        کیف پول و درآمد
                    </a>
                </div>
            </div>
            <div class="an-chart">
                <canvas id="net-daily" role="img" aria-label="نمودار روند ۱۴ روز اخیر کافی‌نت"></canvas>
            </div>
        </section>

        <section class="card ui-lift an-card animate-fade-up delay-3">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12A9 9 0 1 1 12 3a9 9 0 0 1 9 9Z"/><path d="M12 3v9l6 4"/></svg>
                        </span>
                        وضعیت سفارش‌ها
                    </h2>
                    <p class="an-card-sub">۱۴ روز اخیر این کافی‌نت</p>
                </div>
            </div>
            <div class="an-chart">
                <canvas id="net-status" role="img" aria-label="نمودار وضعیت سفارش‌های کافی‌نت"></canvas>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-5">

        {{-- آخرین کارمندان --}}
        <section class="card ui-lift xl:col-span-2 animate-fade-up delay-2 overflow-hidden">
            <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between gap-3">
                <h2 class="no-card-title text-sm font-extrabold text-stone-700">آخرین کارمندان اضافه‌شده</h2>
                <a href="{{ route('coffeenet.staff.index', ['coffeenet' => $coffeenet->id]) }}" class="btn-ghost !py-2 !text-xs">
                    مدیریت کارمندان
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </a>
            </div>

            @if ($recentStaff->isEmpty())
                <div class="p-8">
                    <div class="ui-empty">
                        <span class="ui-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                        <p class="text-sm font-semibold text-stone-500">هنوز کارمندی ثبت نشده است</p>
                        <p class="text-xs text-stone-400 mt-1">اولین اپراتور یا مدیر کمکی خود را از بخش «کارمندان» اضافه کنید.</p>
                        <a href="{{ route('coffeenet.staff.index', ['coffeenet' => $coffeenet->id]) }}" class="btn-primary btn-shine !py-2.5 !text-xs mt-4">افزودن کارمند</a>
                    </div>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table-panel table-modern">
                        <thead>
                        <tr>
                            <th>کارمند</th>
                            <th>سمت</th>
                            <th>مدل حقوق</th>
                            <th>وضعیت</th>
                            <th>آخرین ورود</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($recentStaff as $s)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid place-items-center size-8 rounded-xl bg-amber-100 text-amber-700 text-xs font-bold shrink-0">{{ mb_substr($s->user->name ?? '؟', 0, 1) }}</span>
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-stone-700 truncate">{{ $s->user->full_name }}</p>
                                            <p class="text-[10px] text-stone-400 truncate" dir="ltr">{{ $s->user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $s->position === \App\Enums\StaffPosition::Manager ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-stone-50 text-stone-600 border border-stone-200' }}">{{ $s->position->label() }}</span>
                                </td>
                                <td>
                                    @if ($s->salarySetting)
                                        <span class="text-[11px] font-semibold text-stone-600">{{ $s->salarySetting->type->label() }}</span>
                                    @else
                                        <span class="text-[11px] text-stone-400">تعیین نشده</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $s->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' }}">{{ $s->is_active ? 'فعال' : 'غیرفعال' }}</span>
                                </td>
                                <td class="text-[11px] text-stone-400">{{ $s->user->last_login_at?->diffForHumans(now(), ['locale' => 'fa']) ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ترکیب مدل‌های حقوقی --}}
        <section class="card ui-lift p-5 animate-fade-up delay-3 overflow-hidden relative">
            <span class="ui-orb" data-tone="amber" data-pos="bl" aria-hidden="true"></span>
            <h2 class="no-card-title text-sm font-extrabold text-stone-700 relative">ترکیب مدل‌های حقوقی</h2>
            <p class="text-[11px] text-stone-400 mt-1 relative">بین کارمندان فعال دارای تنظیمات حقوق</p>

            @if ($salaryMix->isEmpty())
                <div class="mt-6">
                    <div class="ui-empty">
                        <span class="ui-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
                        </span>
                        <p class="text-xs text-stone-400 leading-6">هنوز مدلی ثبت نشده است.<br>با افزودن کارمند، مدل حقوق هر نفر تعیین می‌شود.</p>
                    </div>
                </div>
            @else
                <div class="mt-5 space-y-3 relative">
                    @php $total = $salaryMix->sum(); @endphp
                    @foreach ($salaryMix as $type => $count)
                        @php
                            $pct = $total > 0 ? round($count * 100 / $total) : 0;
                            $label = \App\Enums\SalaryType::from($type)->label();
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1.5">
                                <span class="font-semibold text-stone-600">{{ $label }}</span>
                                <span class="tabular-nums text-stone-400">{{ fa_number($count) }} نفر ({{ fa_number($pct) }}٪)</span>
                            </div>
                            <div class="h-2.5 rounded-full bg-stone-100 overflow-hidden">
                                <div class="no-bar h-full rounded-full bg-gradient-to-l from-amber-400 to-amber-600 transition-all duration-700" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <a href="{{ route('coffeenet.salaries.index', ['coffeenet' => $coffeenet->id]) }}" class="btn-ghost w-full !py-2.5 !text-xs mt-5 relative">
                ثبت پرداخت حقوق
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            </a>
        </section>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/vendor/chart.umd.min.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/charts.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/pages/coffeenet/dashboard.js') }}?v=13"></script>
@endpush
