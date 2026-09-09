@extends('back.operator.layouts.panel')

@section('title', 'داشبورد')
@section('page-title', 'داشبورد')
@section('breadcrumb', 'پنل اپراتور ← داشبورد')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/analytics.css') }}?v=9">
@endpush

@section('content')

    @if ($chartData)
        {{-- دادهٔ نمودارها برای JS (فاز ۹) --}}
        <div id="page-data" hidden data-payload="{{ json_encode($chartData) }}"></div>
    @endif

    {{-- کارت‌های آماری --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

        <div class="card ui-lift p-5 relative overflow-hidden animate-fade-up">
            <span class="ui-orb" data-tone="amber" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold text-stone-500">کارهای در جریان من</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums op-stat-num text-amber-700 count-up" data-value="{{ $stats['my_active'] }}">۰</p>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-stone-400 relative">سفارش‌های پذیرفته‌شده و در حال انجام شما</p>
        </div>

        <div class="card ui-lift p-5 relative overflow-hidden animate-fade-up delay-1">
            <span class="ui-orb" data-tone="rose" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip" data-tone="rose" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold text-stone-500">نیازمند اطلاعات</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums op-stat-num text-rose-600 count-up" data-value="{{ $stats['my_needs_info'] }}">۰</p>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-stone-400 relative">کارهایی که منتظر پاسخ/اصلاح شما هستند</p>
        </div>

        <div class="card ui-lift p-5 relative overflow-hidden animate-fade-up delay-2">
            <span class="ui-orb" data-tone="emerald" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip" data-tone="ok" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold text-stone-500">تحویل‌شدهٔ {{ $monthLabel }}</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums op-stat-num text-emerald-600 count-up" data-value="{{ $stats['my_delivered_month'] }}">۰</p>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-stone-400 relative">مجموع سفارش‌های تحویل‌شده توسط شما در ماه جاری</p>
        </div>

        @if ($canAll)
            <div class="card ui-lift p-5 relative overflow-hidden animate-fade-up delay-3">
                <span class="ui-orb" data-tone="teal" data-pos="tr" aria-hidden="true"></span>
                <div class="flex items-center gap-3 relative">
                    <span class="ui-chip" data-tone="teal" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold text-stone-500">کارهای باز کافی‌نت</p>
                        <p class="mt-1 text-3xl font-extrabold tabular-nums op-stat-num text-teal-600 count-up" data-value="{{ $stats['net_open'] ?? 0 }}">۰</p>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-stone-400 relative">همهٔ سفارش‌های فعال کافی‌نت {{ $coffeenet->name }}</p>
            </div>
        @else
            <div class="card ui-lift p-5 relative overflow-hidden animate-fade-up delay-3">
                <span class="ui-orb" data-tone="amber" data-pos="tr" aria-hidden="true"></span>
                <div class="flex items-center gap-3 relative">
                    <span class="ui-chip" data-tone="stone" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold text-stone-500">مجموع کارهای من</p>
                        <p class="mt-1 text-3xl font-extrabold tabular-nums op-stat-num text-stone-500 count-up" data-value="{{ $stats['my_total'] }}">۰</p>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-stone-400 relative">کل سفارش‌های سپرده‌شده به شما (غیر از لغوشده)</p>
            </div>
        @endif
    </div>

    {{-- روند کارهای من (فاز ۹) --}}
    @if ($chartData)
        <section class="card ui-lift an-card animate-fade-up delay-3 mt-4">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                        </span>
                        روند کارهای من
                    </h2>
                    <p class="an-card-sub">تحویل‌شده‌ها و درآمد تسویه — ۱۴ روز اخیر شمسی</p>
                </div>
                <div class="an-card-tools">
                    <a href="{{ route('operator.earnings.index') }}" class="an-export">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                        درآمد و کیف پول
                    </a>
                </div>
            </div>
            <div class="an-chart">
                <canvas id="op-daily" role="img" aria-label="نمودار روند ۱۴ روز اخیر کارهای اپراتور"></canvas>
            </div>
        </section>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-5">

        {{-- آخرین سفارش‌ها در محدودهٔ مجاز --}}
        <section class="card overflow-hidden xl:col-span-2 animate-fade-up delay-2">
            <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between gap-3">
                <h2 class="text-sm font-extrabold text-stone-700">{{ $canAll ? 'آخرین سفارش‌های کافی‌نت' : 'آخرین سفارش‌های من' }}</h2>
                @if ($canViewOrders)
                    <a href="{{ route('operator.orders.index') }}" class="btn-ghost ui-press !py-2 !text-xs">
                        مشاهده همه
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    </a>
                @endif
            </div>

            @if (! $canViewOrders)
                <div class="p-6">
                    <div class="ui-empty mx-auto max-w-md">
                        <span class="ui-empty-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </span>
                        <p class="text-sm font-bold text-stone-600">دسترسی مشاهده سفارش فعال نیست</p>
                        <p class="text-xs text-stone-400 mt-1 leading-6">مدیر کافی‌نت می‌تواند از بخش «کارمندان» دسترسی‌ها را فعال کند.</p>
                    </div>
                </div>
            @elseif ($recent->isEmpty())
                <div class="p-6">
                    <div class="ui-empty mx-auto max-w-md">
                        <span class="ui-empty-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 13v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M2 9h20"/><path d="M12 2 2 9l10 7 10-7-10-7Z"/></svg>
                        </span>
                        <p class="text-sm font-bold text-stone-600">هنوز سفارشی در این محدوده نیست</p>
                        <p class="text-xs text-stone-400 mt-1 leading-6">با پذیرش یا تخصیص سفارش جدید، اینجا نمایش داده می‌شود.</p>
                    </div>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table-panel table-modern">
                        <thead>
                        <tr>
                            <th>سفارش</th>
                            <th>خدمت</th>
                            <th>مشتری</th>
                            <th>وضعیت</th>
                            @if ($canAll)
                                <th>اپراتور</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($recent as $o)
                            <tr>
                                <td><span class="text-[11px] font-mono text-stone-500" dir="ltr">{{ $o->order_number }}</span></td>
                                <td class="text-xs font-bold text-stone-700">{{ $o->service?->name ?? '—' }}</td>
                                <td class="text-xs text-stone-600">{{ trim(($o->customer?->name ?? '').' '.($o->customer?->family ?? '')) ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ ['amber' => 'bg-amber-50 text-amber-700 border border-amber-200', 'sky' => 'bg-sky-50 text-sky-700 border border-sky-200', 'teal' => 'bg-teal-50 text-teal-700 border border-teal-200', 'emerald' => 'bg-emerald-50 text-emerald-700 border border-emerald-200'][$o->status->color()] ?? 'bg-stone-50 text-stone-600 border border-stone-200' }}">{{ $o->status->label() }}</span>
                                </td>
                                @if ($canAll)
                                    <td class="text-xs text-stone-500">
                                        {{ $o->operator ? trim(($o->operator->name ?? '').' '.($o->operator->family ?? '')) : 'تعیین نشده' }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- دسترسی‌های فعال + راهنما --}}
        <section class="card p-5 animate-fade-up delay-3">
            <h2 class="text-sm font-extrabold text-stone-700">دسترسی‌های فعال شما</h2>
            <p class="text-[11px] text-stone-400 mt-1">در کافی‌نت {{ $coffeenet->name }} — قابل تغییر توسط مدیر</p>

            <div class="mt-4 flex flex-wrap gap-2">
                @forelse ($permissions as $key)
                    <span class="badge bg-amber-50 text-amber-700 border border-amber-200">{{ $permissionLabels[$key] ?? $key }}</span>
                @empty
                    <span class="badge bg-stone-50 text-stone-500 border border-stone-200">هیچ دسترسی‌ای فعال نیست</span>
                @endforelse
            </div>

            @if ($canViewOrders)
                <div class="ui-note mt-6" data-tone="ok">
                    <strong>گفتگوی زنده فعال است.</strong>
                    از بخش «<a href="{{ route('operator.chat.index') }}">گفتگوها</a>» با مشتریان چت کنید؛
                    ارسال تصویر/صدا/ویدیو و بروزرسانی وضعیت سفارش از همان‌جا انجام می‌شود.
                </div>
            @else
                <div class="ui-note mt-6">
                    <strong>گفتگوی زنده:</strong>
                    پس از فعال‌شدن دسترسی «مشاهده سفارش» توسط مدیر کافی‌نت، گفتگو با مشتریان از همین پنل انجام می‌شود.
                </div>
            @endif

            <div class="ui-note mt-4">
                <strong>راهنما:</strong> شماره‌گذاری سفارش‌ها به‌صورت CN{ymd}-XXXX است.
                برای پیگیری جزئیات هر سفارش، از فهرست «{{ $canAll ? 'سفارش‌های کافی‌نت' : 'سفارش‌های من' }}» استفاده کنید.
            </div>
        </section>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/vendor/chart.umd.min.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/charts.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/pages/operator/dashboard.js') }}?v=13"></script>
@endpush
