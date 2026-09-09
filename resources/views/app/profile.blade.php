@extends('app.layout')

@section('title', 'پروفایل')
@section('active-nav', 'profile')

@section('content')
<div class="section fade-up">
    <h2>پروفایل من</h2>
</div>

<div class="card fade-up d1">
    <div class="row" style="margin-bottom:16px">
        <span class="avatar-btn" style="width:56px;height:56px;font-size:18px;border-radius:18px" id="profileAvatar">؟</span>
        <div class="grow">
            <strong id="profileName" style="font-size:15px">—</strong>
            <div class="text-faint tiny num" id="profileMobile">—</div>
            <div class="text-faint tiny" id="profileSince">—</div>
        </div>
    </div>

    <form id="profileForm" novalidate>
        <div class="form-group">
            <label class="label" for="pName">نام <span class="req">*</span></label>
            <input class="field" id="pName" type="text" autocomplete="given-name" maxlength="60">
            <p class="field-error" id="pNameError"></p>
        </div>

        <div class="form-group">
            <label class="label" for="pFamily">نام‌خانوادگی <span class="req">*</span></label>
            <input class="field" id="pFamily" type="text" autocomplete="family-name" maxlength="60">
            <p class="field-error" id="pFamilyError"></p>
        </div>

        <div class="form-group">
            <span class="label">جنسیت <span class="req">*</span></span>
            <div class="check-grid" id="genderGroup">
                <label class="check-row">
                    <input type="radio" name="gender" value="male">
                    مرد
                </label>
                <label class="check-row">
                    <input type="radio" name="gender" value="female">
                    زن
                </label>
            </div>
            <p class="field-error" id="genderError"></p>
        </div>

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

        <div class="form-group">
            <label class="label" for="pBirthdate">تاریخ تولد (شمسی) <span class="req">*</span></label>
            <input class="field num" id="pBirthdate" type="text" inputmode="numeric" placeholder="۱۳۷۰/۰۵/۱۲"
                   dir="ltr" style="text-align:center"
                   data-jdp data-jdp-min-years-ago="100" data-jdp-max-years-ago="10" title="برای انتخاب تاریخ کلیک کنید">
            <p class="help-text">نمونه: ۱۳۷۰/۰۵/۱۲ — با کلیک، تقویم شمسی باز می‌شود</p>
            <p class="field-error" id="pBirthdateError"></p>
        </div>

        <button class="btn btn-primary btn-block btn-lg" id="saveProfileBtn" type="submit">
            ذخیره پروفایل
        </button>
    </form>
</div>

<button class="btn btn-danger btn-block mt-2 fade-up d2" id="logoutBtn" type="button">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
    خروج از حساب
</button>
@endsection

@push('page')
    <script src="{{ asset('front/assets/js/pages/profile.js') }}" defer></script>
@endpush
