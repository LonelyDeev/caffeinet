@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'کارکنان و اپراتورها')
@section('page-title', 'کارکنان و اپراتورهای شبکه')
@section('breadcrumb', 'پنل مدیریت کل ← کارکنان و اپراتورها')

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
            <button type="button" id="btn-add-operator" class="btn-primary btn-shine ui-press !py-2.5 !px-4 !text-xs shrink-0" title="افزودن اپراتور جدید با تعیین کافی‌نت (اجباری)">
                <span class="inline-block size-4 align-middle me-1" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg></span>
                افزودن اپراتور
            </button>
            <div class="relative flex-1 min-w-44 max-w-sm">
                <input id="search-input" type="search" class="field !py-2.5 pl-10" placeholder="جستجو: نام کارمند، موبایل، کافی‌نت..." aria-label="جستجوی کارکنان">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <select id="position-filter" class="field !py-2.5 !w-auto min-w-32" aria-label="فیلتر سمت">
                <option value="">همه سمت‌ها</option>
                <option value="manager">مدیر کافی‌نت</option>
                <option value="operator">اپراتور</option>
            </select>
            <select id="active-filter" class="field !py-2.5 !w-auto min-w-32" aria-label="فیلتر وضعیت">
                <option value="">همه وضعیت‌ها</option>
                <option value="1">فعال</option>
                <option value="0">غیرفعال</option>
                <option value="pending">در انتظار تایید</option>
            </select>
            <select id="coffeenet-filter" class="field !py-2.5 !w-auto min-w-40" aria-label="فیلتر کافی‌نت">
                <option value="">همهٔ کافی‌نت‌ها</option>
                @foreach ($coffeenets as $net)
                    <option value="{{ $net->id }}">{{ $net->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- جدول --}}
    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>کارمند</th>
                    <th>کافی‌نت</th>
                    <th>سمت</th>
                    <th>وضعیت</th>
                    <th class="text-center">سفارش‌های انجام‌شده</th>
                    <th class="text-center">در جریان</th>
                    <th class="text-center">پیام‌های چت</th>
                    <th>تاریخ عضویت</th>
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
    'permissions' => \App\Support\OperatorPermissions::CATALOG,
], JSON_UNESCAPED_UNICODE) }}"></div>

{{-- ================== مودال ویرایش کامل کارمند (درخواست بازخوردی) ================== --}}
<div id="edit-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="edit-title">
    <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

    <form id="edit-form" class="ui-modal adm-modal-lg adm-modal-text-start" data-tone="info" role="document" novalidate>
        <div class="adm-modal-head">
            <div>
                <h3 id="edit-title" class="text-sm font-extrabold text-stone-800">ویرایش کارمند</h3>
                <p id="edit-subtitle" class="text-[11px] text-stone-400 mt-0.5">—</p>
            </div>
            <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            <input type="hidden" id="f-assignment-id" value="">

            <div class="ui-note" data-tone="info">
                مدیر کل می‌تواند <strong>همه‌چیز</strong> را ویرایش کند: اطلاعات ورود (ایمیل/رمز)، کافی‌نت محل کار، سمت، دسترسی‌ها و مدل حقوق.
                انتقال به کافی‌نت دیگر تاریخچهٔ سفارش‌ها را حفظ می‌کند.
            </div>

            {{-- اطلاعات حساب --}}
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
                    <label class="lbl" for="f-email">ایمیل ورود <span class="text-rose-500">*</span></label>
                    <input id="f-email" type="email" dir="ltr" class="field" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="email"></p>
                </div>
                <div>
                    <label class="lbl" for="f-mobile">موبایل</label>
                    <input id="f-mobile" type="tel" dir="ltr" class="field" placeholder="09xxxxxxxxx" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="mobile"></p>
                </div>
                <div>
                    <label class="lbl" for="f-password">رمز عبور جدید</label>
                    <input id="f-password" type="password" dir="ltr" class="field" placeholder="(خالی = بدون تغییر)" autocomplete="new-password">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="password"></p>
                </div>
                <div class="flex items-end gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input id="f-user-active" type="checkbox" class="size-4 accent-amber-600">
                        <span class="text-xs font-bold text-stone-700">حساب فعال</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input id="f-assignment-active" type="checkbox" class="size-4 accent-amber-600">
                        <span class="text-xs font-bold text-stone-700">عضویت فعال</span>
                    </label>
                </div>
            </div>

            <hr class="border-stone-100">

            {{-- محل کار + سمت --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="lbl" for="f-coffeenet">کافی‌نت محل کار <span class="text-rose-500">*</span></label>
                    <select id="f-coffeenet" class="field">
                        <option value="">انتخاب کافی‌نت…</option>
                        @foreach ($coffeenets as $net)
                            <option value="{{ $net->id }}">{{ $net->name }}</option>
                        @endforeach
                    </select>
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="coffeenet_id"></p>
                </div>
                <div>
                    <label class="lbl" for="f-position">سمت</label>
                    <select id="f-position" class="field">
                        <option value="operator">اپراتور</option>
                        <option value="manager">مدیر کافی‌نت</option>
                    </select>
                </div>
            </div>

            {{-- دسترسی‌ها (فقط برای اپراتور) --}}
            <div id="perms-box" class="rounded-2xl border border-stone-100 bg-stone-50/50 p-4">
                <p class="text-[11px] font-extrabold text-stone-500 mb-3">دسترسی‌های اپراتور</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach (\App\Support\OperatorPermissions::CATALOG as $key => $label)
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-stone-600">
                            <input type="checkbox" class="perm-check size-4 accent-amber-600" value="{{ $key }}">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- مدل حقوق --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="lbl" for="f-salary-type">مدل حقوق</label>
                    <select id="f-salary-type" class="field">
                        <option value="percent">درصدی از سفارش</option>
                        <option value="fixed_per_order">ثابت هر سفارش</option>
                        <option value="monthly">ماهانه</option>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="f-salary-rate">مقدار <span class="text-stone-400 text-[10px]">(٪ یا تومان)</span></label>
                    <input id="f-salary-rate" type="number" min="0" step="any" dir="ltr" class="field">
                </div>
                <div>
                    <label class="lbl" for="f-overtime-rate">اضافه‌کاری (تومان)</label>
                    <input id="f-overtime-rate" type="number" min="0" step="any" dir="ltr" class="field" placeholder="اختیاری">
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

{{-- ================== مودال افزودن اپراتور (مدیر کل — فاز ۱۳) ================== --}}
<div id="add-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="add-title">
    <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

    <form id="add-form" class="ui-modal adm-modal-lg adm-modal-text-start" data-tone="success" role="document" novalidate>
        <div class="adm-modal-head">
            <div>
                <h3 id="add-title" class="text-sm font-extrabold text-stone-800">افزودن اپراتور جدید</h3>
                <p class="text-[11px] text-stone-400 mt-0.5">مدیر کل — تعیین کافی‌نت <b class="text-amber-600">اجباری</b> است</p>
            </div>
            <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            <div class="ui-note" data-tone="success">
                اپراتور با ایمیل + رمز عبور ساخته می‌شود و <strong>بلافاصله فعال</strong> خواهد بود (بدون نیاز به تایید).
                هر اپراتور فقط در <strong>یک کافی‌نت</strong> فعالیت می‌کند؛ انتقال بعدی فقط از مسیر «ویرایش» توسط شما انجام می‌شود.
            </div>

            {{-- اطلاعات حساب --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="lbl" for="a-name">نام <span class="text-rose-500">*</span></label>
                    <input id="a-name" class="field" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
                </div>
                <div>
                    <label class="lbl" for="a-family">نام خانوادگی</label>
                    <input id="a-family" class="field" autocomplete="off">
                </div>
                <div>
                    <label class="lbl" for="a-email">ایمیل ورود <span class="text-rose-500">*</span></label>
                    <input id="a-email" type="email" dir="ltr" class="field" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="email"></p>
                </div>
                <div>
                    <label class="lbl" for="a-mobile">موبایل</label>
                    <input id="a-mobile" type="tel" dir="ltr" class="field" placeholder="09xxxxxxxxx (اختیاری)" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="mobile"></p>
                </div>
                <div>
                    <label class="lbl" for="a-password">رمز عبور <span class="text-rose-500">*</span></label>
                    <input id="a-password" type="password" dir="ltr" class="field" placeholder="حداقل ۸ کاراکتر" autocomplete="new-password">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="password"></p>
                </div>
                <div>
                    <label class="lbl" for="a-coffeenet">کافی‌نت محل کار <span class="text-rose-500">*</span></label>
                    <select id="a-coffeenet" class="field">
                        <option value="">انتخاب کافی‌نت (اجباری)…</option>
                        @foreach ($coffeenets as $net)
                            <option value="{{ $net->id }}">{{ $net->name }}</option>
                        @endforeach
                    </select>
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="coffeenet_id"></p>
                </div>
            </div>

            <hr class="border-stone-100">

            {{-- سمت + دسترسی‌ها --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="lbl" for="a-position">سمت</label>
                    <select id="a-position" class="field">
                        <option value="operator">اپراتور</option>
                        <option value="manager">مدیر کافی‌نت</option>
                    </select>
                </div>
            </div>

            <div id="a-perms-box" class="rounded-2xl border border-stone-100 bg-stone-50/50 p-4">
                <p class="text-[11px] font-extrabold text-stone-500 mb-3">دسترسی‌های اپراتور (پیش‌فرض: همگی منطقی)</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach (\App\Support\OperatorPermissions::CATALOG as $key => $label)
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-stone-600">
                            <input type="checkbox" class="a-perm-check size-4 accent-amber-600" value="{{ $key }}" {{ in_array($key, \App\Support\OperatorPermissions::defaults(), true) ? 'checked' : '' }}>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- مدل حقوق --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="lbl" for="a-salary-type">مدل حقوق</label>
                    <select id="a-salary-type" class="field">
                        <option value="percent">درصدی از سفارش</option>
                        <option value="fixed_per_order">ثابت هر سفارش</option>
                        <option value="monthly">ماهانه</option>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="a-salary-rate">مقدار <span class="text-stone-400 text-[10px]">(٪ یا تومان)</span></label>
                    <input id="a-salary-rate" type="number" min="0" step="any" dir="ltr" class="field" value="50">
                </div>
                <div>
                    <label class="lbl" for="a-overtime-rate">اضافه‌کاری (تومان)</label>
                    <input id="a-overtime-rate" type="number" min="0" step="any" dir="ltr" class="field" placeholder="اختیاری">
                </div>
            </div>

            <p id="add-error" class="field-error hidden"></p>
        </div>

        <div class="adm-modal-foot">
            <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
            <button type="submit" id="add-save" class="btn-primary btn-shine !py-2.5 !text-xs">ایجاد اپراتور</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/operators/index.js') }}?v=28"></script>
<script src="{{ asset('back/assets/js/pages/trash.js') }}?v=1"></script>
@endpush
