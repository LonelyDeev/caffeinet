@extends('app.layout')

@section('title', 'ویرایش اطلاعات')
@section('active-nav', 'profile')

@section('content')
{{-- بازگشت به پروفایل --}}
<a href="{{ route('app.profile') }}" class="back-link fade-up" id="backLink">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
    پروفایل من
</a>

{{-- ============ بنر خوش‌آمد (اولین ورود — ?new=1) ============ --}}
<section class="pf-alert pf-alert--welcome hidden fade-up" id="pfWelcomeBanner" role="status">
    <span class="pf-alert-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12c2-2.76 0-7-1-8 .5 2.5-.5 5-1 6-1.5 2.5-3 3.5-3 6a5 5 0 0 0 10 0c0-1.5-.5-3-2-4-.5 1.5-1 2-2 2 0-1 0-2-1-2Z"/></svg>
    </span>
    <div class="pf-alert-txt">
        <b>به کافی‌نت آنلاین خوش آمدید 🎉</b>
        <span>برای شروع، اطلاعات پایهٔ خود را کامل کنید؛ فقط چند ثانیه طول می‌کشد.</span>
    </div>
</section>

{{-- ============ فرم ویرایش (v24 — از پروفایل جدا شد) ============ --}}
<section class="card pf-form-card fade-up d1">
    <h2 class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
        ویرایش اطلاعات
    </h2>

    <form id="profileForm" novalidate>
        <div class="pf-sec">
            <div class="pf-sec-head">
                <span class="pf-sec-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <b>اطلاعات شخصی</b>
            </div>

            <div class="pf-grid-2">
                <div class="form-group">
                    <label class="label" for="pName">نام <span class="req">*</span></label>
                    <input class="field" id="pName" type="text" autocomplete="given-name" maxlength="60" placeholder="مثلاً علی">
                    <p class="field-error" id="pNameError"></p>
                </div>

                <div class="form-group">
                    <label class="label" for="pFamily">نام‌خانوادگی <span class="req">*</span></label>
                    <input class="field" id="pFamily" type="text" autocomplete="family-name" maxlength="60" placeholder="مثلاً رضایی">
                    <p class="field-error" id="pFamilyError"></p>
                </div>
            </div>

            <div class="form-group">
                <span class="label">جنسیت <span class="req">*</span></span>
                <div class="pf-segment" id="genderGroup" role="radiogroup" aria-label="جنسیت">
                    <input type="radio" name="gender" value="male" id="pfGenderMale" class="sr-only">
                    <label for="pfGenderMale">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="14" r="5.5"/><path d="m15 9 6-6"/><path d="m17.5 2.5 4 4"/></svg>
                        مرد
                    </label>
                    <input type="radio" name="gender" value="female" id="pfGenderFemale" class="sr-only">
                    <label for="pfGenderFemale">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="9" r="5.5"/><path d="M12 14.5V22"/><path d="M9 19h6"/></svg>
                        زن
                    </label>
                </div>
                <p class="field-error" id="genderError"></p>
            </div>
        </div>

        <div class="pf-sec">
            <div class="pf-sec-head">
                <span class="pf-sec-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.99-5.54 10.19-7.4 11.79a1 1 0 0 1-1.2 0C9.54 20.19 4 14.99 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                </span>
                <b>محل سکونت</b>
            </div>

            <div class="pf-grid-2">
                <div class="form-group">
                    <label class="label" for="pProvince">استان <span class="req">*</span></label>
                    <select class="field" id="pProvince">
                        <option value="">انتخاب استان…</option>
                    </select>
                    <p class="field-error" id="pProvinceError"></p>
                </div>

                <div class="form-group">
                    <label class="label" for="pCity">شهر <span class="req">*</span></label>
                    <select class="field" id="pCity" disabled>
                        <option value="">ابتدا استان را انتخاب کنید</option>
                    </select>
                    <p class="field-error" id="pCityError"></p>
                </div>
            </div>
        </div>

        <div class="pf-sec">
            <div class="pf-sec-head">
                <span class="pf-sec-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 2v4"/><path d="M16 2v4"/><path d="M3 10h18"/></svg>
                </span>
                <b>تاریخ تولد</b>
            </div>

            <div class="form-group">
                <label class="label" for="pBirthdate">تاریخ تولد (شمسی) <span class="req">*</span></label>
                <input class="field num" id="pBirthdate" type="text" inputmode="numeric" placeholder="۱۳۷۰/۰۵/۱۲"
                       dir="ltr" style="text-align:center"
                       data-jdp data-jdp-min-years-ago="100" data-jdp-max-years-ago="10" title="برای انتخاب تاریخ کلیک کنید">
                <p class="help-text">نمونه: ۱۳۷۰/۰۵/۱۲ — با کلیک، تقویم شمسی باز می‌شود</p>
                <p class="field-error" id="pBirthdateError"></p>
            </div>
        </div>

        <button class="btn btn-primary btn-block btn-lg" id="saveProfileBtn" type="submit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.2 3a2 2 0 0 1 2.8 2.8L7.5 14.3 3 15.5l1.2-4.5Z"/><path d="M17 21H7a2 2 0 0 1-2-2v-7"/></svg>
            ذخیره اطلاعات
        </button>
    </form>
</section>
@endsection

@push('page')
    <script src="{{ asset('front/assets/js/pages/profile-edit.js') }}?v=1" defer></script>
@endpush
