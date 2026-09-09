@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'قواعد کمیسیون')
@section('page-title', 'قواعد کمیسیون')
@section('breadcrumb', 'پنل مدیریت کل ← مالی و کمیسیون ← قواعد کمیسیون')

@section('content')

{{-- راهنما --}}
<section class="card ui-lift p-5 animate-fade-up relative overflow-hidden">
    <span class="ui-orb" data-tone="amber" data-pos="tr" aria-hidden="true"></span>
    <div class="flex items-start gap-3 relative">
        <span class="ui-chip" data-tone="amber shrink-0">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </span>
        <div class="text-xs text-stone-500 leading-6 min-w-0">
            <p>قاعدهٔ <strong class="text-stone-700">سراسری</strong> روی همهٔ خدمت‌ها اعمال می‌شود؛ برای هر خدمت می‌توان <strong class="text-stone-700">قاعدهٔ اختصاصی</strong> ثبت کرد که اولویت دارد (به‌محض فعال بودن).</p>
            <p>تسویه فقط روی <strong class="text-amber-600">مبالغ مشمول کمیسیون</strong> (هزینه‌های دارای فلگ «جزو کمیسیون») انجام می‌شود — سهم سازمان فقط برای کافی‌نت‌های زیرمجموعه محاسبه می‌شود و درآمد اپراتور از دل سهم کافی‌نت طبق مدل حقوق او پرداخت می‌شود. باقیماندهٔ سهم‌ها (اگر جمع &lt; ۱۰۰٪) نزد <strong class="text-stone-700">پلتفرم</strong> می‌ماند.</p>
        </div>
    </div>
</section>

{{-- قاعدهٔ سراسری --}}
<section class="card ui-lift animate-fade-up delay-1 overflow-hidden mt-5">
    <div class="adm-card-head">
        <h2 class="no-card-title text-sm font-extrabold text-stone-700">قاعدهٔ سراسری</h2>
        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">همیشه فعال</span>
    </div>

    <form id="global-form" class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4" novalidate>
        {{-- پلتفرم --}}
        <div class="rounded-2xl border border-stone-100 bg-gradient-to-b from-amber-50/60 to-white p-4 space-y-3">
            <div class="flex items-center gap-2">
                <span class="grid place-items-center size-8 rounded-xl bg-amber-100 text-amber-700">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/></svg>
                </span>
                <strong class="text-sm text-stone-800">سهم پلتفرم</strong>
            </div>
            <div class="flex gap-2">
                <select class="field !py-2.5 !text-xs" id="g-platform-type" aria-label="نوع سهم پلتفرم">
                    <option value="percent">درصدی</option>
                    <option value="fixed">مبلغ ثابت</option>
                </select>
                <input type="number" step="0.01" min="0" id="g-platform-value" class="field !py-2.5 !text-xs" placeholder="مثلاً 10" aria-label="مقدار سهم پلتفرم" required>
            </div>
        </div>

        {{-- سازمان --}}
        <div class="rounded-2xl border border-stone-100 bg-gradient-to-b from-teal-50/60 to-white p-4 space-y-3">
            <div class="flex items-center gap-2">
                <span class="grid place-items-center size-8 rounded-xl bg-teal-100 text-teal-700">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <strong class="text-sm text-stone-800">سهم سازمان</strong>
                <span class="text-[10px] text-stone-400">(اختیاری)</span>
            </div>
            <div class="flex gap-2">
                <select class="field !py-2.5 !text-xs" id="g-organization-type" aria-label="نوع سهم سازمان">
                    <option value="">بدون سهم</option>
                    <option value="percent">درصدی</option>
                    <option value="fixed">مبلغ ثابت</option>
                </select>
                <input type="number" step="0.01" min="0" id="g-organization-value" class="field !py-2.5 !text-xs" placeholder="مثلاً 5" aria-label="مقدار سهم سازمان">
            </div>
        </div>

        {{-- کافی‌نت --}}
        <div class="rounded-2xl border border-stone-100 bg-gradient-to-b from-orange-50/60 to-white p-4 space-y-3">
            <div class="flex items-center gap-2">
                <span class="grid place-items-center size-8 rounded-xl bg-orange-100 text-orange-700">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M5 7V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2"/></svg>
                </span>
                <strong class="text-sm text-stone-800">سهم کافی‌نت</strong>
                <span class="text-[10px] text-stone-400">(اپراتور از این سهم)</span>
            </div>
            <div class="flex gap-2">
                <select class="field !py-2.5 !text-xs" id="g-coffeenet-type" aria-label="نوع سهم کافی‌نت">
                    <option value="percent">درصدی</option>
                    <option value="fixed">مبلغ ثابت</option>
                </select>
                <input type="number" step="0.01" min="0" id="g-coffeenet-value" class="field !py-2.5 !text-xs" placeholder="مثلاً 80" aria-label="مقدار سهم کافی‌نت" required>
            </div>
        </div>

        {{-- جمع زنده + ذخیره --}}
        <div class="md:col-span-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-stone-50 border border-stone-100 px-4 py-3">
            <div class="flex items-center gap-2 text-xs text-stone-500">
                <span>جمع سهم‌های درصدی:</span>
                <strong id="g-sum" class="tabular-nums text-stone-700">—</strong>
                <span id="g-sum-note" class="text-[11px]"></span>
            </div>
            <button type="submit" id="g-save" class="btn-primary btn-shine !py-2.5 !px-6 !text-xs">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-7H7v7"/><path d="M7 3v5h8"/></svg>
                ذخیرهٔ قاعدهٔ سراسری
            </button>
        </div>
    </form>
</section>

{{-- قواعد اختصاصی خدمات --}}
<section class="card ui-lift animate-fade-up delay-2 overflow-hidden mt-5">
    <div class="adm-card-head">
        <div class="flex items-center gap-3">
            <h2 class="no-card-title text-sm font-extrabold text-stone-700">قواعد اختصاصی خدمات</h2>
            <span class="badge bg-stone-100 text-stone-500 border border-stone-200" id="rules-count">{{ fa_number($serviceRulesCount) }}</span>
        </div>
        <button type="button" id="btn-new-rule" class="btn-primary btn-shine !py-2.5 !px-5 !text-xs">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            قاعدهٔ جدید
        </button>
    </div>

    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>خدمت</th>
                    <th>پلتفرم</th>
                    <th>سازمان</th>
                    <th>کافی‌نت</th>
                    <th>وضعیت</th>
                    <th class="text-center">عملیات</th>
                </tr>
            </thead>
            <tbody id="rows">
                <tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
            </tbody>
        </table>
    </div>
</section>

{{-- مودال قاعدهٔ اختصاصی --}}
<div id="rule-modal" class="ui-modal-backdrop hidden">
    <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>
    <form id="rule-form" class="ui-modal adm-modal-text-start" data-tone="amber" role="dialog" aria-modal="true" aria-labelledby="rule-title" novalidate>
        <input type="hidden" id="r-id" value="">
        <div class="adm-modal-head">
            <h3 class="text-sm font-extrabold text-stone-800" id="rule-title">قاعدهٔ کمیسیون اختصاصی</h3>
            <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            <div>
                <label class="lbl" for="r-service">خدمت</label>
                <select id="r-service" class="field" required>
                    <option value="">انتخاب خدمت…</option>
                    @foreach ($services as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="lbl !text-[11px]" for="r-platform-type">پلتفرم *</label>
                    <select id="r-platform-type" class="field !py-2.5 !text-xs">
                        <option value="percent">درصدی</option>
                        <option value="fixed">ثابت (تومان)</option>
                    </select>
                </div>
                <div>
                    <label class="lbl !text-[11px]" for="r-organization-type">سازمان</label>
                    <select id="r-organization-type" class="field !py-2.5 !text-xs">
                        <option value="">—</option>
                        <option value="percent">درصدی</option>
                        <option value="fixed">ثابت (تومان)</option>
                    </select>
                </div>
                <div>
                    <label class="lbl !text-[11px]" for="r-coffeenet-type">کافی‌نت *</label>
                    <select id="r-coffeenet-type" class="field !py-2.5 !text-xs">
                        <option value="percent">درصدی</option>
                        <option value="fixed">ثابت (تومان)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="lbl !text-[11px]" for="r-platform-value">مقدار پلتفرم *</label>
                    <input type="number" step="0.01" min="0" id="r-platform-value" class="field !py-2.5 !text-xs" placeholder="10">
                </div>
                <div>
                    <label class="lbl !text-[11px]" for="r-organization-value">مقدار سازمان</label>
                    <input type="number" step="0.01" min="0" id="r-organization-value" class="field !py-2.5 !text-xs" placeholder="5">
                </div>
                <div>
                    <label class="lbl !text-[11px]" for="r-coffeenet-value">مقدار کافی‌نت *</label>
                    <input type="number" step="0.01" min="0" id="r-coffeenet-value" class="field !py-2.5 !text-xs" placeholder="80">
                </div>
            </div>

            <p class="text-[11px] text-stone-400 leading-5 rounded-xl bg-stone-50 border border-stone-100 px-3 py-2">
                سهم سازمان فقط برای کافی‌نت‌های زیرمجموعهٔ سازمان اعمال می‌شود؛ درآمد اپراتور از دل سهم کافی‌نت و طبق مدل حقوق او پرداخت می‌شود.
            </p>
        </div>

        <div class="adm-modal-foot">
            <div class="adm-modal-actions-equal">
                <button type="submit" id="r-save" class="btn-primary btn-shine !py-2.5">ذخیرهٔ قاعده</button>
                <button type="button" class="btn-ghost !py-2.5" data-close-modal>انصراف</button>
            </div>
        </div>
    </form>
</div>

{{-- داده‌های سرور برای اسکریپت صفحه --}}
<div id="page-data" hidden data-payload="{{ json_encode([
    'global' => $global ? [
        'platform_type' => $global->platform_type,
        'platform_value' => (float) $global->platform_value,
        'organization_type' => $global->organization_type,
        'organization_value' => (float) ($global->organization_value ?? 0),
        'coffeenet_type' => $global->coffeenet_type,
        'coffeenet_value' => (float) $global->coffeenet_value,
    ] : null,
]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/commissions/index.js') }}"></script>
@endpush
