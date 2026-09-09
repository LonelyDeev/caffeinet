@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'تسویه‌ها')
@section('page-title', 'تسویه‌های کمیسیون')
@section('breadcrumb', 'پنل مدیریت کل ← مالی و کمیسیون ← تسویه‌ها')

@section('content')

{{-- کارت‌های آماری --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="card ui-lift p-5 animate-fade-up relative overflow-hidden">
        <span class="ui-orb" data-tone="amber" data-pos="tr" aria-hidden="true"></span>
        <p class="text-xs font-semibold text-stone-500 relative">جمع تسویه‌ها</p>
        <p class="mt-2 text-3xl font-extrabold tabular-nums text-stone-800 relative">{{ fa_money($summary['total'], false) }}</p>
        <p class="mt-1 text-[11px] text-stone-400 relative">{{ fa_number($summary['count']) }} پرداخت در {{ fa_number($summary['orders']) }} سفارش</p>
    </div>
    <div class="card ui-lift p-5 animate-fade-up delay-1 relative overflow-hidden">
        <span class="ui-orb" data-tone="amber" data-pos="tr" aria-hidden="true"></span>
        <p class="text-xs font-semibold text-stone-500 relative">سهم پلتفرم</p>
        <p class="mt-2 text-3xl font-extrabold tabular-nums text-amber-600 relative">{{ fa_money($summary['platform'], false) }}</p>
        <p class="mt-1 text-[11px] text-stone-400 relative">واریزی به حساب پلتفرم</p>
    </div>
    <div class="card ui-lift p-5 animate-fade-up delay-2 relative overflow-hidden">
        <span class="ui-orb" data-tone="teal" data-pos="tr" aria-hidden="true"></span>
        <p class="text-xs font-semibold text-stone-500 relative">سهم کافی‌نت‌ها</p>
        <p class="mt-2 text-3xl font-extrabold tabular-nums text-teal-600 relative">{{ fa_money($summary['coffeenet'], false) }}</p>
        <p class="mt-1 text-[11px] text-stone-400 relative">پس از کسر درآمد اپراتور</p>
    </div>
    <div class="card ui-lift p-5 animate-fade-up delay-2 relative overflow-hidden">
        <span class="ui-orb" data-tone="rose" data-pos="tr" aria-hidden="true"></span>
        <p class="text-xs font-semibold text-stone-500 relative">درآمد اپراتورها</p>
        <p class="mt-2 text-3xl font-extrabold tabular-nums text-rose-500 relative">{{ fa_money($summary['operator'], false) }}</p>
        <p class="mt-1 text-[11px] text-stone-400 relative">سازمان‌ها: {{ fa_money($summary['organization'], false) }}</p>
    </div>
</div>

{{-- جدول تسویه‌ها --}}
<section class="card ui-lift animate-fade-up delay-2 overflow-hidden mt-5">
    <div class="adm-card-head">
        <div class="flex items-center gap-3 flex-wrap">
            <div class="relative">
                <input type="text" id="q-filter" class="field !py-2.5 !w-56 !text-xs pl-9" placeholder="جستجوی شماره سفارش…" aria-label="جستجوی شماره سفارش">
                <svg class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-stone-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <input type="text" id="from-filter" dir="ltr" placeholder="۱۴۰۵/۰۶/۰۱" class="field !py-2.5 !text-xs !w-36 !text-center" aria-label="از تاریخ" data-jdp data-jdp-mode="gregorian" title="برای انتخاب تاریخ کلیک کنید">
            <input type="hidden" id="from-filter-g">
            <input type="text" id="to-filter" dir="ltr" placeholder="۱۴۰۵/۰۶/۳۱" class="field !py-2.5 !text-xs !w-36 !text-center" aria-label="تا تاریخ" data-jdp data-jdp-mode="gregorian" title="برای انتخاب تاریخ کلیک کنید">
            <input type="hidden" id="to-filter-g">
        </div>
        <select id="role-filter" class="field !py-2.5 !w-auto min-w-40" aria-label="فیلتر نقش">
            <option value="">همهٔ نقش‌ها</option>
            <option value="platform">پلتفرم</option>
            <option value="organization">سازمان</option>
            <option value="coffeenet">کافی‌نت</option>
            <option value="operator">اپراتور</option>
        </select>
    </div>

    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>سفارش</th>
                    <th>خدمت</th>
                    <th>نقش</th>
                    <th>دارندهٔ سهم</th>
                    <th>مبلغ</th>
                    <th>تاریخ تسویه</th>
                </tr>
            </thead>
            <tbody id="rows">
                <tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
            </tbody>
        </table>
    </div>

    <div id="pagination" class="adm-table-foot text-xs text-stone-500"></div>
</section>

{{-- مودال جزئیات تسویهٔ سفارش --}}
<div id="order-modal" class="ui-modal-backdrop hidden">
    <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>
    <div class="ui-modal adm-modal-text-start" data-tone="amber" role="dialog" aria-modal="true" aria-labelledby="om-title">
        <div class="adm-modal-head">
            <h3 class="text-sm font-extrabold text-stone-800" id="om-title">جزئیات تسویه</h3>
            <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="adm-modal-body space-y-4" id="om-body"></div>
        <div class="adm-modal-foot">
            <button type="button" class="btn-ghost !py-2.5 w-full" data-close-modal>بستن</button>
        </div>
    </div>
</div>

<div id="page-data" hidden data-payload="{{ json_encode(['role' => (string) request('role', '')]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/settlements/index.js') }}"></script>
@endpush
