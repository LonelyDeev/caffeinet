@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'لاگ پیامک‌ها')
@section('page-title', 'لاگ پیامک‌های ارسال‌شده')
@section('breadcrumb', 'پنل مدیریت کل ← لاگ پیامک‌ها')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/sms-logs.css') }}?v=16">
@endpush

@section('content')

<div class="sl-stack">

    {{-- ================== هدر قهرمان ================== --}}
    <div class="card ui-lift animate-fade-up sl-hero">
        <div class="sl-hero-main">
            <span class="sl-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 8h8"/><path d="M8 12h4"/><path d="M8 16h2"/></svg>
            </span>
            <div class="flex-1 min-w-0">
                <h1 class="sl-hero-title">لاگ پیامک‌های ارسال‌شده</h1>
                <p class="sl-hero-sub">
                    گزارش کامل تمام پیامک‌های سیستم — هر ارسال (موفق یا ناموفق) از هر پرووایدری
                    با <b>متن، قالب، وضعیت و پاسخ پرووایدر</b> اینجا ثبت و قابل بررسی است.
                </p>
            </div>
            <div class="sl-hero-side">
                <span class="badge {{ $provider === 'log' ? 'bg-stone-100 text-stone-500 border border-stone-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}" title="پرووایدر فعال فعلی">
                    پرووایدر فعال: {{ $providerLabel }}
                </span>
                <a href="{{ route('admin.sms-templates.index') }}" class="btn-ghost !py-2 !px-4 !text-xs ui-press">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
                    مرکز پیامک
                </a>
            </div>
        </div>

        {{-- آمار کلی --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat-num" id="stat-total">۰</span>
                <span class="sl-stat-label">کل پیامک‌ها</span>
            </div>
            <div class="sl-stat sl-stat--ok">
                <span class="sl-stat-num" id="stat-sent">۰</span>
                <span class="sl-stat-label">ارسال موفق</span>
            </div>
            <div class="sl-stat sl-stat--err">
                <span class="sl-stat-num" id="stat-failed">۰</span>
                <span class="sl-stat-label">ناموفق / خطا</span>
            </div>
            <div class="sl-stat sl-stat--today">
                <span class="sl-stat-num" id="stat-today">۰</span>
                <span class="sl-stat-label">امروز</span>
            </div>
        </div>
    </div>

    {{-- ================== فیلترها + جدول ================== --}}
    <section class="card ui-lift animate-fade-up overflow-hidden" style="animation-delay:.06s">

        <div class="adm-card-head sl-filters">
            <div class="relative flex-1 min-w-40 max-w-xs">
                <input id="f-q" type="search" class="field !py-2.5 !pl-9" placeholder="جستجو: موبایل، متن، کلید قالب..." aria-label="جستجو در لاگ پیامک">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>

            <select id="f-status" class="field !py-2.5 !w-auto !text-xs cursor-pointer" aria-label="وضعیت ارسال">
                <option value="">همه وضعیت‌ها</option>
                <option value="sent">ارسال موفق</option>
                <option value="failed">ناموفق</option>
            </select>

            <select id="f-provider" class="field !py-2.5 !w-auto !text-xs cursor-pointer" aria-label="پرووایدر">
                <option value="">همه پرووایدرها</option>
                @foreach ($providers as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>

            <input id="f-from" type="text" dir="ltr" placeholder="از تاریخ ۱۴۰۵/۰۶/۰۱" class="field num !py-2.5 !w-auto max-w-40 !text-center"
                   aria-label="از تاریخ" data-jdp title="برای انتخاب تاریخ کلیک کنید">
            <input id="f-to" type="text" dir="ltr" placeholder="تا تاریخ ۱۴۰۵/۰۶/۳۱" class="field num !py-2.5 !w-auto max-w-40 !text-center"
                   aria-label="تا تاریخ" data-jdp title="برای انتخاب تاریخ کلیک کنید">

            <button type="button" id="btn-filter" class="btn-ghost !py-2.5 !px-4 !text-xs">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>
                اعمال فیلتر
            </button>

            <button type="button" id="btn-reset" class="btn-ghost !py-2.5 !px-3 !text-xs" title="پاک کردن فیلترها" aria-label="پاک کردن فیلترها">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            </button>
        </div>

        {{-- جدول --}}
        <div class="table-wrap">
            <div class="overflow-x-auto">
                <table class="table-panel table-modern">
                <thead>
                    <tr>
                        <th>موبایل</th>
                        <th>قالب</th>
                        <th>متن پیام</th>
                        <th>پرووایدر</th>
                        <th>وضعیت</th>
                        <th>زمان</th>
                        <th class="text-center">جزئیات</th>
                    </tr>
                </thead>
                <tbody id="rows">
                    <tr><td colspan="7" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
                </tbody>
            </table>
            </div>
        </div>

        <div id="pagination" class="adm-table-foot text-xs text-stone-500"></div>
    </section>
</div>

{{-- ================== مودال جزئیات ================== --}}
<div id="detail-modal" class="ui-modal-backdrop hidden">
    <div data-close class="absolute inset-0" aria-hidden="true"></div>
    <div class="ui-modal adm-modal-md adm-modal-text-start" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="sl-d-title">
        <div class="adm-modal-head">
            <h3 class="text-sm font-extrabold text-stone-800" id="sl-d-title">جزئیات پیامک</h3>
            <button type="button" class="adm-modal-x" data-close aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            {{-- ردیف اطلاعات --}}
            <div class="sl-d-grid">
                <div>
                    <p class="lbl !text-[10px]">موبایل گیرنده</p>
                    <p class="sl-d-val font-mono" dir="ltr" id="sl-d-mobile">—</p>
                </div>
                <div>
                    <p class="lbl !text-[10px]">پرووایدر / روش</p>
                    <p class="sl-d-val" id="sl-d-provider">—</p>
                </div>
                <div>
                    <p class="lbl !text-[10px]">قالب</p>
                    <p class="sl-d-val" id="sl-d-template">—</p>
                </div>
                <div>
                    <p class="lbl !text-[10px]">وضعیت و زمان</p>
                    <p class="sl-d-val" id="sl-d-status">—</p>
                </div>
            </div>

            {{-- متن کامل --}}
            <div>
                <p class="lbl !text-[10px]">متن کامل پیامک</p>
                <div class="sl-d-msg"><p id="sl-d-message">—</p></div>
            </div>

            {{-- پاسخ پرووایدر --}}
            <div>
                <p class="lbl !text-[10px]">پاسخ پرووایدر (JSON)</p>
                <pre class="sl-d-json" dir="ltr"><code id="sl-d-response">—</code></pre>
            </div>
        </div>

        <div class="adm-modal-foot">
            <button type="button" class="btn-ghost ui-press !py-3 !px-5" data-close>بستن</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/sms-logs/index.js') }}?v=16"></script>
@endpush
