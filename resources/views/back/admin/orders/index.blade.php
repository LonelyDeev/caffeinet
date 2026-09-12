@extends('back.layouts.panel')

@section('title', 'سفارش‌ها')
@section('page-title', 'مدیریت سفارش‌ها')
@section('breadcrumb', 'پنل مدیریت کل ← سفارش‌ها')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/trash.css') }}?v=1">
@endpush

@section('content')

    {{-- داده‌های سرور برای JS (بدون کد درون‌خطی) --}}
    <div id="page-data" hidden data-payload="{{ json_encode([
        'base' => '/admin/orders',
    ], JSON_UNESCAPED_UNICODE) }}"></div>

    {{-- چیپ‌های آماری (فیلتر سریع) --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 mb-5">
        <button type="button" class="stat-chip card p-4 flex items-center gap-3 text-right animate-fade-up" data-status="broadcasting">
            <span class="stat-chip-icon grid place-items-center size-10 rounded-2xl bg-sky-50 text-sky-600 shrink-0">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
            </span>
            <div class="min-w-0">
                <p class="stat-chip-label text-[11px] font-semibold text-stone-500">در حال پخش</p>
                <p class="stat-chip-count text-xl font-extrabold tabular-nums text-sky-700"><span id="chip-broadcasting">۰</span> <span class="stat-chip-unit text-[10px] font-medium">سفارش</span></p>
            </div>
        </button>

        <button type="button" class="stat-chip card p-4 flex items-center gap-3 text-right animate-fade-up delay-1" data-status="queued">
            <span class="stat-chip-icon grid place-items-center size-10 rounded-2xl bg-amber-100 text-amber-600 shrink-0">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h4l2-3 4 6 2-3h6"/><path d="M12 2v4"/><path d="m4.93 4.93 2.83 2.83"/><path d="m19.07 4.93-2.83 2.83"/></svg>
            </span>
            <div class="min-w-0">
                <p class="stat-chip-label text-[11px] font-semibold text-stone-500">صف تعیین‌تکلیف</p>
                <p class="stat-chip-count text-xl font-extrabold tabular-nums text-amber-700"><span id="chip-queued">۰</span> <span class="stat-chip-unit text-[10px] font-medium">سفارش</span></p>
            </div>
        </button>

        <button type="button" class="stat-chip card p-4 flex items-center gap-3 text-right animate-fade-up delay-2" data-status="active">
            <span class="stat-chip-icon grid place-items-center size-10 rounded-2xl bg-teal-100 text-teal-600 shrink-0">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 6V4m0 2a2 2 0 1 0 0 4m0-4a2 2 0 1 1 0 4m-6 8a2 2 0 1 0 0-4m0 4a2 2 0 1 1 0-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 1 0 0-4m0 4a2 2 0 1 1 0-4m0 4v2m0-6V4"/></svg>
            </span>
            <div class="min-w-0">
                <p class="stat-chip-label text-[11px] font-semibold text-stone-500">در جریان کار</p>
                <p class="stat-chip-count text-xl font-extrabold tabular-nums text-teal-700"><span id="chip-active">۰</span> <span class="stat-chip-unit text-[10px] font-medium">سفارش</span></p>
            </div>
        </button>

        <button type="button" class="stat-chip card p-4 flex items-center gap-3 text-right animate-fade-up delay-3" data-status="done">
            <span class="stat-chip-icon grid place-items-center size-10 rounded-2xl bg-emerald-100 text-emerald-600 shrink-0">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            </span>
            <div class="min-w-0">
                <p class="stat-chip-label text-[11px] font-semibold text-stone-500">تحویل/تکمیل</p>
                <p class="stat-chip-count text-xl font-extrabold tabular-nums text-emerald-700"><span id="chip-done">۰</span> <span class="stat-chip-unit text-[10px] font-medium">سفارش</span></p>
            </div>
        </button>

        <button type="button" class="stat-chip card p-4 flex items-center gap-3 text-right animate-fade-up delay-4 col-span-2" data-status="">
            <span class="stat-chip-icon grid place-items-center size-10 rounded-2xl bg-stone-100 text-stone-500 shrink-0">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/></svg>
            </span>
            <div class="min-w-0">
                <p class="stat-chip-label text-[11px] font-semibold text-stone-500">کل سفارش‌ها</p>
                <p class="stat-chip-count text-xl font-extrabold tabular-nums text-stone-700"><span id="chip-total">۰</span> <span class="stat-chip-unit text-[10px] font-medium">سفارش</span></p>
            </div>
        </button>
    </div>

    {{-- جدول سفارش‌ها --}}
    <div class="card animate-fade-up delay-2 overflow-hidden">
        <div class="adm-card-head adm-card-head-stacked">
            <div class="relative flex-1 min-w-0">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input id="orders-search" type="text" placeholder="جستجوی شماره سفارش، نام یا موبایل مشتری…"
                       class="field !py-2.5 !text-xs w-full pl-10" autocomplete="off">
            </div>
            <select id="orders-status" class="field !py-2.5 !text-xs !w-auto min-w-44" aria-label="فیلتر وضعیت">
                <option value="">همه وضعیت‌ها</option>
                <option value="active">در جریان کار (پخش تا انجام)</option>
                <option value="broadcasting">در حال پخش</option>
                <option value="queued">صف تعیین‌تکلیف</option>
                <option value="accepted">پذیرفته‌شده</option>
                <option value="in_progress">در حال انجام</option>
                <option value="needs_info">نیازمند اطلاعات</option>
                <option value="delivered">تحویل‌شده</option>
                <option value="completed">تکمیل‌شده</option>
                <option value="cancelled">لغوشده</option>
                <option value="refunded">بازگشت وجه</option>
                <option value="pending_payment">در انتظار پرداخت</option>
                <option value="paid">پرداخت‌شده</option>
            </select>
        </div>

        <div class="table-wrap">
            <div class="overflow-x-auto">
                <table class="table-panel table-modern">
                    <thead>
                    <tr>
                        <th>سفارش</th>
                        <th>خدمت</th>
                        <th>مشتری</th>
                        <th>کافی‌نت</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th>ثبت</th>
                        <th class="text-center">عملیات</th>
                    </tr>
                    </thead>
                    <tbody id="orders-tbody">
                    <tr><td colspan="8" class="!py-10 text-center text-stone-400 text-xs">در حال بارگذاری…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="adm-table-foot">
            <p class="text-[11px] text-stone-400" id="orders-summary">—</p>
            <div class="flex items-center gap-1.5" id="orders-pagination"></div>
        </div>
    </div>

    {{-- ================== مودال جزئیات سفارش ================== --}}
    <div id="detail-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="detail-title">
        <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

        <div class="ui-modal adm-modal-lg adm-modal-text-start" data-tone="info" role="document">
            <div class="adm-modal-head">
                <div class="flex items-center gap-3 min-w-0">
                    <span id="detail-icon" class="grid place-items-center size-11 rounded-2xl bg-amber-100 text-xl shrink-0">📄</span>
                    <div class="min-w-0">
                        <h3 id="detail-title" class="text-sm font-extrabold text-stone-800 font-mono" dir="ltr">—</h3>
                        <p id="detail-service" class="text-[11px] text-stone-400 mt-0.5 truncate">—</p>
                    </div>
                    <span id="detail-status" class="badge bg-stone-50 text-stone-600 border border-stone-200 shrink-0">—</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a id="detail-view-link" href="#" class="btn-ghost ui-press !py-2 !px-4 !text-xs hidden" title="نمایش صفحهٔ کامل جزئیات">صفحهٔ کامل ↗</a>
                    <button type="button" id="detail-assign-btn" class="btn-primary btn-shine !py-2 !px-4 !text-xs hidden">تخصیص دستی</button>
                    <button type="button" id="detail-rebroadcast-btn" class="btn-ghost ui-press !py-2 !px-4 !text-xs hidden">ری‌پخش</button>
                    <button type="button" id="detail-cancel-btn" class="btn-ghost ui-press !py-2 !px-4 !text-xs hidden text-rose-600 hover:bg-rose-50">لغو</button>
                    <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="adm-modal-body space-y-5" id="detail-body">
                <div class="py-16 text-center">
                    <span class="adm-spinner"></span>
                    <p class="text-xs text-stone-400 mt-3">در حال دریافت جزئیات…</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ================== مودال تخصیص دستی ================== --}}
    <div id="assign-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="assign-title">
        <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

        <form id="assign-form" class="ui-modal adm-modal-md adm-modal-text-start" data-tone="info" role="document" novalidate>
            <div class="adm-modal-head">
                <div>
                    <h3 id="assign-title" class="text-sm font-extrabold text-stone-800">تخصیص دستی سفارش</h3>
                    <p id="assign-subtitle" class="text-[11px] text-stone-400 mt-0.5 font-mono" dir="ltr">—</p>
                </div>
                <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="adm-modal-body space-y-4">
                <div class="relative">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input id="assign-search" type="text" placeholder="جستجوی نام کافی‌نت…" class="field !py-2.5 !text-xs w-full pl-10" autocomplete="off">
                </div>

                <div id="assign-list" class="ui-stagger space-y-2 max-h-96 overflow-y-auto" role="radiogroup" aria-label="انتخاب کافی‌نت">
                    <p class="text-xs text-stone-400 text-center py-6">در حال دریافت کافی‌نت‌های فعال…</p>
                </div>

                <div>
                    <label for="assign-note" class="lbl">یادداشت (اختیاری)</label>
                    <textarea id="assign-note" rows="2" class="field !text-xs w-full" placeholder="مثلاً دلیل تخصیص یا توضیح برای تاریخچه…"></textarea>
                </div>

                <p id="assign-error" class="field-error hidden"></p>
            </div>

            <div class="adm-modal-foot">
                <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
                <button type="submit" id="assign-save" class="btn-primary btn-shine !py-2.5 !text-xs">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    تخصیص به کافی‌نت
                </button>
            </div>
        </form>
    </div>

    {{-- ================== مودال لغو ================== --}}
    <div id="cancel-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="cancel-title">
        <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

        <form id="cancel-form" class="ui-modal adm-modal-sm adm-modal-text-start" data-tone="danger" role="document" novalidate>
            <div class="adm-modal-head">
                <div>
                    <h3 id="cancel-title" class="text-sm font-extrabold text-rose-600">لغو سفارش</h3>
                    <p id="cancel-subtitle" class="text-[11px] text-stone-400 mt-0.5 font-mono" dir="ltr">—</p>
                </div>
                <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="adm-modal-body space-y-4">
                <div class="ui-note" data-tone="err">
                    لغو سفارش پرداخت‌شده به مشتری پیامک می‌شود و در تاریخچه ثبت می‌گردد.
                    <strong>وجه پرداختی طبق فرایند تسویه (فاز ۸) برگشت داده می‌شود.</strong>
                </div>
                <div>
                    <label for="cancel-reason" class="lbl">دلیل لغو <span class="text-rose-500">*</span></label>
                    <textarea id="cancel-reason" rows="3" class="field !text-xs w-full" placeholder="دلیل لغو برای مشتری و تاریخچه…"></textarea>
                </div>
                <p id="cancel-error" class="field-error hidden"></p>
            </div>

            <div class="adm-modal-foot">
                <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
                <button type="submit" id="cancel-save" class="ui-btn-danger !py-2.5 !text-xs">
                    لغو سفارش
                </button>
            </div>
        </form>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/orders/index.js') }}?v=28"></script>
<script src="{{ asset('back/assets/js/pages/trash.js') }}?v=1"></script>
@endpush
