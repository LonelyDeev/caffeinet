@extends('app.layout')

@section('title', 'پروفایل')
@section('active-nav', 'profile')

@section('content')
{{-- ============ هرو پروفایل ============ --}}
<section class="pf-hero fade-up" aria-label="خلاصهٔ حساب">
    <div class="pf-hero-cover" aria-hidden="true">
        <span class="pf-hero-glow"></span>
    </div>

    <div class="pf-hero-body">
        <div class="pf-avatar" id="profileAvatar" aria-hidden="true">؟</div>

        <div class="pf-id">
            <strong id="profileName">—</strong>
            <div class="pf-mobile num" id="profileMobile">—</div>
        </div>

        <div class="pf-hero-badges">
            <span class="pf-badge pf-badge--ok hidden" id="profileCompleteBadge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                پروفایل تکمیل
            </span>
            <span class="pf-badge" id="profileSince">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <span id="profileSinceText">—</span>
            </span>
        </div>
    </div>

    {{-- کیف پول --}}
    <a class="pf-wallet" href="{{ route('app.wallet') }}" title="مشاهدهٔ کیف پول">
        <span class="pf-wallet-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
        </span>
        <span class="pf-wallet-txt">
            <small>موجودی کیف پول</small>
            <strong class="num" id="profileBalance">—</strong>
        </span>
        <svg class="pf-wallet-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
    </a>
</section>

{{-- ============ آمار سفارش‌ها ============ --}}
<section class="pf-stats fade-up d1" aria-label="آمار سفارش‌ها">
    <a class="pf-stat" href="{{ route('app.orders') }}">
        <strong class="num" id="statTotal">۰</strong>
        <span>کل سفارش‌ها</span>
    </a>
    <a class="pf-stat" href="{{ route('app.orders') }}">
        <strong class="num pf-stat--active" id="statActive">۰</strong>
        <span>در جریان</span>
    </a>
    <a class="pf-stat" href="{{ route('app.orders') }}">
        <strong class="num pf-stat--done" id="statCompleted">۰</strong>
        <span>تکمیل‌شده</span>
    </a>
    <a class="pf-stat" href="{{ route('app.orders') }}">
        <strong class="num pf-stat--cancel" id="statCancelled">۰</strong>
        <span>لغوشده</span>
    </a>
</section>

{{-- ============ فرم ویرایش ============ --}}
<section class="card pf-form-card fade-up d2">
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
            ذخیره پروفایل
        </button>
    </form>
</section>

{{-- ============ دسترسی سریع ============ --}}
<section class="pf-links fade-up d3" aria-label="دسترسی سریع">
    <a href="{{ route('app.orders') }}">
        <span class="pf-link-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>
        </span>
        <b>سفارش‌های من</b>
        <svg class="pf-link-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
    </a>
    <a href="{{ route('app.support') }}">
        <span class="pf-link-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Z"/><path d="M18 11h3v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2Z"/><path d="M21 11a9 9 0 0 0-18 0"/></svg>
        </span>
        <b>پشتیبانی و تیکت‌ها</b>
        <svg class="pf-link-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
    </a>
</section>

{{-- ============ خروج ============ --}}
<button class="btn btn-danger btn-block mt-2 fade-up d3" id="logoutBtn" type="button">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
    خروج از حساب
</button>
@endsection

@push('page')
    <script src="{{ asset('front/assets/js/pages/profile.js') }}?v=2" defer></script>
@endpush
