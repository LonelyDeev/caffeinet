@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'گزارش تحلیلی')
@section('page-title', 'گزارش تحلیلی')
@section('breadcrumb', 'پنل مدیریت کل ← تحلیل و نمودارها ← گزارش تحلیلی')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/analytics.css') }}?v=9">
@endpush

@section('content')

    {{-- دادهٔ اولیه برای JS --}}
    <div id="page-data" hidden data-payload="{{ json_encode([
        'preset' => $preset,
        'from' => $from,
        'to' => $to,
        'exportBase' => route('admin.analytics.export'),
        'dataUrl' => route('admin.analytics.data'),
    ]) }}"></div>

    {{-- نوار فیلتر بازه + خروجی‌ها --}}
    <section class="card ui-lift an-card animate-fade-up">
        <div class="an-card-head">
            <div>
                <h2 class="an-card-title">
                    <span class="an-title-chip" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 16h.01"/><path d="M11 12h.01"/><path d="M15 8h.01"/><path d="M19 4h.01"/></svg>
                    </span>
                    بازهٔ گزارش
                    <span class="an-card-sub" id="range-label">{{ $rangeLabel }}</span>
                </h2>
            </div>

            <div class="an-card-tools">
                <div class="an-filters">
                    <div class="an-chip-group" role="group" aria-label="بازهٔ زمانی">
                        <button type="button" class="an-chip" data-preset="7">۷ روز</button>
                        <button type="button" class="an-chip" data-preset="30">۳۰ روز</button>
                        <button type="button" class="an-chip" data-preset="90">۹۰ روز</button>
                        <button type="button" class="an-chip" data-preset="month">ماه جاری</button>
                        <button type="button" class="an-chip" data-preset="last_month">ماه قبل</button>
                    </div>

                    <div class="an-date">
                        <input type="text" id="from-input" dir="ltr" placeholder="۱۴۰۵/۰۶/۰۱" style="text-align:center"
                               value="{{ $from ? jdate(Illuminate\Support\Carbon::parse($from))->format('Y/m/d') : '' }}"
                               aria-label="از تاریخ" data-jdp data-jdp-mode="gregorian" title="برای انتخاب تاریخ کلیک کنید">
                        <input type="hidden" id="from-input-g" value="{{ $from }}">
                        <span class="text-[11px] font-bold text-stone-400">تا</span>
                        <input type="text" id="to-input" dir="ltr" placeholder="۱۴۰۵/۰۶/۳۱" style="text-align:center"
                               value="{{ $to ? jdate(Illuminate\Support\Carbon::parse($to))->format('Y/m/d') : '' }}"
                               aria-label="تا تاریخ" data-jdp data-jdp-mode="gregorian" title="برای انتخاب تاریخ کلیک کنید">
                        <input type="hidden" id="to-input-g" value="{{ $to }}">
                        <button type="button" id="apply-custom" class="an-chip is-active" style="display:none">اعمال</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-5 pb-4 pt-3 border-t border-stone-100 mt-2 flex flex-wrap items-center gap-2">
            <span class="text-[11px] font-bold text-stone-400 ml-1">خروجی گزارش:</span>
            <a id="export-orders" href="#" class="an-export" data-scope="orders">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                سفارش‌ها (CSV)
            </a>
            <a id="export-services" href="#" class="an-export" data-scope="services">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                خدمات
            </a>
            <a id="export-operators" href="#" class="an-export" data-scope="operators">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                اپراتورها
            </a>
            <a id="export-customers" href="#" class="an-export" data-scope="customers">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                مشتریان
            </a>
            <a id="export-coffeenets" href="#" class="an-export" data-scope="coffeenets">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                کافی‌نت‌ها
            </a>
        </div>
    </section>

    {{-- کارت‌های خلاصهٔ بازه --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mt-4" id="summary-grid">
        @php
            $mini = [
                ['سفارش ثبت‌شده', fa_number($summary['orders']), ''],
                ['پرداخت‌شده', fa_number($summary['paid']), 'text-emerald-600'],
                ['حجم پرداخت', fa_money($summary['volume'], false), 'text-amber-600'],
                ['تسویهٔ کمیسیون', fa_money($summary['settled'], false), 'text-teal-600'],
                ['درآمد اپراتورها', fa_money($summary['operator_earnings'], false), 'text-rose-500'],
                ['مشتری فعال', fa_number($summary['customers']), ''],
            ];
        @endphp
        @foreach ($mini as $m)
            <div class="an-mini animate-fade-up delay-{{ min($loop->index + 1, 4) }}">
                <div class="min-w-0">
                    <p class="an-mini-label truncate">{{ $m[0] }}</p>
                    <p class="an-mini-value {{ $m[2] }}">{{ $m[1] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ردیف نمودار اصلی: روند روزانه + وضعیت --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-4">

        <section class="card ui-lift an-card animate-fade-up delay-2 xl:col-span-2">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                        </span>
                        روند سفارش‌ها و پرداخت
                    </h2>
                    <p class="an-card-sub">تعداد سفارش (سمت راست) و حجم پرداخت‌شده به تومان (سمت چپ) — روزهای شمسی</p>
                </div>
            </div>
            <div class="an-chart an-chart--tall">
                <canvas id="chart-daily" role="img" aria-label="نمودار روند روزانه سفارش‌ها و پرداخت"></canvas>
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
                    <p class="an-card-sub">تفکیک وضعیت سفارش‌های بازه</p>
                </div>
            </div>
            <div class="an-chart an-chart--tall">
                <canvas id="chart-status" role="img" aria-label="نمودار وضعیت سفارش‌ها"></canvas>
            </div>
        </section>
    </div>

    {{-- ردیف ۲: خدمات پرتقاضا + عملکرد اپراتورها --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-4">

        <section class="card ui-lift an-card animate-fade-up delay-3">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.35a2 2 0 0 0-1.66 0L2.6 7.5a1 1 0 0 0 0 1.8l8.58 5.15a2 2 0 0 0 1.66 0l8.58-5.15a1 1 0 0 0 0-1.8Z"/><path d="m6.08 10.62-3.5 2.1a1 1 0 0 0 0 1.8l8.58 5.15a2 2 0 0 0 1.66 0l8.58-5.15a1 1 0 0 0 0-1.8l-3.5-2.1"/></svg>
                        </span>
                        خدمات پرتقاضا
                    </h2>
                    <p class="an-card-sub">بر اساس تعداد سفارش در بازه</p>
                </div>
            </div>
            <div class="an-chart an-chart--tall">
                <canvas id="chart-services" role="img" aria-label="نمودار خدمات پرتقاضا"></canvas>
            </div>
        </section>

        <section class="card ui-lift an-card animate-fade-up delay-4 overflow-hidden">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm18 0h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-5Z"/><path d="M21 11a9 9 0 0 0-18 0"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/></svg>
                        </span>
                        عملکرد اپراتورها
                    </h2>
                    <p class="an-card-sub">سفارش‌های سپرده‌شده، تحویل‌شده و درآمد تسویه</p>
                </div>
            </div>
            <div class="table-wrap">
                <div class="overflow-x-auto">
                    <table class="table-panel table-modern">
                        <thead>
                            <tr>
                                <th>اپراتور</th>
                                <th>سپرده‌شده</th>
                                <th>تحویل‌شده</th>
                                <th>درآمد</th>
                            </tr>
                        </thead>
                        <tbody id="operators-rows">
                            <tr><td colspan="4" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    {{-- ردیف ۳: عملکرد کافی‌نت‌ها + مشتریان برتر --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-4">

        <section class="card ui-lift an-card animate-fade-up delay-4 overflow-hidden">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/></svg>
                        </span>
                        عملکرد کافی‌نت‌ها
                    </h2>
                    <p class="an-card-sub">سفارش، حجم پرداخت و کمیسیون تسویه‌شده</p>
                </div>
            </div>
            <div class="table-wrap">
                <div class="overflow-x-auto">
                    <table class="table-panel table-modern">
                        <thead>
                            <tr>
                                <th>کافی‌نت</th>
                                <th>سفارش</th>
                                <th>حجم پرداخت</th>
                                <th>کمیسیون</th>
                            </tr>
                        </thead>
                        <tbody id="coffeenets-rows">
                            <tr><td colspan="4" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card ui-lift an-card animate-fade-up delay-4 overflow-hidden">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                        مشتریان برتر
                    </h2>
                    <p class="an-card-sub">بیشترین سفارش و پرداخت در بازه</p>
                </div>
            </div>
            <div class="table-wrap">
                <div class="overflow-x-auto">
                    <table class="table-panel table-modern">
                        <thead>
                            <tr>
                                <th>مشتری</th>
                                <th>سفارش</th>
                                <th>پرداخت</th>
                            </tr>
                        </thead>
                        <tbody id="customers-rows">
                            <tr><td colspan="3" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/vendor/chart.umd.min.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/charts.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/pages/admin/analytics/index.js') }}?v=9"></script>
@endpush
