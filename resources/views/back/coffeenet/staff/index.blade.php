@extends('back.coffeenet.layouts.panel')

@section('title', 'کارمندان')
@section('page-title', 'کارمندان')
@section('breadcrumb', 'پنل کافی‌نت ← کارمندان')

@section('content')

    {{-- نوار ابزار --}}
    <section class="card p-4 sm:p-5 animate-fade-up">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">

            <div class="relative flex-1 min-w-0">
                <input id="staff-search" type="search" class="field !py-2.5 pl-10" placeholder="جستجو بر اساس نام، ایمیل یا موبایل…" aria-label="جستجوی کارمندان">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4.5 text-stone-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>

            <select id="position-filter" class="field !py-2.5 !w-auto !text-xs min-w-36" aria-label="فیلتر سمت">
                <option value="">همه سمت‌ها</option>
                <option value="manager">فقط مدیران</option>
                <option value="operator">فقط اپراتورها</option>
            </select>

            <select id="status-filter" class="field !py-2.5 !w-auto !text-xs min-w-32" aria-label="فیلتر وضعیت">
                <option value="">همه وضعیت‌ها</option>
                <option value="1">فعال</option>
                <option value="0">غیرفعال</option>
            </select>

            <button type="button" id="btn-add" class="btn-primary btn-shine !py-2.5 !text-xs shrink-0">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                افزودن کارمند
            </button>
        </div>
    </section>

    {{-- جدول --}}
    <section class="card mt-4 animate-fade-up delay-1 overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between">
            <h2 class="no-card-title text-sm font-extrabold text-stone-700">لیست کارمندان</h2>
            <span id="rows-summary" class="text-[11px] text-stone-400"></span>
        </div>

        <div class="table-wrap">
            <table class="table-panel table-modern">
                <thead>
                <tr>
                    <th>کارمند</th>
                    <th>موبایل</th>
                    <th>سمت</th>
                    <th>مدل حقوق</th>
                    <th>دسترسی‌ها</th>
                    <th>وضعیت</th>
                    <th>آخرین ورود</th>
                    <th class="!text-center">عملیات</th>
                </tr>
                </thead>
                <tbody id="staff-tbody">
                <tr><td colspan="8" class="!py-10 text-center text-stone-400 text-xs">در حال بارگذاری…</td></tr>
                </tbody>
            </table>
        </div>

        <div id="staff-pagination" class="px-5 py-4 border-t border-stone-100 flex items-center justify-between gap-3 flex-wrap"></div>
    </section>

    {{-- ================== مودال افزودن/ویرایش (بر پایهٔ ui-modal) ================== --}}
    <div id="staff-modal" class="ui-modal-backdrop no-modal-scroll hidden" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="no-modal-veil" data-close-modal aria-hidden="true"></div>

        <form id="staff-form" class="ui-modal no-modal-form" novalidate>

            {{-- سربرگ --}}
            <div class="no-modal-head">
                <span class="no-modal-head-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <div class="min-w-0">
                    <h3 id="modal-title" class="text-sm font-extrabold text-stone-800">افزودن کارمند جدید</h3>
                    <p id="modal-subtitle" class="text-[11px] text-stone-400 mt-0.5">حساب کاربری، دسترسی‌ها و مدل حقوق در یک گام</p>
                </div>
                <button type="button" class="no-modal-close" data-close-modal aria-label="بستن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

                <div class="p-5 sm:p-6 space-y-6 max-h-[70vh] overflow-y-auto">

                    {{-- خطای کلی --}}
                    <p id="modal-error" class="hidden text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3.5 py-2.5 leading-6"></p>

                    {{-- ۱. اطلاعات هویتی --}}
                    <fieldset>
                        <legend class="text-xs font-extrabold text-stone-500 mb-3">۱) اطلاعات هویتی</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl" for="f-name">نام <span class="text-rose-500">*</span></label>
                                <input id="f-name" name="name" type="text" class="field" placeholder="مثلاً رضا" autocomplete="off">
                                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
                            </div>
                            <div>
                                <label class="lbl" for="f-family">نام خانوادگی</label>
                                <input id="f-family" name="family" type="text" class="field" placeholder="مثلاً محمدی" autocomplete="off">
                            </div>
                            <div>
                                <label class="lbl" for="f-email">ایمیل <span class="text-rose-500">*</span></label>
                                <input id="f-email" name="email" type="email" dir="ltr" class="field" placeholder="user@example.com" autocomplete="off">
                                <p class="text-[10px] text-stone-400 mt-1" id="email-hint">اگر قبلاً در سیستم ثبت شده باشد، به همین کافی‌نت متصل می‌شود.</p>
                                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="email"></p>
                            </div>
                            <div>
                                <label class="lbl" for="f-mobile">موبایل</label>
                                <input id="f-mobile" name="mobile" type="text" dir="ltr" class="field" placeholder="09123456789" autocomplete="off">
                                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="mobile"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="lbl" for="f-password">رمز عبور <span id="password-req" class="text-rose-500">*</span></label>
                                <input id="f-password" name="password" type="password" dir="ltr" class="field" placeholder="حداقل ۸ کاراکتر" autocomplete="new-password">
                                <p class="text-[10px] text-stone-400 mt-1" id="password-hint">برای کاربر جدید الزامی است؛ هنگام ویرایش خالی بگذارید تا تغییر نکند.</p>
                                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="password"></p>
                            </div>
                        </div>
                    </fieldset>

                    {{-- ۲. سمت و دسترسی‌ها --}}
                    <fieldset>
                        <legend class="text-xs font-extrabold text-stone-500 mb-3">۲) سمت و دسترسی‌ها</legend>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="position-card cursor-pointer rounded-2xl border-2 border-stone-200 px-4 py-3.5 flex items-center gap-3 transition-all has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50/60">
                                <input type="radio" name="position" value="operator" class="accent-amber-600 size-4" checked>
                                <div class="min-w-0">
                                    <p class="text-xs font-extrabold text-stone-700">اپراتور</p>
                                    <p class="text-[10px] text-stone-400 mt-0.5">دسترسی‌ها قابل شخصی‌سازی</p>
                                </div>
                            </label>
                            <label class="position-card cursor-pointer rounded-2xl border-2 border-stone-200 px-4 py-3.5 flex items-center gap-3 transition-all has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50/60">
                                <input type="radio" name="position" value="manager" class="accent-amber-600 size-4">
                                <div class="min-w-0">
                                    <p class="text-xs font-extrabold text-stone-700">مدیر کافی‌نت</p>
                                    <p class="text-[10px] text-stone-400 mt-0.5">دسترسی کامل به پنل</p>
                                </div>
                            </label>
                        </div>

                        <div id="permissions-box" class="mt-4 rounded-2xl border border-stone-200 bg-stone-50/50 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-xs font-bold text-stone-600">دسترسی‌های این اپراتور</p>
                                <div class="flex items-center gap-2 text-[11px]">
                                    <button type="button" id="perm-all" class="text-amber-700 hover:underline">انتخاب همه</button>
                                    <span class="text-stone-300">|</span>
                                    <button type="button" id="perm-none" class="text-amber-700 hover:underline">حذف همه</button>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                @foreach ($permissionCatalog as $key => $label)
                                    <label class="perm-item cursor-pointer flex items-center gap-2.5 rounded-xl bg-white border border-stone-200 px-3 py-2.5 text-xs text-stone-600 hover:border-amber-300 transition-colors">
                                        <input type="checkbox" name="permissions" value="{{ $key }}" class="perm-check accent-amber-600 size-4 shrink-0">
                                        <span class="min-w-0">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-[10px] text-stone-400 mt-3 leading-5">این دسترسی‌ها فقط محدوده فعالیت اپراتور را در همین کافی‌نت تعیین می‌کنند و از فاز ۶ (سفارش‌ها) به بعد اعمال می‌شوند.</p>
                        </div>
                    </fieldset>

                    {{-- ۳. مدل حقوق --}}
                    <fieldset>
                        <legend class="text-xs font-extrabold text-stone-500 mb-3">۳) مدل حقوق</legend>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="lbl" for="f-salary-type">نوع قرارداد <span class="text-rose-500">*</span></label>
                                <select id="f-salary-type" name="salary_type" class="field">
                                    <option value="percent">درصدی از هر سفارش</option>
                                    <option value="fixed_per_order">مبلغ ثابت هر سفارش</option>
                                    <option value="monthly">ماهیانه</option>
                                </select>
                            </div>
                            <div>
                                <label class="lbl" for="f-salary-rate"><span id="rate-label">درصد</span> <span class="text-rose-500">*</span></label>
                                <input id="f-salary-rate" name="salary_rate" type="number" min="0" max="1000000000" step="0.01" dir="ltr" class="field" placeholder="مثلاً 30">
                                <p class="text-[10px] text-stone-400 mt-1" id="rate-hint">سهم کارمند از مبلغ سفارش (۰ تا ۱۰۰)</p>
                                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="salary_rate"></p>
                            </div>
                            <div>
                                <label class="lbl" for="f-overtime">نرخ اضافه‌کار <span class="text-stone-400 font-normal">(اختیاری)</span></label>
                                <input id="f-overtime" name="overtime_rate" type="number" min="0" step="0.01" dir="ltr" class="field" placeholder="مثلاً 250000">
                                <p class="text-[10px] text-stone-400 mt-1">تومان — صرفاً گزارشی برای ماهیانه</p>
                            </div>
                        </div>
                    </fieldset>
                </div>

                {{-- فوتر --}}
                <div class="no-modal-foot">
                    <button type="button" class="btn-ghost !py-2.5 !text-xs" data-close-modal>انصراف</button>
                    <button type="submit" id="btn-save" class="btn-primary btn-shine !py-2.5 !text-xs !px-6">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg>
                        ذخیره کارمند
                    </button>
                </div>
            </form>
    </div>


{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode(['catalog' => $permissionCatalog, 'defaults' => $permissionDefaults, 'base' => '/coffeenet/' . $coffeenet->id . '/staff']) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/coffeenet/staff/index.js') }}"></script>
@endpush
