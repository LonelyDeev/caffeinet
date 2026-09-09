@extends('back.coffeenet.layouts.panel')

@php
    /* ۱۲ ماه اخیر برای انتخاب دوره — برچسب شمسی، مقدار میلادی Y-m */
    $monthOptions = [];
    $cursor = now()->startOfMonth();
    for ($i = 0; $i < 12; $i++) {
        $monthOptions[] = [
            'value' => $cursor->format('Y-m'),
            'label' => jdate($cursor)->format('F Y'),
        ];
        $cursor = $cursor->copy()->subMonth();
    }
    $currentPeriodLabel = jdate(now())->format('F Y');
    $lastPeriodLabel = jdate(now()->subMonth())->format('F Y');
@endphp

@section('title', 'حقوق و دستمزد')
@section('page-title', 'حقوق و دستمزد')
@section('breadcrumb', 'پنل کافی‌نت ← حقوق و دستمزد')

@section('content')

    {{-- کارت‌های خلاصه --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

        <div class="card ui-lift p-5 animate-fade-up bg-gradient-to-l from-emerald-50 to-white overflow-hidden relative">
            <span class="ui-orb" data-tone="emerald" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip" data-tone="ok">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-stone-500">پرداختی {{ $currentPeriodLabel }}</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums text-emerald-600">{{ fa_money($summary['current_total']) }}</p>
                </div>
            </div>
        </div>

        <div class="card ui-lift p-5 animate-fade-up delay-1 overflow-hidden relative">
            <span class="ui-orb" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip" data-tone="stone">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-stone-500">پرداختی {{ $lastPeriodLabel }}</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums text-stone-500">{{ fa_money($summary['last_total']) }}</p>
                </div>
            </div>
        </div>

        <div class="card ui-lift p-5 animate-fade-up delay-2 overflow-hidden relative">
            <span class="ui-orb" data-tone="amber" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-stone-500">کل رکوردهای پرداخت</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums text-amber-700">{{ fa_number($summary['logs_count']) }}</p>
                </div>
            </div>
        </div>

        <div class="card ui-lift p-5 animate-fade-up delay-3 overflow-hidden relative">
            <span class="ui-orb" data-tone="teal" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3 relative">
                <span class="ui-chip" data-tone="teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-stone-500">دریافت‌کنندگان این ماه</p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums text-teal-600">{{ fa_number($summary['paid_staff']) }} <span class="text-sm font-bold">نفر</span></p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-5">

        {{-- فرم ثبت پرداخت --}}
        <section class="card ui-lift p-5 xl:col-span-1 animate-fade-up delay-1 h-fit overflow-hidden relative">
            <span class="ui-orb" data-tone="amber" data-pos="bl" aria-hidden="true"></span>
            <h2 class="no-card-title text-sm font-extrabold text-stone-700 relative">ثبت پرداخت جدید</h2>
            <p class="text-[11px] text-stone-400 mt-1 mb-4 leading-5 relative">پرداختی‌های حقوق/اضافه‌کار/پاداش ثبت می‌شوند (دفتر گزارشی کافی‌نت) و مستقیماً از کیف پول کم نمی‌شوند.</p>

            <form id="salary-form" class="space-y-4" novalidate>
                <p id="salary-error" class="hidden text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3.5 py-2.5 leading-6"></p>

                <div>
                    <label class="lbl" for="s-user">کارمند <span class="text-rose-500">*</span></label>
                    <select id="s-user" class="field" required>
                        <option value="">— انتخاب کارمند —</option>
                    </select>
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="user_id"></p>
                </div>

                <div>
                    <label class="lbl" for="s-period">دوره (ماه) <span class="text-rose-500">*</span></label>
                    <select id="s-period" class="field" required>
                        @foreach ($monthOptions as $m)
                            <option value="{{ $m['value'] }}" {{ $m['value'] === $currentPeriod ? 'selected' : '' }}>{{ $m['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="period"></p>
                </div>

                <div>
                    <label class="lbl" for="s-type">نوع پرداخت <span class="text-rose-500">*</span></label>
                    <select id="s-type" class="field" required>
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="lbl" for="s-amount">مبلغ (تومان) <span class="text-rose-500">*</span></label>
                    <input id="s-amount" type="number" min="1" step="0.01" dir="ltr" class="field" placeholder="مثلاً 5000000" required>
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="amount"></p>
                </div>

                <div>
                    <label class="lbl" for="s-desc">توضیحات <span class="text-stone-400 font-normal">(اختیاری)</span></label>
                    <textarea id="s-desc" rows="2" class="field !py-2.5" maxlength="500" placeholder="مثلاً: حقوق مهر + دو روز اضافه‌کار"></textarea>
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="description"></p>
                </div>

                <button type="submit" id="btn-pay" class="btn-primary btn-shine w-full !py-3 !text-xs">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                    ثبت پرداخت
                </button>
            </form>
        </section>

        {{-- جدول لاگ‌ها --}}
        <section class="card ui-lift xl:col-span-2 animate-fade-up delay-2 overflow-hidden">
            <div class="px-5 py-4 border-b border-stone-100 flex flex-wrap items-center gap-2.5 justify-between">
                <h2 class="no-card-title text-sm font-extrabold text-stone-700">تاریخچه پرداخت‌ها</h2>
                <div class="flex items-center gap-2 flex-wrap">
                    <select id="f-user" class="field !py-2 !w-auto !text-xs min-w-36" aria-label="فیلتر کارمند">
                        <option value="">همه کارمندان</option>
                    </select>
                    <select id="f-type" class="field !py-2 !w-auto !text-xs min-w-32" aria-label="فیلتر نوع">
                        <option value="">همه انواع</option>
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select id="f-period" class="field !py-2 !w-auto !text-xs min-w-32" aria-label="فیلتر دوره">
                        <option value="">همه دوره‌ها</option>
                        @foreach ($monthOptions as $m)
                            <option value="{{ $m['value'] }}">{{ $m['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table-panel table-modern">
                    <thead>
                    <tr>
                        <th>کارمند</th>
                        <th>دوره</th>
                        <th>نوع</th>
                        <th>مبلغ</th>
                        <th>توضیحات</th>
                        <th>ثبت‌کننده</th>
                        <th>زمان ثبت</th>
                    </tr>
                    </thead>
                    <tbody id="logs-tbody">
                    <tr><td colspan="7" class="!py-10 text-center text-stone-400 text-xs">در حال بارگذاری…</td></tr>
                    </tbody>
                </table>
            </div>

            <div id="logs-pagination" class="px-5 py-4 border-t border-stone-100 flex items-center justify-between gap-3 flex-wrap"></div>
        </section>
    </div>


{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode(['staff' => $staff, 'base' => '/coffeenet/' . $coffeenet->id . '/salaries']) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/coffeenet/salaries/index.js') }}"></script>
@endpush
