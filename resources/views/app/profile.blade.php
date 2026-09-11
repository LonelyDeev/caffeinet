@extends('app.layout')

@section('title', 'پروفایل')
@section('active-nav', 'profile')

@section('content')
{{-- ============ هشدار پروفایل ناقص (v24) ============ --}}
<section class="pf-alert hidden fade-up" id="pfIncompleteBanner" role="alert">
    <span class="pf-alert-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
    </span>
    <div class="pf-alert-txt">
        <b>اطلاعات شما کامل نیست</b>
        <span>برای ثبت سفارش، شارژ کیف پول و ارسال تیکت، ابتدا اطلاعات پایهٔ خود را تکمیل کنید.</span>
    </div>
    <a class="btn btn-primary btn-sm" href="{{ route('app.profile.edit') }}">تکمیل اطلاعات</a>
</section>

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

{{-- ============ منوی حساب (v24 — ویرایش اطلاعات جدا شد) ============ --}}
<section class="pf-links fade-up d2" aria-label="منوی حساب">
    <a href="{{ route('app.profile.edit') }}" id="pfEditLink">
        <span class="pf-link-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
        </span>
        <span class="pf-link-body">
            <b>ویرایش اطلاعات</b>
            <small id="pfEditHint">مشاهده و اصلاح اطلاعات شخصی</small>
        </span>
        <span class="pf-link-flag hidden" id="pfEditFlag">تکمیل نشده</span>
        <svg class="pf-link-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
    </a>
    <a href="{{ route('app.orders') }}">
        <span class="pf-link-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>
        </span>
        <span class="pf-link-body">
            <b>سفارش‌های من</b>
            <small>پیگیری وضعیت و تاریخچهٔ سفارش‌ها</small>
        </span>
        <svg class="pf-link-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
    </a>
    <a href="{{ route('app.support') }}">
        <span class="pf-link-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Z"/><path d="M18 11h3v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2Z"/><path d="M21 11a9 9 0 0 0-18 0"/></svg>
        </span>
        <span class="pf-link-body">
            <b>پشتیبانی و تیکت‌ها</b>
            <small>ثبت درخواست و پیگیری پاسخ‌ها</small>
        </span>
        <svg class="pf-link-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
    </a>
    <a href="{{ route('app.wallet') }}">
        <span class="pf-link-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
        </span>
        <span class="pf-link-body">
            <b>کیف پول</b>
            <small>موجودی، شارژ و گردش حساب</small>
        </span>
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
    <script src="{{ asset('front/assets/js/pages/profile.js') }}?v=3" defer></script>
@endpush
