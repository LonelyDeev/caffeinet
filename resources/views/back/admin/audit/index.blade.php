@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'لاگ فعالیت')
@section('page-title', 'لاگ فعالیت‌ها')
@section('breadcrumb', 'پنل مدیریت کل ← لاگ فعالیت‌ها')

@section('content')

@php
    $fromG = (string) request('from');
    $toG = (string) request('to');
    $fromFa = $fromG ? jdate(Illuminate\Support\Carbon::parse($fromG))->format('Y/m/d') : '';
    $toFa = $toG ? jdate(Illuminate\Support\Carbon::parse($toG))->format('Y/m/d') : '';
@endphp

<section class="card ui-lift animate-fade-up overflow-hidden">

    {{-- فیلترها --}}
    <div class="adm-card-head">
        <div class="relative flex-1 min-w-44 max-w-xs">
            <input id="f-q" type="search" class="field !py-2.5 pl-10" placeholder="جستجو: عملیات، توضیح، کاربر..." aria-label="جستجو در لاگ‌ها">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <input id="f-from" type="text" dir="ltr" placeholder="۱۴۰۵/۰۶/۰۱" class="field !py-2.5 !w-auto max-w-40 !text-center"
               aria-label="از تاریخ" data-jdp data-jdp-mode="gregorian" value="{{ $fromFa }}" title="برای انتخاب تاریخ کلیک کنید">
        <input type="hidden" id="f-from-g" value="{{ $fromG }}">
        <input id="f-to" type="text" dir="ltr" placeholder="۱۴۰۵/۰۶/۳۱" class="field !py-2.5 !w-auto max-w-40 !text-center"
               aria-label="تا تاریخ" data-jdp data-jdp-mode="gregorian" value="{{ $toFa }}" title="برای انتخاب تاریخ کلیک کنید">
        <input type="hidden" id="f-to-g" value="{{ $toG }}">
        <button type="button" id="btn-filter" class="btn-ghost !py-2.5 !px-4 !text-xs">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>
            اعمال فیلتر
        </button>
    </div>

    {{-- جدول --}}
    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>کاربر</th>
                    <th>عملیات</th>
                    <th>توضیحات</th>
                    <th>موجودیت</th>
                    <th>IP</th>
                    <th>زمان</th>
                    <th class="text-center">جزئیات</th>
                </tr>
            </thead>
            <tbody id="rows">
                <tr><td colspan="7" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
            </tbody>
        </table>
    </div>

    <div id="pagination" class="adm-table-foot text-xs text-stone-500"></div>
</section>

{{-- مودال جزئیات --}}
<div id="detail-modal" class="ui-modal-backdrop hidden">
    <div data-close class="absolute inset-0" aria-hidden="true"></div>
    <div class="ui-modal adm-modal-md adm-modal-text-start" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="d-title">
        <div class="adm-modal-head">
            <h3 class="text-sm font-extrabold text-stone-800" id="d-title">جزئیات فعالیت</h3>
            <button type="button" class="adm-modal-x" data-close aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="adm-modal-body">
            <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                    <p class="lbl">مقادیر قبلی</p>
                    <pre class="rounded-xl bg-stone-50 border border-stone-200 p-3 text-[11px] leading-6 overflow-x-auto max-h-60 overflow-y-auto text-stone-600" dir="ltr"><code id="d-old">—</code></pre>
                </div>
                <div>
                    <p class="lbl">مقادیر جدید</p>
                    <pre class="rounded-xl bg-stone-50 border border-stone-200 p-3 text-[11px] leading-6 overflow-x-auto max-h-60 overflow-y-auto text-stone-600" dir="ltr"><code id="d-new">—</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/audit/index.js') }}"></script>
@endpush
