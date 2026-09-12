@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'مدیران سیستم')
@section('page-title', 'مدیران سیستم')
@section('breadcrumb', 'پنل مدیریت کل ← مدیران سیستم')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-perms.css') }}">
@endpush

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/trash.css') }}?v=1">
@endpush

@section('content')

<section class="card ui-lift animate-fade-up overflow-hidden">
    {{-- هدر جدول --}}
    <div class="adm-card-head">
        <div class="relative flex-1 min-w-52 max-w-sm">
            <input id="search-input" type="search" class="field !py-2.5 pl-10" placeholder="جستجو: نام، ایمیل، موبایل..." aria-label="جستجوی مدیران">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <button type="button" id="btn-new" class="btn-primary btn-shine ui-press">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            افزودن مدیر
        </button>
    </div>

    {{-- جدول --}}
    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>نام و نام خانوادگی</th>
                    <th>ایمیل</th>
                    <th>سطح دسترسی</th>
                    <th>دسترسی به بخش‌ها</th>
                    <th>آخرین ورود</th>
                    <th>وضعیت</th>
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

{{-- ================== مودال ایجاد/ویرایش + ماتریس دسترسی ================== --}}
<div id="modal" class="ui-modal-backdrop hidden">
    <div data-close class="absolute inset-0" aria-hidden="true"></div>
    <form id="modal-form" class="ui-modal adm-modal-sm adm-modal-text-start !max-w-2xl" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="modal-title" novalidate>
        <input type="hidden" id="f-id" value="">
        <div class="adm-modal-head">
            <h3 id="modal-title" class="text-sm font-extrabold text-stone-800">افزودن مدیر جدید</h3>
            <button type="button" class="adm-modal-x modal-close" aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="lbl" for="f-name">نام</label>
                <input id="f-name" class="field" autocomplete="off" required>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
            </div>
            <div>
                <label class="lbl" for="f-family">نام خانوادگی</label>
                <input id="f-family" class="field" autocomplete="off">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="lbl" for="f-email">ایمیل <span class="text-rose-500">*</span></label>
                <input id="f-email" type="email" dir="ltr" class="field" autocomplete="off" required>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="email"></p>
            </div>
            <div>
                <label class="lbl" for="f-mobile">موبایل (اختیاری)</label>
                <input id="f-mobile" type="tel" dir="ltr" class="field" placeholder="09123456789" autocomplete="off">
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="mobile"></p>
            </div>
        </div>

        <div>
            <label class="lbl" for="f-password">رمز عبور <span id="pass-hint" class="font-normal text-stone-400">(حداقل ۸ کاراکتر)</span></label>
            <input id="f-password" type="password" dir="ltr" class="field" autocomplete="new-password">
            <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="password"></p>
        </div>

        {{-- سطح دسترسی (درخواست بازخوردی ۶-۴) --}}
        <div class="ap-role-box">
            <p class="lbl !mb-2">سطح دسترسی</p>
            <label class="ap-role-option">
                <input type="radio" name="f-role" value="super_admin">
                <span class="ap-role-radio" aria-hidden="true"></span>
                <span class="flex-1">
                    <b class="block text-xs text-stone-800">مدیر کل</b>
                    <small class="text-[10px] text-stone-400">دسترسی کامل به همهٔ بخش‌ها و تنظیمات</small>
                </span>
                <span class="badge bg-amber-50 text-amber-700 border border-amber-200">کامل</span>
            </label>
            <label class="ap-role-option">
                <input type="radio" name="f-role" value="admin" checked>
                <span class="ap-role-radio" aria-hidden="true"></span>
                <span class="flex-1">
                    <b class="block text-xs text-stone-800">مدیر دستیار</b>
                    <small class="text-[10px] text-stone-400">فقط بخش‌هایی که در پایین فعال کنید</small>
                </span>
                <span class="badge bg-sky-50 text-sky-700 border border-sky-200">انتخابی</span>
            </label>
        </div>

        {{-- ماتریس بخش‌ها — فقط برای مدیر دستیار --}}
        <div id="ap-matrix" class="ap-matrix">
            <div class="ap-matrix-head">
                <p class="text-xs font-extrabold text-stone-700">دسترسی به بخش‌های پنل</p>
                <div class="ap-matrix-tools">
                    <button type="button" id="ap-all" class="ap-bulk-btn">همه</button>
                    <button type="button" id="ap-none" class="ap-bulk-btn">هیچ‌کدام</button>
                </div>
            </div>
            <div class="ap-matrix-grid">
                @foreach ($sectionMatrix as $item)
                    <label class="ap-check {{ $item['free'] ? 'ap-check--free' : '' }}" data-section="{{ $item['section'] }}">
                        <input type="checkbox" class="ap-perm" value="{{ $item['permission'] ?? '' }}" data-section="{{ $item['section'] }}" {{ $item['free'] ? 'checked disabled' : '' }}>
                        <span class="ap-box" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        <span class="ap-check-label">{{ $item['label'] }}</span>
                        @if ($item['free'])
                            <span class="ap-free-tag">همیشه فعال</span>
                        @endif
                    </label>
                @endforeach
            </div>
            <p class="err text-[11px] text-rose-500 mt-1.5 hidden" data-for="permissions"></p>
        </div>

        <div class="adm-modal-foot">
            <button type="submit" id="modal-save" class="btn-primary btn-shine !py-3">ذخیره</button>
            <button type="button" class="btn-ghost modal-close !py-3 px-5">انصراف</button>
        </div>
    </form>
</div>

{{-- داده‌های سرور برای اسکریپت --}}
<div id="page-data" hidden data-payload="{{ json_encode([
    'sectionLabels' => collect($sectionMatrix)->pluck('label', 'section')->all(),
]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/admins/index.js') }}?v=28"></script>
<script src="{{ asset('back/assets/js/pages/trash.js') }}?v=1"></script>
@endpush
