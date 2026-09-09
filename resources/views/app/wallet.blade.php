@extends('app.layout')

@section('title', 'کیف پول')
@section('active-nav', 'wallet')

@section('content')
<div class="wallet-hero fade-up">
    <div class="w-label">موجودی کیف پول شما</div>
    <div class="w-balance"><span id="walletBalance">—</span> <small>تومان</small></div>
    <div class="w-num" id="walletMobile"></div>
    <button class="btn btn-success btn-block mt-2" id="chargeBtn" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="m5 12 7-7 7 7"/></svg>
        افزایش اعتبار کیف پول
    </button>
</div>

<div class="section fade-up d1">
    <h2>گردش حساب</h2>
    <span class="more" id="txCount"></span>
</div>

<div class="card fade-up d2">
    <div id="txList" aria-live="polite">
        <div class="skeleton" style="height:60px"></div>
        <div class="skeleton" style="height:60px"></div>
    </div>

    <div class="empty-state hidden" id="txEmpty">
        <div class="e-icon">💳</div>
        <div class="e-title">تراکنشی ثبت نشده</div>
        <div class="e-desc">پس از اولین پرداخت یا واریز، گردش اینجا نمایش داده می‌شود.</div>
    </div>
</div>

<div class="center-loader hidden" id="txMoreLoader"><span class="spinner"></span></div>

<button class="btn btn-outline btn-block mt-2 hidden" id="txLoadMore" type="button">
    تراکنش‌های بیشتر
</button>

{{-- شیت شارژ کیف پول --}}
<div class="charge-overlay" id="chargeOverlay" aria-hidden="true"></div>
<div class="charge-sheet" id="chargeSheet" role="dialog" aria-modal="true" aria-labelledby="chargeTitle">
    <div class="sheet-grip" aria-hidden="true"></div>
    <div class="cs-head">
        <h2 id="chargeTitle">افزایش اعتبار کیف پول</h2>
        <button type="button" class="cs-close" id="chargeClose" aria-label="بستن">✕</button>
    </div>

    <div class="charge-amounts" id="quickAmounts">
        <button type="button" class="charge-amt" data-amount="50000">۵۰ هزار <span class="unit">تومان</span></button>
        <button type="button" class="charge-amt" data-amount="100000">۱۰۰ هزار <span class="unit">تومان</span></button>
        <button type="button" class="charge-amt" data-amount="200000">۲۰۰ هزار <span class="unit">تومان</span></button>
        <button type="button" class="charge-amt" data-amount="500000">۵۰۰ هزار <span class="unit">تومان</span></button>
    </div>

    <div class="charge-custom-wrap">
        <input class="field num" id="chargeCustom" type="tel" inputmode="numeric" placeholder="مبلغ دلخواه (تومان)" style="text-align:center">
        <span class="suffix">تومان</span>
    </div>

    <button class="btn btn-primary btn-block btn-lg" id="chargeSubmit" type="button">
        پرداخت و افزایش اعتبار
    </button>

    <p class="cs-hint" id="chargeError" style="color:var(--err-600);font-weight:600;display:none"></p>
    <p class="cs-hint">
        مبلغ پس از پرداخت موفق در درگاه، بلافاصله به کیف پول شما اضافه می‌شود.<br>
        حداقل ۱۰,۰۰۰ و حداکثر ۵۰,۰۰۰,۰۰۰ تومان.
    </p>
</div>
@endsection

@push('page')
    <script src="{{ asset('front/assets/js/pages/wallet.js') }}" defer></script>
@endpush
