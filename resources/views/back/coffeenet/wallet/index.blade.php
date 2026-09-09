@extends('back.coffeenet.layouts.panel')

@section('title', 'کیف پول')
@section('page-title', 'کیف پول کافی‌نت')
@section('breadcrumb', 'پنل کافی‌نت ← کیف پول')

@section('content')

{{-- کارت‌های موجودی --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <div class="card ui-lift p-6 animate-fade-up bg-gradient-to-l from-orange-50 to-white overflow-hidden relative">
        <span class="ui-orb" data-tone="amber" data-pos="tr" aria-hidden="true"></span>
        <span class="ui-orb" data-tone="orange" data-pos="bl" aria-hidden="true"></span>
        <p class="text-xs font-semibold text-stone-500 relative">موجودی فعلی</p>
        <p class="mt-2 text-4xl font-extrabold tabular-nums text-orange-700 relative">{{ fa_money($wallet->balance) }}</p>
        <div class="mt-4 flex items-center gap-2 flex-wrap relative">
            <a href="{{ route('coffeenet.withdrawals.index', ['coffeenet' => $coffeenet]) }}" class="btn-primary btn-shine !py-2.5 !text-xs">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                درخواست برداشت
            </a>
        </div>
    </div>

    <div class="card ui-lift p-5 animate-fade-up delay-1 overflow-hidden relative">
        <span class="ui-orb" data-tone="emerald" data-pos="tr" aria-hidden="true"></span>
        <div class="flex items-center gap-3 relative">
            <span class="ui-chip" data-tone="ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-stone-500">درآمد کمیسیون سفارش‌ها</p>
                <p class="mt-1 text-3xl font-extrabold tabular-nums text-emerald-600">{{ fa_money($summary['commission'], false) }}</p>
            </div>
        </div>
    </div>

    <div class="card ui-lift p-5 animate-fade-up delay-2 overflow-hidden relative">
        <span class="ui-orb" data-tone="rose" data-pos="tr" aria-hidden="true"></span>
        <div class="flex items-center gap-3 relative">
            <span class="ui-chip" data-tone="rose">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-stone-500">مجموع برداشت‌ها و کسرها</p>
                <p class="mt-1 text-3xl font-extrabold tabular-nums text-rose-500">{{ fa_money($summary['total_debit'], false) }}</p>
            </div>
        </div>
    </div>
</div>

{{-- جدول تراکنش‌ها --}}
<section class="card ui-lift mt-5 animate-fade-up delay-2 overflow-hidden">
    <div class="px-5 py-4 border-b border-stone-100 flex flex-wrap items-center gap-3 justify-between">
        <h2 class="no-card-title text-sm font-extrabold text-stone-700">گردش حساب</h2>
        <div class="flex items-center gap-2">
            <select id="type-filter" class="field !py-2 !w-auto !text-xs min-w-32" aria-label="فیلتر نوع تراکنش">
                <option value="">همه تراکنش‌ها</option>
                <option value="credit">فقط واریز</option>
                <option value="debit">فقط برداشت</option>
            </select>
        </div>
    </div>

    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>نوع</th>
                    <th>مبلغ</th>
                    <th>مانده پس از</th>
                    <th>مرجع</th>
                    <th>توضیح</th>
                    <th>تاریخ</th>
                </tr>
            </thead>
            <tbody id="rows">
                <tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
            </tbody>
        </table>
    </div>

    <div id="pagination" class="px-5 py-4 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500"></div>
</section>

{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode(['base' => '/coffeenet/' . $coffeenet->id . '/wallet/data', 'withdraw_url' => route('coffeenet.withdrawals.index', ['coffeenet' => $coffeenet])]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/coffeenet/wallet/index.js') }}"></script>
@endpush
