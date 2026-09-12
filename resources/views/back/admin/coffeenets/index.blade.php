@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'کافی‌نت‌ها')
@section('page-title', 'مدیریت کافی‌نت‌ها')
@section('breadcrumb', 'پنل مدیریت کل ← کافی‌نت‌ها')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/trash.css') }}?v=1">
@endpush

@section('content')

<section class="card ui-lift animate-fade-up overflow-hidden">

    {{-- هدر جدول --}}
    <div class="adm-card-head">
        <div class="flex flex-1 min-w-64 flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-44 max-w-sm">
                <input id="search-input" type="search" class="field !py-2.5 pl-10" placeholder="جستجو: نام کافی‌نت، سازمان..." aria-label="جستجوی کافی‌نت‌ها">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <select id="status-filter" class="field !py-2.5 !w-auto min-w-36" aria-label="فیلتر وضعیت">
                <option value="">همه وضعیت‌ها</option>
                <option value="pending">در انتظار تأیید</option>
                <option value="approved">تأییدشده</option>
                <option value="rejected">ردشده</option>
                <option value="suspended">معلق</option>
            </select>
            <select id="org-filter" class="field !py-2.5 !w-auto min-w-40" aria-label="فیلتر سازمان">
                <option value="">همه (مستقل + زیرمجموعه)</option>
                <option value="0">فقط مستقل</option>
                @foreach ($organizations as $org)
                    <option value="{{ $org->id }}">سازمان {{ $org->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="button" id="btn-new" class="btn-primary btn-shine ui-press">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            ثبت کافی‌نت جدید
        </button>
    </div>

    {{-- جدول --}}
    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>کافی‌نت</th>
                    <th>وابستگی</th>
                    <th>مدیر</th>
                    <th>موقعیت</th>
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
            <h3 id="modal-title" class="text-sm font-extrabold text-stone-800">ثبت کافی‌نت جدید</h3>
            <button type="button" class="adm-modal-x modal-close" aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            <p class="ui-note">
                کاربر «مدیر کافی‌نت» همزمان ساخته می‌شود (پنل کاملش در فاز ۳). سازمان خالی = کافی‌نت مستقل.
            </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
                <label class="lbl" for="f-name">نام کافی‌نت <span class="text-rose-500">*</span></label>
                <input id="f-name" class="field" autocomplete="off" required>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
            </div>

            <div class="sm:col-span-2">
                <label class="lbl" for="f-org">سازمان (وابستگی)</label>
                <select id="f-org" class="field">
                    <option value="">مستقل — بدون سازمان</option>
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-stone-400 mt-1.5">در صورت انتخاب سازمان، پاداش معرفی پس از تأیید پرداخت می‌شود</p>
            </div>

            <div>
                <label class="lbl" for="f-phone">تلفن</label>
                <input id="f-phone" class="field" dir="ltr" autocomplete="off">
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
            </div>

            <div id="approve-wrap">
                <label class="lbl" for="f-approve">ثبت و تأیید فوری</label>
                <label class="flex items-center gap-2.5 rounded-xl border border-stone-200 px-3.5 py-2.5 cursor-pointer hover:bg-stone-50 transition-colors">
                    <input type="checkbox" id="f-approve" class="size-4 accent-amber-600">
                    <span class="text-xs font-semibold text-stone-600">بدون نیاز به تأیید مجدد فعال شود</span>
                </label>
            </div>

            <div class="sm:col-span-2">
                <label class="lbl" for="f-address">نشانی</label>
                <textarea id="f-address" class="field min-h-20" rows="2" autocomplete="off"></textarea>
            </div>
        </div>

        {{-- مدیر کافی‌نت --}}
        <div class="border-t border-stone-100 pt-4 space-y-3">
            <p class="text-xs font-bold text-stone-600 flex items-center gap-2">
                <span class="grid place-items-center size-6 rounded-lg bg-amber-50 text-amber-600">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </span>
                حساب مدیر کافی‌نت
            </p>

            <p id="no-manager-hint" class="hidden text-[11px] leading-5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 px-3 py-2">
                این کافی‌نت هنوز <b>کاربر مدیر (اطلاعات ورود) ندارد</b> — مثلاً کافی‌نت معرفی‌شده توسط سازمان.
                نام، ایمیل و رمز را پر کنید تا حساب مدیر ساخته شود؛ بدون آن، اعلان‌های کافی‌نت گیرنده‌ای ندارد.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="manager-basic-fields">
                <div>
                    <label class="lbl" for="f-manager-name">نام <span class="text-rose-500">*</span></label>
                    <input id="f-manager-name" class="field" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="manager_name"></p>
                </div>
                <div>
                    <label class="lbl" for="f-manager-family">نام خانوادگی</label>
                    <input id="f-manager-family" class="field" autocomplete="off">
                </div>
                <div>
                    <label class="lbl" for="f-manager-email">ایمیل (نام کاربری) <span class="text-rose-500">*</span></label>
                    <input id="f-manager-email" type="email" dir="ltr" class="field" autocomplete="off" placeholder="net@example.com">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="manager_email"></p>
                </div>
                <div>
                    <label class="lbl" for="f-manager-mobile">موبایل (اختیاری)</label>
                    <input id="f-manager-mobile" type="tel" dir="ltr" class="field" placeholder="09123456789" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="manager_mobile"></p>
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl" for="f-manager-password">رمز عبور <span id="pass-hint" class="font-normal text-stone-400">(حداقل ۸ کاراکتر)</span></label>
                    <input id="f-manager-password" type="password" dir="ltr" class="field" autocomplete="new-password">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="manager_password"></p>
                </div>
            </div>
        </div>

        <div class="adm-modal-foot">
            <button type="submit" id="modal-save" class="btn-primary btn-shine !py-3">ذخیره کافی‌نت</button>
            <button type="button" class="btn-ghost modal-close !py-3 px-5">انصراف</button>
        </div>
    </form>
</div>

{{-- مودال تأیید عملیات وضعیت — به PanelUI.confirm منتقل شد (coffeenets/index.js) --}}


{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode(['referral_reward' => $referralActive ? $referralReward : 0, 'status' => (string) request('status', '')]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/coffeenets/index.js') }}?v=28"></script>
<script src="{{ asset('back/assets/js/pages/trash.js') }}?v=1"></script>
@endpush
