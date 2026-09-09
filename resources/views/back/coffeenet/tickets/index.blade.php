@extends('back.coffeenet.layouts.panel')

@section('title', 'تیکت‌های پشتیبانی')
@section('page-title', 'تیکت‌های پشتیبانی')
@section('breadcrumb', 'پنل کافی‌نت ← تیکت‌های پشتیبانی')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/tickets.css') }}?v=13">
@endpush

@section('content')

    {{-- چیپ‌های آماری --}}
    <div class="tk-stats">
        @php
            $chips = [
                ['label' => 'همه تیکت‌ها', 'value' => $stats['total'], 'tone' => 'total', 'key' => ''],
                ['label' => 'باز', 'value' => $stats['open'], 'tone' => 'open', 'key' => 'open'],
                ['label' => 'پاسخ داده‌شده', 'value' => $stats['answered'], 'tone' => 'answered', 'key' => 'answered'],
                ['label' => 'پاسخ مشتری', 'value' => $stats['customer_reply'], 'tone' => 'customer', 'key' => 'customer_reply'],
                ['label' => 'بسته', 'value' => $stats['closed'], 'tone' => 'closed', 'key' => 'closed'],
                ['label' => 'اولویت زیاد (باز)', 'value' => $stats['high'], 'tone' => 'high', 'key' => 'high-open'],
            ];
        @endphp
        @foreach ($chips as $chip)
            <button type="button" class="tk-stat" data-tone="{{ $chip['tone'] }}" data-stat-chip="{{ $chip['key'] }}">
                <strong>{{ fa_number($chip['value']) }}</strong>
                <span>{{ $chip['label'] }}</span>
            </button>
        @endforeach
    </div>

<section class="card ui-lift animate-fade-up overflow-hidden">

    {{-- هدر جدول --}}
    <div class="adm-card-head">
        <div class="text-xs text-stone-500 leading-6">
            فقط تیکت‌های سفارش‌های این کافی‌نت اینجا دیده می‌شوند؛ تیکت‌های عمومی توسط مدیریت کل پاسخ می‌گیرند.
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <select id="status-filter" class="field !py-2.5 !w-auto min-w-36" aria-label="فیلتر وضعیت">
                <option value="">همه وضعیت‌ها</option>
                <option value="open">باز</option>
                <option value="answered">پاسخ داده‌شده</option>
                <option value="customer_reply">پاسخ مشتری</option>
                <option value="closed">بسته‌شده</option>
            </select>
            <div class="relative">
                <input id="search-input" type="search" class="field !py-2.5 min-w-44 !ps-9" placeholder="جستجو: شماره / موضوع / مشتری…" aria-label="جستجو">
                <svg class="absolute start-3 top-1/2 -translate-y-1/2 size-4 text-stone-400 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
        </div>
    </div>

    {{-- جدول --}}
    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>تیکت</th>
                    <th>مشتری</th>
                    <th>سفارش مرتبط</th>
                    <th>وضعیت</th>
                    <th>اولویت</th>
                    <th>آخرین فعالیت</th>
                    <th class="text-center">عملیات</th>
                </tr>
            </thead>
            <tbody id="rows">
                <tr><td colspan="7" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
            </tbody>
        </table>
    </div>

    {{-- صفحه‌بندی --}}
    <div id="pagination" class="adm-table-foot text-xs text-stone-500"></div>
</section>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/coffeenet/tickets/index.js') }}?v=13"></script>
@endpush
