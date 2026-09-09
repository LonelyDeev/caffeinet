@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'جزئیات سفارش')
@section('page-title', 'جزئیات سفارش: '.($order->order_number ?? '—'))
@section('breadcrumb', 'پنل مدیریت کل ← سفارش‌ها ← '.($order->order_number ?? ''))

@section('content')

    {{-- داده‌های سرور برای JS (بدون کد درون‌خطی) --}}
    <div id="page-data" hidden data-payload="{{ json_encode([
        'base' => '/admin/orders',
        'orderId' => $order->id,
        'orderNumber' => $order->order_number,
        'chatUrl' => "/admin/orders/{$order->id}/chat",
    ], JSON_UNESCAPED_UNICODE) }}"></div>

    {{-- ================== هدر صفحه ================== --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 animate-fade-up">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.orders.index') }}" class="grid place-items-center size-9 rounded-xl border border-stone-200 bg-white text-stone-500 hover:bg-stone-50 transition-colors" title="بازگشت به فهرست سفارش‌ها" aria-label="بازگشت">
                <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <span id="order-icon" class="grid place-items-center size-11 rounded-2xl bg-amber-100 text-xl shrink-0">📄</span>
            <div class="min-w-0">
                <h1 id="order-title" class="text-sm font-extrabold text-stone-800 font-mono" dir="ltr">{{ $order->order_number }}</h1>
                <p id="order-service" class="text-[11px] text-stone-400 mt-0.5 truncate">{{ $order->service?->name ?? '—' }}</p>
            </div>
            <span id="order-status" class="badge bg-stone-50 text-stone-600 border border-stone-200 shrink-0">—</span>
        </div>

        <div class="flex items-center gap-1.5 flex-wrap" id="order-actions">
            <a id="act-chat" href="/admin/orders/{{ $order->id }}/chat" class="btn-ghost ui-press !py-2 !px-4 !text-xs hidden">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                گفتگوی سفارش
            </a>
            <button type="button" id="act-status" class="btn-primary btn-shine !py-2 !px-4 !text-xs hidden">تغییر وضعیت</button>
            <button type="button" id="act-assign" class="btn-primary btn-shine !py-2 !px-4 !text-xs hidden">تخصیص به کافی‌نت</button>
            <button type="button" id="act-operator" class="btn-primary btn-shine !py-2 !px-4 !text-xs hidden">واگذاری به اپراتور</button>
            <button type="button" id="act-rebroadcast" class="btn-ghost ui-press !py-2 !px-4 !text-xs hidden">ری‌پخش</button>
            <button type="button" id="act-cancel" class="btn-ghost ui-press !py-2 !px-4 !text-xs hidden text-rose-600 hover:bg-rose-50">لغو سفارش</button>
        </div>
    </div>

    {{-- ================== بدنه جزئیات ================== --}}
    <div id="order-body" class="cs-stack">
        <div class="card animate-fade-up py-16 text-center">
            <span class="adm-spinner"></span>
            <p class="text-xs text-stone-400 mt-3">در حال دریافت جزئیات…</p>
        </div>
    </div>

    {{-- ================== مودال تخصیص به کافی‌نت ================== --}}
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

                <div id="assign-list" class="ui-stagger space-y-2 max-h-72 overflow-y-auto" role="radiogroup" aria-label="انتخاب کافی‌نت">
                    <p class="text-xs text-stone-400 text-center py-6">در حال دریافت کافی‌نت‌های فعال…</p>
                </div>

                {{-- اپراتور اختیاری — بعد از انتخاب کافی‌نت بارگذاری می‌شود --}}
                <div id="assign-operators-box" class="hidden">
                    <label for="assign-operator" class="lbl">واگذاری به اپراتور (اختیاری)</label>
                    <select id="assign-operator" class="field !text-xs">
                        <option value="">بدون اپراتور — تعیین تکلیف توسط مدیر کافی‌نت</option>
                    </select>
                    <p class="st-hint text-[11px] text-stone-400 mt-1">اپراتورهای فعال کافی‌نت انتخابی — اختیاری</p>
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

    {{-- ================== مودال واگذاری به اپراتور ================== --}}
    <div id="operator-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="operator-title">
        <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

        <form id="operator-form" class="ui-modal adm-modal-md adm-modal-text-start" data-tone="info" role="document" novalidate>
            <div class="adm-modal-head">
                <div>
                    <h3 id="operator-title" class="text-sm font-extrabold text-stone-800">واگذاری به اپراتور</h3>
                    <p id="operator-subtitle" class="text-[11px] text-stone-400 mt-0.5 font-mono" dir="ltr">—</p>
                </div>
                <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="adm-modal-body space-y-4">
                <div id="operator-list" class="ui-stagger space-y-2 max-h-72 overflow-y-auto" role="radiogroup" aria-label="انتخاب اپراتور">
                    <p class="text-xs text-stone-400 text-center py-6">در حال دریافت اپراتورها…</p>
                </div>

                <div>
                    <label for="operator-note" class="lbl">یادداشت (اختیاری)</label>
                    <textarea id="operator-note" rows="2" class="field !text-xs w-full" placeholder="مثلاً توضیح واگذاری برای تاریخچه…"></textarea>
                </div>

                <p id="operator-error" class="field-error hidden"></p>
            </div>

            <div class="adm-modal-foot">
                <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
                <button type="submit" id="operator-save" class="btn-primary btn-shine !py-2.5 !text-xs">واگذاری به اپراتور</button>
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
                    <strong>وجه پرداختی طبق فرایند تسویه برگشت داده می‌شود.</strong>
                </div>
                <div>
                    <label for="cancel-reason" class="lbl">دلیل لغو <span class="text-rose-500">*</span></label>
                    <textarea id="cancel-reason" rows="3" class="field !text-xs w-full" placeholder="دلیل لغو برای مشتری و تاریخچه…"></textarea>
                </div>
                <p id="cancel-error" class="field-error hidden"></p>
            </div>

            <div class="adm-modal-foot">
                <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
                <button type="submit" id="cancel-save" class="ui-btn-danger !py-2.5 !text-xs">لغو سفارش</button>
            </div>
        </form>
    </div>

    {{-- ================== مودال تغییر وضعیت ================== --}}
    <div id="status-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="status-title">
        <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

        <form id="status-form" class="ui-modal adm-modal-sm adm-modal-text-start" data-tone="info" role="document" novalidate>
            <div class="adm-modal-head">
                <div>
                    <h3 id="status-title" class="text-sm font-extrabold text-stone-800">تغییر وضعیت سفارش</h3>
                    <p id="status-subtitle" class="text-[11px] text-stone-400 mt-0.5 font-mono" dir="ltr">—</p>
                </div>
                <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="adm-modal-body space-y-4">
                <div>
                    <label class="lbl">وضعیت جدید <span class="text-amber-600">*</span></label>
                    <div id="status-options" class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="انتخاب وضعیت جدید"></div>
                </div>

                <div>
                    <label for="status-reason" class="lbl">یادداشت / دلیل <span id="status-reason-req" class="text-rose-500 hidden">*</span></label>
                    <textarea id="status-reason" rows="2" class="field !text-xs w-full" placeholder="یادداشت تاریخچه (برای لغو/بازگشت وجه اجباری)…"></textarea>
                </div>

                <div class="ui-note" data-tone="info">
                    گذارها بر اساس ماشین وضعیت اعتبارسنجی می‌شوند؛ پیام سیستمی در چت و اعلان/پیامک مشتری به‌طور خودکار ارسال می‌گردد.
                </div>

                <p id="status-error" class="field-error hidden"></p>
            </div>

            <div class="adm-modal-foot">
                <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
                <button type="submit" id="status-save" class="btn-primary btn-shine !py-2.5 !text-xs">اعمال وضعیت</button>
            </div>
        </form>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/orders/show.js') }}?v=2"></script>
@endpush
