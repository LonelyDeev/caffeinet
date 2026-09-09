@extends('back.operator.layouts.panel')

@section('title', 'سفارش‌ها')
@section('page-title', $canAll ? 'سفارش‌های کافی‌نت' : 'سفارش‌های من')
@section('breadcrumb', 'پنل اپراتور ← سفارش‌ها')

@section('content')

    {{-- داده‌های سرور برای JS (بدون کد درون‌خطی) --}}
    <div id="page-data" hidden data-payload="{{ json_encode([
        'base' => '/operator/orders',
        'canAll' => (bool) $canAll,
    ], JSON_UNESCAPED_UNICODE) }}"></div>

    {{-- سربرگ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 animate-fade-up">
        <div>
            <p class="text-sm font-extrabold text-stone-700">{{ $canAll ? 'همهٔ سفارش‌های کافی‌نت' : 'سفارش‌های سپرده‌شده به شما' }}</p>
            <p class="text-[11px] text-stone-400 mt-1 leading-5">
                {{ $canAll
                    ? 'شما دسترسی مشاهده همهٔ سفارش‌های کافی‌نت «'.$coffeenet->name.'» را دارید.'
                    : 'فقط سفارش‌هایی که اپراتور آن‌ها هستید نمایش داده می‌شوند.' }}
            </p>
        </div>
    </div>

    {{-- جدول --}}
    <div class="card overflow-hidden animate-fade-up">
        {{-- فیلترها --}}
        <div class="px-5 py-4 border-b border-stone-100 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input id="orders-search" type="text" placeholder="جستجوی شماره سفارش یا نام مشتری…"
                       class="field op-pill-input !py-2.5 !text-xs w-full pl-10" autocomplete="off">
            </div>
            <select id="orders-status" class="field op-select-pill !py-2.5 !text-xs !w-auto min-w-44" aria-label="فیلتر وضعیت">
                <option value="active">در جریان کار</option>
                <option value="accepted">پذیرفته‌شده (در انتظار پرداخت)</option>
                <option value="paid">پرداخت‌شده (آماده شروع)</option>
                <option value="in_progress">در حال انجام</option>
                <option value="needs_info">نیازمند اطلاعات</option>
                <option value="done">تحویل/تکمیل‌شده</option>
                <option value="completed">تکمیل‌شده</option>
                <option value="cancelled">لغوشده</option>
                <option value="all">همه</option>
            </select>
        </div>

        <div class="table-wrap">
            <table class="table-panel table-modern">
                <thead>
                <tr>
                    <th>سفارش</th>
                    <th>خدمت</th>
                    <th>مشتری</th>
                    @if ($canAll)
                        <th>اپراتور</th>
                    @endif
                    <th>مبلغ</th>
                    <th>وضعیت</th>
                    <th>پرداخت</th>
                    <th>پذیرش</th>
                    <th>گفتگو</th>
                </tr>
                </thead>
                <tbody id="orders-tbody">
                <tr><td colspan="{{ $canAll ? 9 : 8 }}" class="!py-10 text-center text-stone-400 text-xs">در حال بارگذاری…</td></tr>
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-stone-100 flex items-center justify-between gap-3">
            <p class="text-[11px] text-stone-400" id="orders-summary">—</p>
            <div class="flex items-center gap-1.5" id="orders-pagination"></div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/operator/orders/index.js') }}"></script>
@endpush
