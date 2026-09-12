@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'مشتریان')
@section('page-title', 'مدیریت مشتریان')
@section('breadcrumb', 'پنل مدیریت کل ← مشتریان')

@push('styles')
    {{-- توکن‌های تم (فاز ۱۰) — مصرف‌کننده، اگر layout قبلاً لینک کرده باشد لینک تکراری بی‌ضرر است --}}
    <link rel="stylesheet" href="{{ asset('assets/css/pages/org-operators.css') }}">
@endpush

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/trash.css') }}?v=1">
@endpush

@section('content')

<section class="card ui-lift animate-fade-up overflow-hidden">

    {{-- هدر جدول --}}
    <div class="adm-card-head">
        <div class="flex flex-1 min-w-64 flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-44 max-w-sm">
                <input id="search-input" type="search" class="field !py-2.5 pl-10" placeholder="جستجو: نام، موبایل، ایمیل..." aria-label="جستجوی مشتریان">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <select id="status-filter" class="field !py-2.5 !w-auto min-w-32" aria-label="فیلتر وضعیت">
                <option value="">همه وضعیت‌ها</option>
                <option value="active">فعال</option>
                <option value="banned">مسدود (بن)</option>
            </select>
            <select id="profile-filter" class="field !py-2.5 !w-auto min-w-36" aria-label="فیلتر پروفایل">
                <option value="">همه پروفایل‌ها</option>
                <option value="complete">کامل</option>
                <option value="incomplete">ناقص</option>
            </select>
            <select id="province-filter" class="field !py-2.5 !w-auto min-w-36" aria-label="فیلتر استان">
                <option value="">همه استان‌ها</option>
                @foreach ($provinces as $province)
                    <option value="{{ $province->id }}">{{ $province->name }}</option>
                @endforeach
            </select>
            <select id="sort-filter" class="field !py-2.5 !w-auto min-w-36" aria-label="مرتب‌سازی">
                <option value="newest">جدیدترین عضویت</option>
                <option value="old">قدیمی‌ترین عضویت</option>
                <option value="orders"> بیشترین سفارش</option>
                <option value="spent">بیشترین پرداخت</option>
                <option value="wallet">بیشترین موجودی کیف</option>
                <option value="login">آخرین ورود</option>
            </select>
        </div>
    </div>

    {{-- جدول --}}
    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>مشتری</th>
                    <th>استان / شهر</th>
                    <th>وضعیت</th>
                    <th>پروفایل</th>
                    <th class="text-center">سفارش‌ها</th>
                    <th class="text-center">مجموع پرداخت</th>
                    <th class="text-center">کیف پول</th>
                    <th>آخرین ورود</th>
                    <th class="text-center">عملیات</th>
                </tr>
            </thead>
            <tbody id="rows">
                <tr><td colspan="9" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
            </tbody>
        </table>
    </div>

    {{-- صفحه‌بندی --}}
    <div id="pagination" class="adm-table-foot text-xs text-stone-500"></div>
</section>

{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode([
    'geoCitiesUrl' => route('admin.geo.cities'),
], JSON_UNESCAPED_UNICODE) }}"></div>

{{-- ================== مودال ویرایش کامل مشتری ================== --}}
<div id="edit-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="edit-title">
    <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

    <form id="edit-form" class="ui-modal adm-modal-lg adm-modal-text-start" data-tone="info" role="document" novalidate>
        <div class="adm-modal-head">
            <div>
                <h3 id="edit-title" class="text-sm font-extrabold text-stone-800">ویرایش اطلاعات مشتری</h3>
                <p id="edit-subtitle" class="text-[11px] text-stone-400 mt-0.5">—</p>
            </div>
            <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            <input type="hidden" id="f-id" value="">

            <div class="ui-note" data-tone="info">
                مدیر کل می‌تواند <strong>همهٔ اطلاعات</strong> مشتری را ویرایش کند: مشخصات فردی، جغرافیا، تاریخ تولد (شمسی)، وضعیت پروفایل و حساب.
                موبایل، شمارهٔ ورود مشتری به اپ است و پس از تغییر، ورود با شمارهٔ جدید انجام می‌شود.
            </div>

            {{-- مشخصات فردی --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="lbl" for="f-name">نام <span class="text-rose-500">*</span></label>
                    <input id="f-name" class="field" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
                </div>
                <div>
                    <label class="lbl" for="f-family">نام خانوادگی</label>
                    <input id="f-family" class="field" autocomplete="off">
                </div>
                <div>
                    <label class="lbl" for="f-mobile">موبایل (شمارهٔ ورود) <span class="text-rose-500">*</span></label>
                    <input id="f-mobile" type="tel" dir="ltr" class="field" placeholder="09xxxxxxxxx" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="mobile"></p>
                </div>
                <div>
                    <label class="lbl" for="f-email">ایمیل</label>
                    <input id="f-email" type="email" dir="ltr" class="field" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="email"></p>
                </div>
                <div>
                    <label class="lbl" for="f-gender">جنسیت</label>
                    <select id="f-gender" class="field">
                        <option value="">نامشخص</option>
                        <option value="male">مرد</option>
                        <option value="female">زن</option>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="f-birthdate">تاریخ تولد (شمسی)</label>
                    <input id="f-birthdate" type="text" dir="ltr" class="field num !text-center" placeholder="۱۳۷۰/۰۵/۱۲"
                           data-jdp data-jdp-min-years-ago="100" data-jdp-max-years-ago="10" title="برای انتخاب تاریخ کلیک کنید">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="birthdate"></p>
                </div>
                <div>
                    <label class="lbl" for="f-province">استان</label>
                    <select id="f-province" class="field">
                        <option value="">انتخاب استان…</option>
                        @foreach ($provinces as $province)
                            <option value="{{ $province->id }}">{{ $province->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="lbl" for="f-city">شهر</label>
                    <select id="f-city" class="field">
                        <option value="">ابتدا استان را انتخاب کنید…</option>
                    </select>
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="city_id"></p>
                </div>
            </div>

            <hr class="border-stone-100">

            {{-- وضعیت حساب --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                <div class="flex items-end gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input id="f-profile-completed" type="checkbox" class="size-4 accent-amber-600">
                        <span class="text-xs font-bold text-stone-700">پروفایل کامل</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input id="f-is-active" type="checkbox" class="size-4 accent-amber-600">
                        <span class="text-xs font-bold text-stone-700">حساب فعال</span>
                    </label>
                </div>
                <div>
                    <label class="lbl" for="f-password">رمز عبور جدید</label>
                    <input id="f-password" type="password" dir="ltr" class="field" placeholder="(خالی = بدون تغییر)" autocomplete="new-password">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="password"></p>
                </div>
            </div>

            <p id="edit-error" class="field-error hidden"></p>
        </div>

        <div class="adm-modal-foot">
            <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
            <button type="submit" id="edit-save" class="btn-primary btn-shine !py-2.5 !text-xs">ذخیرهٔ تغییرات</button>
        </div>
    </form>
</div>

{{-- ================== مودال مسدودسازی (بن) ================== --}}
<div id="ban-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="ban-title">
    <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

    <form id="ban-form" class="ui-modal adm-modal-md adm-modal-text-start" data-tone="danger" role="document" novalidate>
        <div class="adm-modal-head">
            <div>
                <h3 id="ban-title" class="text-sm font-extrabold text-stone-800">مسدودسازی مشتری</h3>
                <p id="ban-subtitle" class="text-[11px] text-stone-400 mt-0.5">—</p>
            </div>
            <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body">
            <div class="ui-note" data-tone="danger">
                مشتری مسدودشده <strong>نمی‌تواند وارد اپ شود</strong> (ورود با کد تأیید رد می‌شود) و
                همهٔ جلسه‌های فعال او فوراً بسته می‌شوند. سفارش‌ها و تاریخچه‌اش حفظ می‌شود.
            </div>

            <div class="mt-4">
                <label class="lbl" for="ban-reason">دلیل مسدودسازی <span class="text-rose-500">*</span></label>
                <textarea id="ban-reason" rows="3" maxlength="490" class="field !text-xs w-full"
                          placeholder="مثلاً: تخلف در ثبت سفارش / فیش جعلی / رفتار نامناسب با اپراتور…"></textarea>
                <p id="ban-error" class="field-error mt-2 hidden">حداقل ۳ حرف لازم است.</p>
            </div>
        </div>

        <div class="adm-modal-foot">
            <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
            <button type="submit" id="ban-save" class="ui-btn-danger !py-2.5 !text-xs">مسدودسازی حساب</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/customers/index.js') }}?v=28"></script>
<script src="{{ asset('back/assets/js/pages/trash.js') }}?v=1"></script>
@endpush
