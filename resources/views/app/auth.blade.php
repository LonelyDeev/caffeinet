@extends('app.layout')

@section('title', 'ورود / ثبت‌نام')
@section('no-chrome', '1')
@section('shell-class', 'no-nav')

@section('content')
<div class="auth-wrap">
    <div class="auth-brand fade-up">
        <div class="mark">
            <div class="steam" aria-hidden="true"><span></span><span></span><span></span></div>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>
                <path d="M6 2v2"/><path d="M10 2v2"/><path d="M14 2v2"/>
            </svg>
        </div>
        <h1>کافی‌نت آنلاین</h1>
        <p>خدمات کافی‌نت، آنلاین و در جیب شما</p>
    </div>

    {{-- گام ۱: شماره موبایل --}}
    <div class="card auth-card fade-up d1" id="stepMobile">
        <h2 class="card-title">
            ورود یا ثبت‌نام
            <span class="hint">با کد یک‌بارمصرف</span>
        </h2>

        <div class="form-group">
            <label class="label" for="mobileInput">شماره موبایل <span class="req">*</span></label>
            <input class="field num" id="mobileInput" type="tel" inputmode="numeric" autocomplete="tel"
                   placeholder="۰۹۱۲۳۴۵۶۷۸۹" maxlength="14" dir="ltr" style="text-align:center; font-weight:700; letter-spacing:.08em;">
            <p class="field-error" id="mobileError"></p>
        </div>

        <button class="btn btn-primary btn-block btn-lg" id="sendOtpBtn" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4Z"/></svg>
            دریافت کد تأیید
        </button>

        <p class="text-faint tiny text-center mt-2">
            با ورود، <a href="#">قوانین</a> و <a href="#">حریم خصوصی</a> را می‌پذیرم.
        </p>
    </div>

    {{-- گام ۲: کد تأیید --}}
    <div class="card auth-card hidden" id="stepCode">
        <h2 class="card-title">
            کد تأیید را وارد کنید
            <span class="hint" id="codeTarget"></span>
        </h2>

        <div class="form-group">
            <label class="label" for="codeInput">کد ۵ رقمی پیامک‌شده <span class="req">*</span></label>
            <input class="field otp-input" id="codeInput" type="tel" inputmode="numeric" maxlength="5" autocomplete="one-time-code" placeholder="●●●●●">
            <p class="field-error" id="codeError"></p>
        </div>

        <div class="dev-code-note hidden" id="devCodeNote">
            محیط توسعه (پیامک لاگی): کد شما
            <strong id="devCodeValue">—</strong>
            است
        </div>

        <div class="resend-bar">
            <span id="resendTimer"></span>
            <button class="resend-btn" id="resendBtn" type="button" disabled>ارسال مجدد کد</button>
        </div>

        <button class="btn btn-primary btn-block btn-lg mt-2" id="verifyBtn" type="button">
            تأیید و ورود
        </button>

        <button class="btn btn-ghost btn-block btn-sm mt-1" id="backBtn" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
            اصلاح شماره موبایل
        </button>
    </div>
</div>
@endsection

@push('page')
    <script src="{{ asset('front/assets/js/pages/auth.js') }}" defer></script>
@endpush
