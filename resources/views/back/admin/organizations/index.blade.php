@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'سازمان‌ها')
@section('page-title', 'مدیریت سازمان‌ها')
@section('breadcrumb', 'پنل مدیریت کل ← سازمان‌ها')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/trash.css') }}?v=1">
@endpush

@section('content')

<section class="card ui-lift animate-fade-up overflow-hidden">

    {{-- هدر جدول --}}
    <div class="adm-card-head">
        <div class="flex flex-1 min-w-64 flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-44 max-w-sm">
                <input id="search-input" type="search" class="field !py-2.5 pl-10" placeholder="جستجو: نام سازمان، مدیر، شناسه..." aria-label="جستجوی سازمان‌ها">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <select id="status-filter" class="field !py-2.5 !w-auto min-w-36" aria-label="فیلتر وضعیت">
                <option value="">همه وضعیت‌ها</option>
                <option value="pending">در انتظار بررسی</option>
                <option value="approved">تأییدشده</option>
                <option value="rejected">ردشده</option>
                <option value="suspended">معلق</option>
            </select>
        </div>
        <button type="button" id="btn-new" class="btn-primary btn-shine ui-press">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            ثبت سازمان جدید
        </button>
    </div>

    {{-- جدول --}}
    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>سازمان</th>
                    <th>مدیر سازمان</th>
                    <th>موقعیت</th>
                    <th>کیف پول</th>
                    <th>وضعیت</th>
                    <th class="text-center">عملیات</th>
                </tr>
            </thead>
            <tbody id="rows">
                <tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
            </tbody>
        </table>
    </div>

    {{-- صفحه‌بندی --}}
    <div id="pagination" class="adm-table-foot text-xs text-stone-500"></div>
</section>

{{-- ================== مودال ایجاد/ویرایش ================== --}}
<div id="modal" class="ui-modal-backdrop hidden">
    <div data-close class="absolute inset-0" aria-hidden="true"></div>
    <form id="modal-form" class="ui-modal adm-modal-lg adm-modal-text-start" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="modal-title" novalidate>
        <input type="hidden" id="f-id" value="">
        <div class="adm-modal-head">
            <h3 id="modal-title" class="text-sm font-extrabold text-stone-800">ثبت سازمان جدید</h3>
            <button type="button" class="adm-modal-x modal-close" aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            <p class="ui-note">
                کاربر «مدیر سازمان» همزمان ساخته می‌شود و با ایمیل و رمز وارد <strong>پنل سازمان</strong> می‌شود.
            </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
                <label class="lbl" for="f-name">نام سازمان <span class="text-rose-500">*</span></label>
                <input id="f-name" class="field" autocomplete="off" required>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
            </div>

            <div>
                <label class="lbl" for="f-type">نوع سازمان</label>
                <select id="f-type" class="field">
                    <option value="legal">حقوقی</option>
                    <option value="individual">حقیقی</option>
                </select>
            </div>

            <div>
                <label class="lbl" for="f-status">وضعیت اولیه</label>
                <select id="f-status" class="field">
                    <option value="approved">تأییدشده</option>
                    <option value="pending">در انتظار بررسی</option>
                </select>
            </div>

            <div>
                <label class="lbl" for="f-national-id">شناسه ملی / کد ملی</label>
                <input id="f-national-id" class="field" dir="ltr" autocomplete="off">
            </div>

            <div>
                <label class="lbl" for="f-phone">تلفن سازمان</label>
                <input id="f-phone" class="field" dir="ltr" placeholder="02112345678" autocomplete="off">
            </div>

            <div>
                <label class="lbl" for="f-province">استان</label>
                <select id="f-province" class="field">
                    <option value="">— انتخاب استان —</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}">{{ $province->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="lbl" for="f-city">شهرستان</label>
                <select id="f-city" class="field" disabled>
                    <option value="">ابتدا استان را انتخاب کنید</option>
                </select>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="city_id"></p>
            </div>

                <div class="sm:col-span-2">
                    <label class="lbl" for="f-address">نشانی</label>
                    <textarea id="f-address" class="field min-h-20" rows="2" autocomplete="off"></textarea>
                </div>
            </div>

        {{-- مدیر سازمان --}}
        <div class="border-t border-stone-100 pt-4 space-y-3">
            <p class="text-xs font-bold text-stone-600 flex items-center gap-2">
                <span class="grid place-items-center size-6 rounded-lg bg-amber-50 text-amber-600">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </span>
                حساب مدیر سازمان
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="owner-basic-fields">
                <div>
                    <label class="lbl" for="f-owner-name">نام <span class="text-rose-500">*</span></label>
                    <input id="f-owner-name" class="field" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="owner_name"></p>
                </div>
                <div>
                    <label class="lbl" for="f-owner-family">نام خانوادگی</label>
                    <input id="f-owner-family" class="field" autocomplete="off">
                </div>
                <div>
                    <label class="lbl" for="f-owner-email">ایمیل (نام کاربری) <span class="text-rose-500">*</span></label>
                    <input id="f-owner-email" type="email" dir="ltr" class="field" autocomplete="off" placeholder="org@example.com">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="owner_email"></p>
                </div>
                <div>
                    <label class="lbl" for="f-owner-mobile">موبایل (اختیاری)</label>
                    <input id="f-owner-mobile" type="tel" dir="ltr" class="field" placeholder="09123456789" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="owner_mobile"></p>
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl" for="f-owner-password">رمز عبور <span id="pass-hint" class="font-normal text-stone-400">(حداقل ۸ کاراکتر)</span></label>
                    <input id="f-owner-password" type="password" dir="ltr" class="field" autocomplete="new-password">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="owner_password"></p>
                </div>
            </div>
        </div>

        </div>

        <div class="adm-modal-foot">
            <button type="submit" id="modal-save" class="btn-primary btn-shine !py-3">ذخیره سازمان</button>
            <button type="button" class="btn-ghost modal-close !py-3 px-5">انصراف</button>
        </div>
    </form>
</div>

{{-- مودال تغییر وضعیت --}}
<div id="status-modal" class="ui-modal-backdrop hidden">
    <div data-close-status class="absolute inset-0" aria-hidden="true"></div>
    <form id="status-form" class="ui-modal adm-modal-sm adm-modal-text-start" data-tone="warn" role="dialog" aria-modal="true" aria-labelledby="status-title" novalidate>
        <div class="adm-modal-head">
            <h3 class="text-sm font-extrabold text-stone-800" id="status-title">تغییر وضعیت سازمان</h3>
            <button type="button" class="adm-modal-x" data-close-status aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="adm-modal-body space-y-4">
            <div>
                <label class="lbl" for="s-status">وضعیت جدید</label>
                <select id="s-status" class="field">
                    <option value="approved">تأییدشده</option>
                    <option value="pending">در انتظار بررسی</option>
                    <option value="rejected">ردشده</option>
                    <option value="suspended">معلق (مدیر سازمان غیرفعال می‌شود)</option>
                </select>
            </div>
            <div>
                <label class="lbl" for="s-note">یادداشت (اختیاری)</label>
                <textarea id="s-note" class="field" rows="2"></textarea>
            </div>
        </div>
        <div class="adm-modal-foot">
            <button type="submit" id="status-save" class="btn-primary btn-shine !py-2.5">اعمال</button>
            <button type="button" class="btn-ghost !py-2.5" data-close-status>انصراف</button>
        </div>
    </form>
</div>


{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode(['status' => (string) request('status', '')]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/organizations/index.js') }}?v=28"></script>
<script src="{{ asset('back/assets/js/pages/trash.js') }}?v=1"></script>
@endpush
