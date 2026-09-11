@extends('app.layout')

@section('title', 'ثبت سفارش')

@section('content')
{{-- قهرمان خدمت --}}
<div class="svc-hero fade-up" id="svcHero">
    <div class="svc-icon-lg" id="svcIcon">📄</div>
    <h1 id="svcName">در حال دریافت…</h1>
    <div class="badges" id="svcBadges"></div>
    <p class="desc" id="svcDesc"></p>
</div>

{{-- اطلاعات قیمت --}}
<div class="card fade-up d1" id="costCard">
    <h2 class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
        جزئیات هزینه
        <span class="hint" id="svcTime"></span>
    </h2>

    <div class="price-rows" id="costRows">
        <div class="price-row"><span class="pr-title">…</span><span class="pr-amount"></span></div>
    </div>
</div>

{{-- فرم داینامیک --}}
<div class="card fade-up d2" id="formCard">
    <h2 class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>
        فرم سفارش
        <span class="hint">اطلاعات دقیق = انجام سریع‌تر</span>
    </h2>

    <form id="orderForm" novalidate>
        <div id="dynamicFields" aria-live="polite">
            <div class="skeleton" style="height:56px"></div>
            <div class="skeleton" style="height:56px"></div>
        </div>

        {{-- مدارک اضافی (اختیاری) — فاز ۱۲: آپلودر زیبا --}}
        <div class="form-group mt-3 hidden" id="extraDocsGroup">
            <label class="label" for="extraDocs">مدارک تکمیلی (اختیاری)</label>
            <div class="fup" id="fupExtra">
                <input type="file" id="extraDocs" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" hidden>
                <button type="button" class="fup-zone" id="fupExtraZone" aria-describedby="fupExtraHint">
                    <span class="fz-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 8 5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg>
                    </span>
                    <span class="fz-txt">
                        <strong>انتخاب فایل یا رها کردن در اینجا</strong>
                        <small id="fupExtraHint">PDF، تصویر یا Word — هر فایل حداکثر ۵ مگابایت</small>
                    </span>
                </button>
                <div class="fup-list" id="fupExtraList" aria-live="polite"></div>
            </div>
            <p class="field-error" id="filesError"></p>
        </div>

        <div class="price-row total mt-3" id="totalRow">
            <span class="pr-title">هزینهٔ درخواست</span>
            <span class="pr-amount" id="totalAmount">—</span>
        </div>

        <button class="btn btn-primary btn-block btn-lg mt-2" id="submitOrderBtn" type="submit" disabled>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 14l2 2 4-4"/></svg>
            ثبت درخواست
        </button>

        <p class="help-text text-center mt-2">
            پس از ثبت، درخواست شما برای اپراتورها ارسال می‌شود؛
            بعد از اتصال اپراتور، پرداخت را انجام می‌دهید.
        </p>
    </form>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/uploader.css') }}?v=13">
@endpush

@push('page')
    <script src="{{ asset('front/assets/js/pages/service.js') }}?v=15" defer></script>
@endpush
