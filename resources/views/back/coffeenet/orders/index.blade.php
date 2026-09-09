@extends('back.coffeenet.layouts.panel')

@section('title', 'سفارش‌ها')
@section('page-title', 'سفارش‌ها و پخش زنده')
@section('breadcrumb', 'پنل کافی‌نت ← سفارش‌ها')

@section('content')

    {{-- داده‌های سرور برای JS (بدون کد درون‌خطی) --}}
    <div id="page-data" hidden data-payload="{{ json_encode([
        'base' => "/coffeenet/{$coffeenet->id}/orders",
        'timeout' => (int) $broadcastTimeout,
    ], JSON_UNESCAPED_UNICODE) }}"></div>

    {{-- سربرگ + تب‌ها --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 animate-fade-up">
        <div class="no-tabs self-start" role="tablist" aria-label="بخش‌های سفارش‌ها">
            <button type="button" id="tab-broadcast" class="tab-btn no-tab is-active" role="tab" aria-selected="true">
                <span class="no-tab-pulse relative flex size-2">
                    <span class="absolute inline-flex size-full rounded-full bg-amber-400 opacity-60 animate-ping"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-amber-400"></span>
                </span>
                صندوق پخش
                <span id="broadcast-count" class="no-tab-count">۰</span>
            </button>
            <button type="button" id="tab-mine" class="tab-btn no-tab" role="tab" aria-selected="false">
                سفارش‌های من
                <span id="mine-count" class="no-tab-count">۰</span>
            </button>
        </div>
        <p class="ui-note max-w-xl">
            درخواست‌های تازهٔ مشتریان به‌مدت <strong>{{ fa_number($broadcastTimeout) }}</strong> ثانیه بین اپراتورها و کافی‌نت‌ها پخش می‌شود؛
            اولین که بپذیرد، درخواست را قفل می‌کند — مشتری پس از اتصال، هزینه را پرداخت می‌کند.
        </p>
    </div>

    {{-- ================== تب صندوق پخش ================== --}}
    <section id="pane-broadcast" class="animate-fade-up">
        <div id="broadcast-empty" class="ui-empty">
            <span class="ui-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 13v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M2 9h20"/><path d="m12 2 10 7-10 7-10-7 10-7Z"/></svg>
            </span>
            <p class="text-sm font-extrabold text-stone-600">در انتظار سفارش جدید…</p>
            <p class="text-xs text-stone-400 mt-1.5 leading-6 max-w-md mx-auto">هر لحظه این صفحه به‌صورت زنده بروزرسانی می‌شود؛ به‌محض ثبت درخواست جدید توسط مشتری، کارت آن با شمارش معکوس همین‌جا ظاهر می‌شود.</p>
        </div>

        <div id="broadcast-list" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4"></div>
    </section>

    {{-- ================== تب سفارش‌های من ================== --}}
    <section id="pane-mine" class="hidden animate-fade-up">
        <div class="card">
            {{-- فیلترها --}}
            <div class="px-5 py-4 border-b border-stone-100 flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input id="mine-search" type="text" placeholder="جستجوی شماره سفارش یا نام مشتری…"
                           class="field !py-2.5 !text-xs w-full pl-10" autocomplete="off">
                </div>
                <select id="mine-status" class="field !py-2.5 !text-xs !w-auto min-w-44" aria-label="فیلتر وضعیت">
                    <option value="active">در جریان کار</option>
                    <option value="accepted">پذیرفته‌شده</option>
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
                        <th>اپراتور</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th>پذیرش</th>
                        <th class="text-center">عملیات</th>
                    </tr>
                    </thead>
                    <tbody id="mine-tbody">
                    <tr><td colspan="8" class="!py-10 text-center text-stone-400 text-xs">در حال بارگذاری…</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-stone-100 flex items-center justify-between gap-3">
                <p class="text-[11px] text-stone-400" id="mine-summary">—</p>
                <div class="flex items-center gap-1.5" id="mine-pagination"></div>
            </div>
        </div>
    </section>

    {{-- ================== مودال ارجاع به اپراتور ================== --}}
    <div id="operator-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="operator-title">
        <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

        <form id="operator-form" class="ui-modal adm-modal-md adm-modal-text-start" data-tone="info" role="document" novalidate>
            <div class="adm-modal-head">
                <div>
                    <h3 id="operator-title" class="text-sm font-extrabold text-stone-800">ارجاع سفارش به اپراتور</h3>
                    <p id="operator-subtitle" class="text-[11px] text-stone-400 mt-0.5 font-mono" dir="ltr">—</p>
                </div>
                <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="adm-modal-body space-y-4">
                <div class="ui-note" data-tone="info">
                    سفارش‌های پذیرفته‌شدهٔ کافی‌نت را به یکی از <strong>اپراتورهای فعال</strong> بسپارید؛
                    اپراتور اعلان می‌گیرد و گفتگو و پیگیری سفارش با او خواهد بود.
                </div>

                <div id="operator-list" class="ui-stagger space-y-2 max-h-72 overflow-y-auto" role="radiogroup" aria-label="انتخاب اپراتور">
                    <p class="text-xs text-stone-400 text-center py-6">در حال دریافت اپراتورها…</p>
                </div>

                <div>
                    <label for="operator-note" class="lbl">یادداشت (اختیاری)</label>
                    <textarea id="operator-note" rows="2" class="field !text-xs w-full" placeholder="مثلاً توضیح ارجاع برای تاریخچه…"></textarea>
                </div>

                <p id="operator-error" class="field-error hidden"></p>
            </div>

            <div class="adm-modal-foot">
                <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
                <button type="submit" id="operator-save" class="btn-primary btn-shine !py-2.5 !text-xs">ارجاع به اپراتور</button>
            </div>
        </form>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/coffeenet/orders/index.js') }}"></script>
@endpush
