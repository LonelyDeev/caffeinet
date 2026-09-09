@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'دسته‌بندی خدمات')
@section('page-title', 'دسته‌بندی خدمات')
@section('breadcrumb', 'پنل مدیریت کل ← کاتالوگ ← دسته‌بندی خدمات')

@section('content')

<section class="card ui-lift animate-fade-up overflow-hidden">

    {{-- هدر --}}
    <div class="adm-card-head">
        <div class="flex flex-1 min-w-56 flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-44 max-w-sm">
                <input id="search-input" type="search" class="field !py-2.5 pl-10" placeholder="جستجوی دسته‌بندی..." aria-label="جستجوی دسته‌بندی">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <span class="text-[11px] text-stone-400">درخت دوسطحی: دسته اصلی + زیردسته</span>
        </div>
        <button type="button" id="btn-new" class="btn-primary btn-shine ui-press">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            دسته اصلی جدید
        </button>
    </div>

    {{-- درخت --}}
    <div id="tree" class="p-4 sm:p-5 space-y-2 min-h-40">
        <p class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</p>
    </div>
</section>

{{-- ================== مودال دسته‌بندی ================== --}}
<div id="modal" class="ui-modal-backdrop hidden">
    <div data-close class="absolute inset-0" aria-hidden="true"></div>
    <form id="modal-form" class="ui-modal adm-modal-md adm-modal-text-start" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="modal-title" novalidate>
        <input type="hidden" id="f-id" value="">
        <input type="hidden" id="f-icon" value="">
        <div class="adm-modal-head">
            <h3 id="modal-title" class="text-sm font-extrabold text-stone-800">ثبت دسته‌بندی جدید</h3>
            <button type="button" class="adm-modal-x modal-close" aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-3">
            <div>
                <label class="lbl" for="f-name">نام دسته‌بندی <span class="text-rose-500">*</span></label>
                <input id="f-name" class="field" autocomplete="off" maxlength="150" placeholder="مثلاً خدمات خودرویی">
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
            </div>

            <div>
                <label class="lbl" for="f-parent">دسته والد</label>
                <select id="f-parent" class="field">
                    <option value="">— دسته اصلی (بدون والد) —</option>
                </select>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="parent_id"></p>
            </div>

            <div>
                <span class="lbl">آیکون (نمایش در کاتالوگ مشتری)</span>
                <div id="icon-picker" class="grid grid-cols-8 gap-1.5 mt-1.5" role="radiogroup" aria-label="انتخاب آیکون"></div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="lbl" for="f-sort">ترتیب نمایش</label>
                    <input id="f-sort" type="number" class="field" min="0" max="9999" dir="ltr" value="0">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none pb-2.5">
                        <input id="f-active" type="checkbox" class="size-4 accent-amber-600" checked>
                        <span class="text-xs font-semibold text-stone-600">فعال</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="lbl" for="f-desc">توضیح کوتاه</label>
                <textarea id="f-desc" class="field min-h-16" rows="2" maxlength="500"></textarea>
            </div>
        </div>

        <div class="adm-modal-foot">
            <button type="submit" id="modal-save" class="btn-primary btn-shine !py-3">ذخیره دسته‌بندی</button>
            <button type="button" class="btn-ghost modal-close !py-3 px-5">انصراف</button>
        </div>
    </form>
</div>

{{-- مودال تأیید حذف --}}
<div id="delete-modal" class="ui-modal-backdrop hidden">
    <div data-close-del class="absolute inset-0" aria-hidden="true"></div>
    <form id="delete-form" class="ui-modal adm-modal-sm" data-tone="danger" role="dialog" aria-modal="true" aria-labelledby="del-title" novalidate>
        <span class="ui-modal-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </span>
        <h3 id="del-title" class="ui-modal-title">حذف دسته‌بندی «<span id="del-name"></span>»؟</h3>
        <p class="ui-modal-desc">فقط دسته‌های بدون خدمت و زیردسته قابل حذف هستند.</p>
        <div class="ui-modal-actions">
            <button type="submit" id="delete-confirm" class="ui-btn-danger">حذف کن</button>
            <button type="button" class="btn btn-ghost ui-press" data-close-del>انصراف</button>
        </div>
    </form>
</div>

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/services/categories.js') }}"></script>
@endpush
@endsection
