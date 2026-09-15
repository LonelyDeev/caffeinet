{{-- v39/v40 — کارت‌های بانکی (UI مشترک سه پنل: اپراتور / مدیر کافی‌نت / مدیر سازمان)
     $baseUrl: پیشوند endpointهای CRUD (مثلاً /operator/bank-cards)
     $cards:   آرایهٔ اولیهٔ کارت‌ها (JSON در data-attribute — CSP-safe)
     $finnotechVerify: آیا استعلام مالکیت کارت (فینوتک) فعال است؟ (v40) --}}
@php
    $cardsJson = json_encode($cards ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    $finnotechVerify = $finnotechVerify ?? app(\App\Services\Finnotech\FinnotechService::class)->cardVerificationOn();
@endphp

<div class="space-y-4" id="bcRoot" data-base="{{ $baseUrl }}" data-cards="{{ $cardsJson }}" data-finnotech="{{ $finnotechVerify ? '1' : '0' }}">

    {{-- هدر + دکمهٔ افزودن --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-sm font-extrabold text-stone-700">کارت‌های بانکی من</h2>
            <p class="text-[11px] text-stone-400 mt-1 leading-5">
                شماره کارت، شبا یا حساب خود را برای تسویه‌ها ثبت کنید؛ کارتِ «پیش‌فرض» مبنای واریز است.
                @if($finnotechVerify)
                    <span class="text-teal-600 font-bold">مالکیت کارت‌ها از طریق فینوتک بررسی می‌شود ✓</span>
                @endif
            </p>
        </div>
        <button type="button" id="bcAddBtn" class="btn-primary btn-shine ui-press !py-2.5 !px-5 !text-xs whitespace-nowrap">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            افزودن کارت
        </button>
    </div>

    {{-- لیست کارت‌ها --}}
    <div id="bcList" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>

    {{-- حالت خالی --}}
    <div id="bcEmpty" class="hidden card p-8 text-center">
        <span class="grid place-items-center size-14 mx-auto rounded-2xl bg-stone-100 text-stone-400">
            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
        </span>
        <p class="text-sm font-bold text-stone-500 mt-3">هنوز کارتی ثبت نکرده‌اید</p>
        <p class="text-xs text-stone-400 mt-1 leading-6">برای دریافت تسویه‌ها، شماره کارت/شبا/حساب خود را با دکمهٔ «افزودن کارت» ثبت کنید.</p>
    </div>

    {{-- مودال افزودن/ویرایش --}}
    <div id="bcModal" class="hidden fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="bcModalTitle">
        <div class="absolute inset-0 bg-black/45" data-bc-close></div>
        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[min(94vw,460px)] card p-5 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-extrabold text-stone-700" id="bcModalTitle">افزودن کارت بانکی</h3>
                <button type="button" class="grid place-items-center size-8 rounded-xl text-stone-400 hover:bg-stone-100" data-bc-close aria-label="بستن">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <form id="bcForm" novalidate>
                <div class="space-y-3">
                    <div>
                        <label class="lbl" for="bcCard">شماره کارت (۱۶ رقم)</label>
                        <input id="bcCard" name="card_number" class="field num" dir="ltr" style="text-align:left" inputmode="numeric"
                               placeholder="6219-8610-1234-5678" maxlength="23">
                        <p class="field-error" id="bcCardError"></p>
                    </div>

                    <div>
                        <label class="lbl" for="bcSheba">شماره شبا</label>
                        <input id="bcSheba" name="sheba_number" class="field num" dir="ltr" style="text-align:left"
                               placeholder="IR + ۲۴ رقم" maxlength="27">
                        <p class="field-error" id="bcShebaError"></p>
                    </div>

                    <div>
                        <label class="lbl" for="bcAccount">شماره حساب</label>
                        <input id="bcAccount" name="account_number" class="field num" dir="ltr" style="text-align:left"
                               placeholder="شماره حساب (اختیاری)" maxlength="30">
                        <p class="field-error" id="bcAccountError"></p>
                    </div>

                    <div>
                        <label class="lbl" for="bcHolder">نام صاحب حساب (اختیاری)</label>
                        <input id="bcHolder" name="holder_name" class="field" placeholder="مثلاً علی رضایی" maxlength="120">
                    </div>

                    {{-- v40 — کد ملی صاحب کارت (استعلام مالکیت فینوتک) --}}
                    <div id="bcNidWrap" class="{{ $finnotechVerify ? '' : 'hidden' }}">
                        <label class="lbl" for="bcOwnerNid">کد ملی صاحب کارت <span class="text-red-500">*</span></label>
                        <input id="bcOwnerNid" name="owner_nid" class="field num" dir="ltr" style="text-align:left" inputmode="numeric"
                               placeholder="کد ملی ۱۰ رقمی صاحب کارت" maxlength="10">
                        <p class="st-hint">برای احراز مالکیت، شماره کارت با کد ملی صاحبش از طریق فینوتک تطبیق داده می‌شود.</p>
                        <p class="field-error" id="bcOwnerNidError"></p>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="bcDefault" name="is_default" class="size-4 accent-amber-600">
                        <span class="text-xs font-bold text-stone-600">کارت پیش‌فرض تسویه باشد</span>
                    </label>
                </div>

                <div class="flex gap-2 mt-5">
                    <button type="submit" id="bcSaveBtn" class="btn-primary btn-shine ui-press flex-1 !py-2.5 !text-xs">ذخیره کارت</button>
                    <button type="button" class="btn-ghost ui-press !py-2.5 !px-5 !text-xs" data-bc-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>
</div>
