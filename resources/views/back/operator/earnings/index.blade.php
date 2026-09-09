@extends('back.operator.layouts.panel')

@section('title', 'درآمد و کیف پول')
@section('page-title', 'درآمد و کیف پول')
@section('breadcrumb', 'پنل اپراتور ← درآمد و کیف پول')

@section('content')

    {{-- کارت‌های درآمد --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        <div class="card ui-lift p-5 animate-fade-up relative overflow-hidden bg-gradient-to-l from-emerald-50 to-white">
            <span class="ui-orb" data-tone="emerald" data-pos="tr" aria-hidden="true"></span>
            <p class="text-xs font-semibold text-stone-500 relative">موجودی کیف پول من</p>
            <p class="mt-2 text-4xl font-extrabold tabular-nums text-emerald-700 relative">{{ fa_money($wallet->balance) }}</p>
            <p class="mt-1 text-[11px] text-stone-400 relative">درآمد همهٔ کافی‌نت‌های محل کار شما در یک کیف جمع می‌شود</p>
        </div>

        <div class="card ui-lift p-5 animate-fade-up delay-1 relative overflow-hidden">
            <span class="ui-orb" data-tone="amber" data-pos="tr" aria-hidden="true"></span>
            <p class="text-xs font-semibold text-stone-500 relative">جمع درآمد تسویه‌شده</p>
            <p class="mt-2 text-3xl font-extrabold tabular-nums text-amber-600 relative">{{ fa_money($summary['total'], false) }}</p>
            <p class="mt-1 text-[11px] text-stone-400 relative">{{ fa_number($summary['orders']) }} سفارش تحویل‌شده</p>
        </div>

        <div class="card ui-lift p-5 animate-fade-up delay-2 relative overflow-hidden">
            <span class="ui-orb" data-tone="rose" data-pos="tr" aria-hidden="true"></span>
            <p class="text-xs font-semibold text-stone-500 relative">درآمد این ماه</p>
            <p class="mt-2 text-3xl font-extrabold tabular-nums text-rose-500 relative">{{ fa_money($summary['month'], false) }}</p>
            <p class="mt-1 text-[11px] text-stone-400 relative">
                @if ($summary['last_payout'])
                    آخرین تسویه: {{ jdate($summary['last_payout']->created_at)->format('Y/m/d') }}
                @else
                    هنوز تسویه‌ای ثبت نشده
                @endif
            </p>
        </div>
    </div>

    {{-- راهنمای مدل حقوق --}}
    <div class="card ui-lift p-4 mt-4 animate-fade-up delay-2 relative overflow-hidden">
        <div class="flex items-start gap-3 relative">
            <span class="ui-chip" data-tone="amber shrink-0">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </span>
            <p class="text-xs text-stone-500 leading-6">
                سهم شما از هر سفارش، بلافاصله پس از <strong class="text-stone-700">تحویل به مشتری</strong> و طبق مدل حقوق‌تان در همان کافی‌نت محاسبه و به کیف پول شما واریز می‌شود (درصدی یا مبلغ ثابت هر سفارش؛ مدل «ماهیانه» فقط در لاگ حقوق مدیر کافی‌نت ثبت می‌شود).
            </p>
        </div>
    </div>

    {{-- سابقهٔ تسویه‌ها --}}
    <section class="card ui-lift mt-4 animate-fade-up delay-2 overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100 flex flex-wrap items-center gap-3 justify-between">
            <h2 class="no-card-title text-sm font-extrabold text-stone-700">سابقهٔ تسویهٔ سهم‌ها</h2>
        </div>

        <div class="table-wrap">
            <table class="table-panel table-modern">
                <thead>
                    <tr>
                        <th>سفارش</th>
                        <th>خدمت</th>
                        <th>کافی‌نت</th>
                        <th>سهم من</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody id="rows">
                    <tr><td colspan="5" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
                </tbody>
            </table>
        </div>

        <div id="pagination" class="px-5 py-4 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500"></div>
    </section>

    {{-- لاگ تراکنش‌های کیف پول (درخواست بازخوردی) --}}
    <section class="card ui-lift mt-4 animate-fade-up delay-3 overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100 flex flex-wrap items-center gap-3 justify-between">
            <h2 class="no-card-title text-sm font-extrabold text-stone-700">لاگ تراکنش‌های کیف پول</h2>
            <div class="flex items-center gap-1.5" id="tx-filters" role="tablist" aria-label="فیلتر نوع تراکنش">
                <button type="button" class="tx-filter-btn btn-ghost !py-2 !px-4 !text-xs" data-type="" aria-pressed="true">همه</button>
                <button type="button" class="tx-filter-btn btn-ghost !py-2 !px-4 !text-xs" data-type="credit" aria-pressed="false">واریز</button>
                <button type="button" class="tx-filter-btn btn-ghost !py-2 !px-4 !text-xs" data-type="debit" aria-pressed="false">برداشت</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table-panel table-modern">
                <thead>
                    <tr>
                        <th>نوع</th>
                        <th>مبلغ</th>
                        <th>موجودی پس از</th>
                        <th>مرجع</th>
                        <th>شرح</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody id="tx-rows">
                    <tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
                </tbody>
            </table>
        </div>

        <div id="tx-pagination" class="px-5 py-4 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500"></div>
    </section>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/operator/earnings/index.js') }}?v=2"></script>
@endpush
