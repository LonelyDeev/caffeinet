@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'خدمات و فرم‌ساز')
@section('page-title', 'خدمات و فرم‌ساز')
@section('breadcrumb', 'پنل مدیریت کل ← کاتالوگ ← خدمات')

@section('content')

<section class="card ui-lift animate-fade-up overflow-hidden">

    {{-- هدر: جستجو + فیلترها + دکمه خدمت جدید --}}
    <div class="adm-card-head adm-card-head-stacked">
        <div class="flex flex-1 min-w-0 flex-wrap items-center gap-2.5">
            <div class="relative flex-1 min-w-44 max-w-sm">
                <input id="search-input" type="search" class="field !py-2.5 pl-10" placeholder="جستجو: نام، توضیحات..." aria-label="جستجوی خدمات">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <select id="filter-category" class="field !py-2.5 !w-auto min-w-36 text-xs" aria-label="فیلتر دسته‌بندی">
                <option value="">همه دسته‌ها</option>
                @foreach ($categories as $c)
                    <option value="{{ $c['id'] }}">{{ $c['name'] }}{{ $c['is_active'] ? '' : ' (غیرفعال)' }}</option>
                @endforeach
            </select>
            <select id="filter-status" class="field !py-2.5 !w-auto text-xs" aria-label="فیلتر وضعیت">
                <option value="">همه وضعیت‌ها</option>
                <option value="active">فعال</option>
                <option value="inactive">غیرفعال</option>
            </select>
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-stone-500 hover:text-amber-700 transition-colors px-2 py-2 rounded-xl hover:bg-amber-50" title="فقط خدمت‌های ویژه">
                <input id="filter-featured" type="checkbox" class="size-4 accent-amber-600">
                <svg class="size-4 text-amber-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.02l-5.88 3.09 1.12-6.55-4.76-4.64 6.58-.96z"/></svg>
                ویژه
            </label>
        </div>
        <a href="{{ route('admin.services.create') }}" id="btn-new" class="btn-primary btn-shine ui-press">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            خدمت جدید (فرم‌ساز)
        </a>
    </div>

    {{-- جدول --}}
    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
                <thead>
                    <tr>
                        <th>خدمت</th>
                        <th class="hidden md:table-cell">دسته‌بندی</th>
                        <th>قیمت پایه</th>
                        <th class="hidden lg:table-cell">مشمول کمیسیون</th>
                        <th class="hidden lg:table-cell">هزینه / فیلد</th>
                        <th class="hidden sm:table-cell">نسخه</th>
                        <th>وضعیت</th>
                        <th class="text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody id="rows">
                    <tr><td colspan="8" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- صفحه‌بندی --}}
    <div id="pagination" class="adm-table-foot text-xs text-stone-500"></div>
</section>

{{-- ================== مودال نسخه‌ها ================== --}}
<div id="versions-modal" class="ui-modal-backdrop hidden">
    <div data-close-versions class="absolute inset-0" aria-hidden="true"></div>
    <div class="ui-modal adm-modal-md adm-modal-text-start" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="versions-title">
        <div class="adm-modal-head">
            <h3 id="versions-title" class="text-sm font-extrabold text-stone-800">تاریخچه نسخه‌های «<span id="versions-service-name"></span>»</h3>
            <button type="button" class="adm-modal-x" data-close-versions aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="adm-modal-body space-y-4">
            <p class="ui-note">هر تغییر در قیمت، هزینه‌ها یا فرم سفارش، یک نسخه جدید ثبت می‌کند؛ سفارش‌های ثبت‌شده بر اساس نسخهٔ خودشان قیمت‌گذاری می‌شوند.</p>
            <div id="versions-list" class="ui-stagger space-y-2"></div>
        </div>
    </div>
</div>

{{-- ================== مودال تأیید حذف ================== --}}
<div id="delete-modal" class="ui-modal-backdrop hidden">
    <div data-close-del class="absolute inset-0" aria-hidden="true"></div>
    <form id="delete-form" class="ui-modal adm-modal-sm" data-tone="danger" role="dialog" aria-modal="true" aria-labelledby="del-title" novalidate>
        <span class="ui-modal-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </span>
        <h3 id="del-title" class="ui-modal-title">حذف خدمت «<span id="del-name"></span>»؟</h3>
        <p class="ui-modal-desc">خدمت‌های دارای سفارش قابل حذف نیستند — می‌توانید غیرفعالشان کنید.</p>
        <div class="ui-modal-actions">
            <button type="submit" id="delete-confirm" class="ui-btn-danger">حذف کن</button>
            <button type="button" class="btn btn-ghost ui-press" data-close-del>انصراف</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/services/index.js') }}"></script>
@endpush
