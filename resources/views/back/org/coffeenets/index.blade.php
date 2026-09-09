@extends('back.layouts.org', ['organization' => $organization])

@section('title', 'کافی‌نت‌های من')
@section('page-title', 'کافی‌نت‌های من')
@section('breadcrumb', 'پنل سازمان ← کافی‌نت‌های من')

@section('content')

    {{-- بنر پاداش معرفی --}}
    @if ($referral->is_active && (float) $referral->introduction_reward > 0)
        <div class="card ui-lift p-4 mb-5 animate-fade-up bg-gradient-to-l from-emerald-50 to-white border-emerald-200/70 flex flex-wrap items-center gap-3 justify-between relative overflow-hidden">
            <span class="ui-orb" data-tone="emerald" data-pos="tr" aria-hidden="true"></span>
            <div class="flex items-center gap-3.5 relative">
                <span class="ui-chip" data-tone="ok">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/></svg>
                </span>
                <div>
                    <p class="text-sm font-extrabold text-emerald-800 flex items-center gap-2">
                        <span class="ui-dot text-emerald-500"></span>
                        پاداش معرفی فعال است
                    </p>
                    <p class="text-[11px] text-emerald-700/80 mt-0.5">با تأیید هر کافی‌نت جدید، <strong>{{ fa_money($referral->introduction_reward) }}</strong> به‌صورت خودکار به کیف پول شما واریز می‌شود.</p>
                </div>
            </div>
        </div>
    @endif

    <section class="card ui-lift animate-fade-up overflow-hidden">

        {{-- هدر جدول --}}
        <div class="px-5 py-4 border-b border-stone-100 flex flex-wrap items-center gap-3 justify-between">
            <div class="flex flex-1 min-w-52 flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-44 max-w-sm">
                    <input id="search-input" type="search" class="field !py-2.5 pl-10" placeholder="جستجو: نام کافی‌نت..." aria-label="جستجوی کافی‌نت‌ها">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </div>
                <select id="status-filter" class="field !py-2.5 !w-auto min-w-36" aria-label="فیلتر وضعیت">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending">در انتظار تأیید</option>
                    <option value="approved">تأییدشده</option>
                    <option value="rejected">ردشده</option>
                    <option value="suspended">معلق</option>
                </select>
            </div>
            <button type="button" id="btn-new" class="btn-primary btn-shine no-btn-teal">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                معرفی کافی‌نت جدید
            </button>
        </div>

        {{-- جدول --}}
        <div class="table-wrap">
            <table class="table-panel table-modern">
                <thead>
                    <tr>
                        <th>کافی‌نت</th>
                        <th>موقعیت</th>
                        <th>پاداش معرفی</th>
                        <th>وضعیت</th>
                        <th>تاریخ ثبت</th>
                    </tr>
                </thead>
                <tbody id="rows">
                    <tr><td colspan="5" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
                </tbody>
            </table>
        </div>

        {{-- صفحه‌بندی --}}
        <div id="pagination" class="px-5 py-4 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500"></div>
    </section>

    {{-- ================== مودال معرفی کافی‌نت (بر پایهٔ ui-modal) ================== --}}
    <div id="modal" class="ui-modal-backdrop no-modal-scroll hidden" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="no-modal-veil" data-close aria-hidden="true"></div>
        <form id="modal-form" class="ui-modal no-modal-form" novalidate>
            <div class="no-modal-head">
                <span class="no-modal-head-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/></svg>
                </span>
                <div class="min-w-0">
                    <h3 id="modal-title" class="text-sm font-extrabold text-stone-800">معرفی کافی‌نت جدید</h3>
                    <p class="text-[11px] text-stone-400 mt-0.5">ثبت اولیه و ارسال برای بررسی مدیریت کل</p>
                </div>
                <button type="button" class="no-modal-close modal-close" aria-label="بستن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="p-5 sm:p-6 space-y-4">
                <p class="ui-note">
                    کافی‌نت با وضعیت «در انتظار تأیید» ثبت می‌شود و پس از بررسی و تأیید مدیریت کل فعال خواهد شد.
                    مدیر کافی‌نت توسط مدیریت کل تعیین می‌شود.
                </p>

            <div>
                <label class="lbl" for="f-name">نام کافی‌نت <span class="text-rose-500">*</span></label>
                <input id="f-name" class="field" autocomplete="off" required placeholder="کافی‌نت نمونه">
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
            </div>

            <div>
                <label class="lbl" for="f-phone">تلفن (اختیاری)</label>
                <input id="f-phone" class="field" dir="ltr" autocomplete="off">
            </div>

            <div class="grid grid-cols-2 gap-3">
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
                        <option value="">ابتدا استان…</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="lbl" for="f-address">نشانی (اختیاری)</label>
                <textarea id="f-address" class="field min-h-20" rows="2" autocomplete="off"></textarea>
            </div>

            <div class="no-modal-foot">
                <button type="button" class="btn-ghost !py-2.5 !text-xs modal-close">انصراف</button>
                <button type="submit" id="modal-save" class="btn-primary btn-shine no-btn-teal !py-2.5 !text-xs">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                    ثبت برای بررسی
                </button>
            </div>
        </form>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/org/coffeenets/index.js') }}"></script>
@endpush
