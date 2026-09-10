@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'تنظیمات')
@section('page-title', 'تنظیمات سیستم')
@section('breadcrumb', 'پنل مدیریت کل ← تنظیمات')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/settings.css') }}?v=16">
@endpush

@section('content')

@php
    $provider = $settings->get('sms.provider', 'log');
    $kvnConfigured = (bool) $settings->get('sms.kavenegar.api_key');
    $fraaConfigured = (bool) $settings->get('sms.fraasms.api_key');
    $ippConfigured = trim((string) $settings->get('sms.ippanel.username')) !== '' && trim((string) $settings->get('sms.ippanel.password')) !== '';
    $melConfigured = trim((string) $settings->get('sms.melipayamak.username')) !== '' && trim((string) $settings->get('sms.melipayamak.password')) !== '';
    $idehConfigured = trim((string) $settings->get('sms.idehpardazan.api_key')) !== '' && trim((string) $settings->get('sms.idehpardazan.secret_key')) !== '';
    $providerLabel = match ($provider) {
        'kavenegar' => 'کاوه‌نگار',
        'fraasms' => 'فراز اس‌ام‌اس',
        'ippanel' => 'آی‌پی‌پنل',
        'melipayamak' => 'ملی‌پیامک',
        'idehpardazan' => 'ایده‌پردازان',
        default => 'لاگ (محیط توسعه)',
    };

    $payDriver = (string) $settings->get('payment.driver', 'local');
    $payDriverLabel = match ($payDriver) {
        'zarinpal' => 'زرین‌پال',
        'zibal' => 'زیبال',
        'behpardakht' => 'بانک ملت',
        'sep' => 'بانک ملی',
        'sepehr' => 'درگاه سپهر',
        default => 'درگاه تست (local)',
    };
    $zarinpalConfigured = (bool) $settings->get('payment.zarinpal.merchant_id');
    $zibalConfigured = (bool) $settings->get('payment.zibal.merchant_id');
    $zarinpalSandbox = (bool) $settings->get('payment.zarinpal.sandbox', true);
    $behpConfigured = trim((string) $settings->get('payment.behpardakht.terminal_id')) !== ''
        && trim((string) $settings->get('payment.behpardakht.username')) !== ''
        && trim((string) $settings->get('payment.behpardakht.password')) !== '';
    $sepConfigured = trim((string) $settings->get('payment.sep.terminal_id')) !== '';
    $sepehrConfigured = trim((string) $settings->get('payment.sepehr.terminal_id')) !== '';
@endphp

<div class="st-layout">

    {{-- ================== ناوبری سکشن‌ها ================== --}}
    <aside class="st-nav card ui-lift animate-fade-up" aria-label="ناوبری بخش‌های تنظیمات">
        <div class="st-nav-head">
            <span class="st-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
            <div class="min-w-0">
                <b class="block text-sm font-extrabold text-stone-800">تنظیمات سیستم</b>
                <span class="block text-[11px] text-stone-400">۷ بخش پیکربندی</span>
            </div>
        </div>

        <nav class="st-nav-list" role="tablist">
            <button type="button" role="tab" class="st-nav-item is-active" data-section="general">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                <span class="flex-1 text-start">عمومی</span>
                <span class="st-nav-hint">۱</span>
            </button>

            <button type="button" role="tab" class="st-nav-item" data-section="sms">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
                <span class="flex-1 text-start">پیامک و پرووایدر</span>
                <span class="st-nav-hint">{{ $providerLabel }}</span>
            </button>

            <button type="button" role="tab" class="st-nav-item" data-section="orders">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="flex-1 text-start">موتور سفارش‌ها</span>
                <span class="st-nav-hint">پخش</span>
            </button>

            <button type="button" role="tab" class="st-nav-item" data-section="workhours">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <span class="flex-1 text-start">ساعت کاری</span>
                <span class="st-nav-hint" data-wh-hint>{{ $settings->get('workhours.enabled') ? 'فعال' : 'خاموش' }}</span>
            </button>

            <button type="button" role="tab" class="st-nav-item" data-section="payment">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
                <span class="flex-1 text-start">درگاه پرداخت</span>
                <span class="st-nav-hint">{{ $payDriverLabel }}</span>
            </button>

            <button type="button" role="tab" class="st-nav-item" data-section="staff">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="flex-1 text-start">کارکنان کافی‌نت‌ها</span>
                <span class="st-nav-hint">{{ $settings->get('staff.hiring.mode', 'auto') === 'approval' ? 'تایید مدیر کل' : 'خودکار' }}</span>
            </button>

            <button type="button" role="tab" class="st-nav-item" data-section="realtime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                <span class="flex-1 text-start">Realtime (پوشر)</span>
                <span class="st-nav-hint">{{ $settings->get('realtime.pusher.enabled') ? 'فعال' : 'خاموش' }}</span>
            </button>

            <button type="button" role="tab" class="st-nav-item" data-section="referral">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="flex-1 text-start">پاداش معرفی</span>
                @if ($referral->is_active)
                    <span class="st-nav-dot st-nav-dot--on" title="فعال"></span>
                @else
                    <span class="st-nav-dot" title="غیرفعال"></span>
                @endif
            </button>

            <a href="{{ route('admin.sms-templates.index') }}" class="st-nav-item st-nav-item--link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v18l-6-3-6 3Z"/></svg>
                <span class="flex-1 text-start">مرکز پیامک (پترن‌ها)</span>
                <svg class="size-3.5 text-stone-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </nav>
    </aside>

    {{-- ================== سکشن‌ها ================== --}}
    <div class="st-sections">

        {{-- ---------- عمومی ---------- --}}
        <form data-group="general" class="st-section card ui-lift animate-fade-up" id="sec-general">
            <div class="st-section-head">
                <span class="st-section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                </span>
                <div class="flex-1">
                    <h2 class="st-section-title">تنظیمات عمومی</h2>
                    <p class="st-section-desc">نام نمایشی سیستم در پنل‌ها، اپ مشتری و پیامک‌ها استفاده می‌شود.</p>
                </div>
            </div>

            <div class="st-field-row">
                <label class="lbl" for="g-app-name">نام سیستم</label>
                <input id="g-app-name" data-key="general.app_name" class="field" value="{{ old('general.app_name', $settings->get('general.app_name', 'کافی‌نت آنلاین')) }}">
                <p class="st-hint">در متن پیامک‌ها با متغیر <span class="font-mono text-amber-600" dir="ltr">{app_name}</span> درج می‌شود</p>
            </div>

            <div class="st-section-foot">
                <button type="submit" class="btn-primary btn-shine ui-press !py-2.5 px-7">ذخیرهٔ تنظیمات عمومی</button>
            </div>
        </form>

        {{-- ---------- پیامک ---------- --}}
        <form data-group="sms" class="st-section card ui-lift animate-fade-up hidden" id="sec-sms">
            <div class="st-section-head">
                <span class="st-section-icon st-section-icon--sms" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
                </span>
                <div class="flex-1">
                    <h2 class="st-section-title">پیامک و پرووایدر</h2>
                    <p class="st-section-desc">پرووایدر ارسال را انتخاب کنید — تنظیمات همان پرووایدر بلافاصله زیر کارت‌ها باز می‌شود. کد پترن هر رویداد در «مرکز پیامک» ثبت می‌شود.</p>
                </div>
                <span class="badge {{ $provider === 'log' ? 'bg-stone-100 text-stone-500 border border-stone-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}" id="sms-provider-badge">{{ $providerLabel }}</span>
            </div>

            {{-- کارت‌های انتخاب پرووایدر — فعال: تنظیمات همان کارت زیرش باز می‌شود --}}
            <div class="st-pick-head">
                <b>پرووایدر فعال</b>
                <span>با انتخاب کارت، تنظیمات همان سرویس در ادامه نمایان می‌شود</span>
            </div>

            <div class="st-prov-grid" role="radiogroup" aria-label="انتخاب پرووایدر پیامک">
                <label class="st-prov-card" data-prov="kavenegar">
                    <input type="radio" name="sms-provider" value="kavenegar" class="sr-only" {{ $provider === 'kavenegar' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--kavenegar" aria-hidden="true">ک</span>
                    <b class="st-prov-name">کاوه‌نگار</b>
                    <span class="st-prov-sub">Verify Lookup · پترن + متن</span>
                </label>

                <label class="st-prov-card" data-prov="fraasms">
                    <input type="radio" name="sms-provider" value="fraasms" class="sr-only" {{ $provider === 'fraasms' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--fraasms" aria-hidden="true">ف</span>
                    <b class="st-prov-name">فراز اس‌ام‌اس</b>
                    <span class="st-prov-sub">ایران‌پیامک · پترن‌محور</span>
                </label>

                <label class="st-prov-card" data-prov="ippanel">
                    <input type="radio" name="sms-provider" value="ippanel" class="sr-only" {{ $provider === 'ippanel' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--ippanel" aria-hidden="true">آ</span>
                    <b class="st-prov-name">آی‌پی‌پنل</b>
                    <span class="st-prov-sub">ippanel · پترن‌محور</span>
                </label>

                <label class="st-prov-card" data-prov="melipayamak">
                    <input type="radio" name="sms-provider" value="melipayamak" class="sr-only" {{ $provider === 'melipayamak' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--melipayamak" aria-hidden="true">م</span>
                    <b class="st-prov-name">ملی‌پیامک</b>
                    <span class="st-prov-sub">متن ثابت (bodyId)</span>
                </label>

                <label class="st-prov-card" data-prov="idehpardazan">
                    <input type="radio" name="sms-provider" value="idehpardazan" class="sr-only" {{ $provider === 'idehpardazan' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--idehpardazan" aria-hidden="true">ا</span>
                    <b class="st-prov-name">ایده‌پردازان</b>
                    <span class="st-prov-sub">قالب سریع RestfulSms</span>
                </label>

                <label class="st-prov-card" data-prov="log">
                    <input type="radio" name="sms-provider" value="log" class="sr-only" {{ $provider === 'log' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--log" aria-hidden="true">ل</span>
                    <b class="st-prov-name">لاگ (توسعه)</b>
                    <span class="st-prov-sub">فقط ثبت در گزارش</span>
                </label>
            </div>

            <input type="hidden" id="s-provider" data-key="sms.provider" value="{{ $provider }}">

            {{-- ===== کارت تنظیمات کاوه‌نگار ===== --}}
            <div class="st-gw" data-gw="kavenegar">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--kavenegar" aria-hidden="true">ک</span>
                    <div class="st-gw-title flex-1">
                        <b>کاوه‌نگار <span class="font-normal text-stone-400" dir="ltr">(Kavenegar)</span></b>
                        <span>REST رسمی · ارسال پترنی Verify Lookup + متن آزاد (در خطای پترن، افت به متن)</span>
                    </div>
                    @if ($kvnConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">کلید ثبت‌شده</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-field-row">
                    <label class="lbl" for="s-kvn-key">کلید API</label>
                    <input id="s-kvn-key" data-key="sms.kavenegar.api_key" data-empty-skip dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                           placeholder="{{ $kvnConfigured ? '•••••••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'کلید API را از پنل کاوه‌نگار کپی کنید' }}">
                </div>

                <div class="st-grid-2">
                    <div class="st-field-row !mb-0">
                        <label class="lbl" for="s-kvn-sender">شماره خط فرستنده</label>
                        <input id="s-kvn-sender" data-key="sms.kavenegar.sender" dir="ltr" class="field" placeholder="10004345"
                               value="{{ $settings->get('sms.kavenegar.sender') }}">
                    </div>
                    <div class="st-field-row !mb-0">
                        <label class="lbl" for="s-kvn-endpoint">آدرس API</label>
                        <input id="s-kvn-endpoint" data-key="sms.kavenegar.endpoint" dir="ltr" class="field font-mono !text-xs"
                               value="{{ $settings->get('sms.kavenegar.endpoint', 'https://api.kavenegar.com') }}">
                    </div>
                </div>
                <p class="st-hint">
                    کد پترن قالب‌ها در «<a href="{{ route('admin.sms-templates.index') }}" class="text-amber-700 font-semibold hover:underline">مرکز پیامک</a>» = <b>نام قالب Verify Lookup</b> در پنل کاوه‌نگار؛ متغیرهای قالب به‌ترتیب روی token10/20/30 نگاشت می‌شوند.
                    مستندات: <a href="https://kavenegar.com/rest.html" target="_blank" rel="noopener" class="text-amber-700 font-semibold hover:underline" dir="ltr">kavenegar.com/rest</a>
                </p>
            </div>

            {{-- ===== کارت تنظیمات فراز اس‌ام‌اس ===== --}}
            <div class="st-gw" data-gw="fraasms">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--fraasms" aria-hidden="true">ف</span>
                    <div class="st-gw-title flex-1">
                        <b>فراز اس‌ام‌اس <span class="font-normal text-stone-400" dir="ltr">(ایران‌پیامک)</span></b>
                        <span>پترن‌محور خالص — متن آزاد پذیرفته نمی‌شود؛ قالب بدون پترن ارسال نمی‌شود</span>
                    </div>
                    @if ($fraaConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">کلید ثبت‌شده</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-field-row">
                    <label class="lbl" for="s-api-key">کلید API <span class="text-stone-400 text-[10px]" dir="ltr">(Api-Key)</span></label>
                    <input id="s-api-key" data-key="sms.fraasms.api_key" data-empty-skip dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                           placeholder="{{ $fraaConfigured ? '•••••••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'کلید API پنل فراز را وارد کنید' }}">
                    <p class="st-hint">از پنل فراز اس‌ام‌اس: بخش «API» → تولید کلید (به‌صورت هدر <span class="font-mono text-amber-600" dir="ltr">Api-Key</span> ارسال می‌شود)</p>
                </div>

                <div class="st-grid-2">
                    <div class="st-field-row !mb-0">
                        <label class="lbl" for="s-sender">شماره خط <span class="text-stone-400 text-[10px]" dir="ltr">(line_number)</span></label>
                        <input id="s-sender" data-key="sms.fraasms.sender" dir="ltr" class="field" placeholder="+983000505"
                               value="{{ $settings->get('sms.fraasms.sender') }}">
                    </div>
                    <div class="st-field-row !mb-0">
                        <label class="lbl" for="s-endpoint">آدرس API پترن</label>
                        <input id="s-endpoint" data-key="sms.fraasms.endpoint" dir="ltr" class="field font-mono !text-xs"
                               value="{{ $settings->get('sms.fraasms.endpoint', 'https://api.iranpayamak.com/ws/v1/sms/pattern') }}"
                               placeholder="https://api.iranpayamak.com/ws/v1/sms/pattern">
                    </div>
                </div>
                <p class="st-hint">
                    کد پترن هر رویداد را در «<a href="{{ route('admin.sms-templates.index') }}" class="text-amber-700 font-semibold hover:underline">مرکز پیامک</a>» ثبت کنید؛ نام متغیرها باید با متغیرهای پترن پنل فراز یکی باشد (مثل <span class="font-mono text-amber-600" dir="ltr">{code}</span>).
                </p>
            </div>

            {{-- ===== کارت تنظیمات آی‌پی‌پنل ===== --}}
            <div class="st-gw" data-gw="ippanel">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--ippanel" aria-hidden="true">آ</span>
                    <div class="st-gw-title flex-1">
                        <b>آی‌پی‌پنل <span class="font-normal text-stone-400" dir="ltr">(ippanel.com)</span></b>
                        <span>وب‌سرویس پترن پنل · ارسال پترن‌محور (متن آزاد قابل اتکا نیست)</span>
                    </div>
                    @if ($ippConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">پیکربندی کامل</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-grid-2">
                    <div class="st-field-row">
                        <label class="lbl" for="s-ipp-user">نام کاربری پنل</label>
                        <input id="s-ipp-user" data-key="sms.ippanel.username" dir="ltr" class="field" autocomplete="off"
                               placeholder="نام کاربری پنل آی‌پی‌پنل" value="{{ $settings->get('sms.ippanel.username') }}">
                    </div>
                    <div class="st-field-row">
                        <label class="lbl" for="s-ipp-pass">رمز عبور پنل</label>
                        <input id="s-ipp-pass" data-key="sms.ippanel.password" data-empty-skip dir="ltr" class="field" type="password" autocomplete="off"
                               placeholder="{{ $ippConfigured ? '•••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'رمز عبور پنل آی‌پی‌پنل' }}">
                    </div>
                </div>

                <div class="st-grid-2">
                    <div class="st-field-row !mb-0">
                        <label class="lbl" for="s-ipp-from">شماره خط فرستنده</label>
                        <input id="s-ipp-from" data-key="sms.ippanel.from" dir="ltr" class="field" placeholder="+983000505"
                               value="{{ $settings->get('sms.ippanel.from') }}">
                    </div>
                    <div class="st-field-row !mb-0">
                        <label class="lbl" for="s-ipp-endpoint">آدرس ارسال پترن</label>
                        <input id="s-ipp-endpoint" data-key="sms.ippanel.endpoint" dir="ltr" class="field font-mono !text-xs"
                               value="{{ $settings->get('sms.ippanel.endpoint', 'https://ippanel.com/patterns/pattern') }}"
                               placeholder="https://ippanel.com/patterns/pattern">
                    </div>
                </div>
                <p class="st-hint">
                    کد پترن قالب‌ها در «مرکز پیامک» = <b>pattern_code</b> پنل آی‌پی‌پنل؛ نام متغیرهای نام‌دار باید با متغیرهای تعریف‌شدهٔ پترن یکی باشد.
                </p>
            </div>

            {{-- ===== کارت تنظیمات ملی‌پیامک ===== --}}
            <div class="st-gw" data-gw="melipayamak">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--melipayamak" aria-hidden="true">م</span>
                    <div class="st-gw-title flex-1">
                        <b>ملی‌پیامک <span class="font-normal text-stone-400" dir="ltr">(Melipayamak)</span></b>
                        <span>سرویس پایه مشترک — ارسال با «شناسه متن ثابت» (bodyId)</span>
                    </div>
                    @if ($melConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">پیکربندی کامل</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-grid-2">
                    <div class="st-field-row">
                        <label class="lbl" for="s-mp-user">نام کاربری پنل</label>
                        <input id="s-mp-user" data-key="sms.melipayamak.username" dir="ltr" class="field" autocomplete="off"
                               placeholder="نام کاربری پنل ملی‌پیامک" value="{{ $settings->get('sms.melipayamak.username') }}">
                    </div>
                    <div class="st-field-row">
                        <label class="lbl" for="s-mp-pass">رمز عبور پنل</label>
                        <input id="s-mp-pass" data-key="sms.melipayamak.password" data-empty-skip dir="ltr" class="field" type="password" autocomplete="off"
                               placeholder="{{ $melConfigured ? '•••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'رمز عبور پنل ملی‌پیامک' }}">
                    </div>
                </div>

                <div class="st-grid-2">
                    <div class="st-field-row !mb-0">
                        <label class="lbl" for="s-mp-from">شماره خط فرستنده</label>
                        <input id="s-mp-from" data-key="sms.melipayamak.from" dir="ltr" class="field" placeholder="5000..."
                               value="{{ $settings->get('sms.melipayamak.from') }}">
                    </div>
                    <div class="st-field-row !mb-0">
                        <label class="lbl" for="s-mp-endpoint">آدرس REST</label>
                        <input id="s-mp-endpoint" data-key="sms.melipayamak.endpoint" dir="ltr" class="field font-mono !text-xs"
                               value="{{ $settings->get('sms.melipayamak.endpoint', 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber') }}"
                               placeholder="https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber">
                    </div>
                </div>
                <p class="st-hint">
                    کد پترن قالب‌ها در «مرکز پیامک» = <b>شناسه متن ثابت (bodyId)</b> پنل ملی‌پیامک؛ مقادیر متغیرهای قالب به‌ترتیب تعریف، با «;» به هم الحاق و ارسال می‌شوند.
                </p>
            </div>

            {{-- ===== کارت تنظیمات ایده‌پردازان ===== --}}
            <div class="st-gw" data-gw="idehpardazan">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--idehpardazan" aria-hidden="true">ا</span>
                    <div class="st-gw-title flex-1">
                        <b>ایده‌پردازان <span class="font-normal text-stone-400" dir="ltr">(RestfulSms)</span></b>
                        <span>ارسال قالب سریع (Ultra Fast Send) با کلیدهای پنل</span>
                    </div>
                    @if ($idehConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">پیکربندی کامل</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-grid-2">
                    <div class="st-field-row">
                        <label class="lbl" for="s-ide-key">کلید API <span class="text-stone-400 text-[10px]" dir="ltr">(UserApiKey)</span></label>
                        <input id="s-ide-key" data-key="sms.idehpardazan.api_key" data-empty-skip dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                               placeholder="{{ $idehConfigured ? '•••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'UserApiKey پنل ایده‌پردازان' }}">
                    </div>
                    <div class="st-field-row">
                        <label class="lbl" for="s-ide-secret">کلید امنیتی <span class="text-stone-400 text-[10px]" dir="ltr">(SecretKey)</span></label>
                        <input id="s-ide-secret" data-key="sms.idehpardazan.secret_key" data-empty-skip dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                               placeholder="{{ $idehConfigured ? '•••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'SecretKey پنل ایده‌پردازان' }}">
                    </div>
                </div>

                <div class="st-field-row">
                    <label class="lbl" for="s-ide-endpoint">آدرس ارسال قالب سریع</label>
                    <input id="s-ide-endpoint" data-key="sms.idehpardazan.endpoint" dir="ltr" class="field font-mono !text-xs"
                           value="{{ $settings->get('sms.idehpardazan.endpoint', 'https://RestfulSms.com/api/UltraFastSend/direct') }}"
                           placeholder="https://RestfulSms.com/api/UltraFastSend/direct">
                </div>
                <p class="st-hint">
                    کد پترن قالب‌ها در «مرکز پیامک» = <b>TemplateId</b> قالب سریع ایده‌پردازان؛ متغیرها به‌صورت «پارامتر نام‌دار» ارسال می‌شوند.
                </p>
            </div>

            {{-- ===== لاگ توسعه ===== --}}
            <div class="st-gw" data-gw="log">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--log" aria-hidden="true">ل</span>
                    <div class="st-gw-title flex-1">
                        <b>لاگ توسعه <span class="font-normal text-stone-400" dir="ltr">(log)</span></b>
                        <span>بدون ارسال واقعی — همهٔ پیامک‌ها فقط در «گزارش پیامک‌ها» ثبت می‌شوند</span>
                    </div>
                    <span class="badge bg-stone-100 text-stone-500 border border-stone-200">محیط توسعه</span>
                </div>
                <p class="st-hint">برای تست کامل جریان‌های سیستم بدون هزینهٔ پیامک مناسب است؛ در پروداکشن یکی از پرووایدرهای واقعی بالا را انتخاب و پیکربندی کنید.</p>
            </div>

            <div class="st-section-foot">
                <button type="button" id="btn-test-sms" class="btn-ghost ui-press !py-2.5">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4Z"/></svg>
                    ارسال پیامک آزمایشی
                </button>
                <button type="submit" class="btn-primary btn-shine ui-press !py-2.5 px-7">ذخیرهٔ تنظیمات پیامک</button>
            </div>
        </form>

        {{-- ---------- سفارش‌ها ---------- --}}
        <form data-group="orders" class="st-section card ui-lift animate-fade-up hidden" id="sec-orders">
            <div class="st-section-head">
                <span class="st-section-icon st-section-icon--orders" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                </span>
                <div class="flex-1">
                    <h2 class="st-section-title">موتور پخش سفارش‌ها</h2>
                    <p class="st-section-desc">سفارش‌های جدید بین کافی‌نت‌ها پخش می‌شوند — محدوده، مهلت و سیاست پس از انقضا را تعیین کنید.</p>
                </div>
            </div>

            <div class="st-field-row">
                <label class="lbl" for="o-scope">محدودهٔ پخش سفارش</label>
                <select id="o-scope" data-key="orders.broadcast_scope" class="field">
                    @foreach (['all' => 'همهٔ کافی‌نت‌ها (بدون فیلتر جغرافیایی)', 'province' => 'فقط کافی‌نت‌های هم‌استان مشتری', 'city' => 'فقط کافی‌نت‌های هم‌شهرستان مشتری'] as $value => $label)
                        <option value="{{ $value }}" {{ $settings->get('orders.broadcast_scope', 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="st-hint">پیش‌فرض فعلی: پخش به همهٔ شهرستان‌ها بدون فیلتر</p>
            </div>

            <div class="st-grid-2">
                <div class="st-field-row !mb-0">
                    <label class="lbl" for="o-timeout">مهلت پخش (ثانیه)</label>
                    <input id="o-timeout" data-key="orders.broadcast_timeout" type="number" min="15" max="300" class="field"
                           value="{{ $settings->get('orders.broadcast_timeout', 60) }}">
                    <p class="st-hint">پس از این زمان بدون پذیرش، سفارش به صف تعیین‌تکلیف می‌رود</p>
                </div>
                <div class="st-field-row !mb-0">
                    <label class="lbl" for="o-assign">سیاست پس از انقضای مهلت</label>
                    <select id="o-assign" data-key="orders.assign_after_timeout" class="field">
                        @foreach (['manual' => 'در انتظار تخصیص دستی ادمین', 'rebroadcast' => 'پخش مجدد خودکار (چرخشی)'] as $value => $label)
                            <option value="{{ $value }}" {{ $settings->get('orders.assign_after_timeout', 'manual') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="st-hint">سیاست فعلی: صف تعیین‌تکلیف دستی</p>
                </div>
            </div>

            <div class="st-section-foot">
                <button type="submit" class="btn-primary btn-shine ui-press !py-2.5 px-7">ذخیرهٔ تنظیمات سفارش‌ها</button>
            </div>
        </form>

        {{-- ---------- ساعت کاری (فاز ۱۵) ---------- --}}
        <form data-group="workhours" class="st-section card ui-lift animate-fade-up hidden" id="sec-workhours">
            <div class="st-section-head">
                <span class="st-section-icon st-section-icon--referral" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </span>
                <div class="flex-1">
                    <h2 class="st-section-title">ساعت کاری و محدودیت ثبت درخواست</h2>
                    <p class="st-section-desc">خارج از ساعت کاری، مشتری نمی‌تواند درخواست جدید ثبت کند — در اپ مشتری و API با مودال زیبا informing می‌شود (همین قاعده در POST /orders هم اعمال می‌شود).</p>
                </div>
            </div>

            @php
                $whEnabled = (bool) $settings->get('workhours.enabled');
                $whDays = collect(explode(',', (string) $settings->get('workhours.days', '6,0,1,2,3,4')))
                    ->map(fn ($d) => (int) trim($d))->filter(fn ($d) => $d >= 0 && $d <= 6)->values()->all();
                $weekDays = [
                    ['value' => 6, 'label' => 'شنبه'],
                    ['value' => 0, 'label' => 'یکشنبه'],
                    ['value' => 1, 'label' => 'دوشنبه'],
                    ['value' => 2, 'label' => 'سه‌شنبه'],
                    ['value' => 3, 'label' => 'چهارشنبه'],
                    ['value' => 4, 'label' => 'پنج‌شنبه'],
                    ['value' => 5, 'label' => 'جمعه'],
                ];
            @endphp

            {{-- سوییچ فعال/غیرفعال --}}
            <div class="st-switch-row">
                <div class="min-w-0">
                    <b class="block text-sm">فعال‌سازی محدودیت ساعت کاری</b>
                    <small class="st-hint">وقتی روشن باشد، ثبت درخواست فقط در بازهٔ تعیین‌شده ممکن است.</small>
                </div>
                <label class="st-switch">
                    <input type="checkbox" id="wh-enabled" class="opacity-0" data-key="workhours.enabled" {{ $whEnabled ? 'checked' : '' }}>
                    <span class="st-switch-track" aria-hidden="true"></span>
                </label>
            </div>

            <div class="st-grid-2">
                <div class="st-field-row !mb-0">
                    <label class="lbl" for="wh-start">شروع ساعت کاری</label>
                    <input id="wh-start" data-key="workhours.start" type="time" dir="ltr" class="field !text-center"
                           value="{{ $settings->get('workhours.start', '08:00') }}">
                </div>
                <div class="st-field-row !mb-0">
                    <label class="lbl" for="wh-end">پایان ساعت کاری</label>
                    <input id="wh-end" data-key="workhours.end" type="time" dir="ltr" class="field !text-center"
                           value="{{ $settings->get('workhours.end', '22:00') }}">
                    <p class="st-hint">اگر پایان قبل از شروع باشد، بازهٔ شبانه در نظر گرفته می‌شود (مثلاً ۱۸:۰۰ تا ۰۲:۰۰).</p>
                </div>
            </div>

            <div class="st-field-row">
                <label class="lbl">روزهای کاری</label>
                <input type="hidden" id="wh-days" data-key="workhours.days" value="{{ implode(',', $whDays) }}">
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="روزهای کاری">
                    @foreach ($weekDays as $day)
                        <button type="button" class="wh-day-chip {{ in_array($day['value'], $whDays, true) ? 'is-on' : '' }}"
                                data-day="{{ $day['value'] }}" aria-pressed="{{ in_array($day['value'], $whDays, true) ? 'true' : 'false' }}">
                            {{ $day['label'] }}
                        </button>
                    @endforeach
                </div>
                <p class="st-hint">حداقل یک روز باید انتخاب باشد — روزهای غیر انتخابی، کل روز «بسته» محسوب می‌شوند.</p>
            </div>

            <div class="st-field-row">
                <label class="lbl" for="wh-message">پیام سفارشی مودال خارج از ساعت کاری (اختیاری)</label>
                <textarea id="wh-message" data-key="workhours.message" class="field min-h-16" rows="2" maxlength="500"
                          placeholder="مثلاً: لطفاً در ساعت کاری (۹ صبح تا ۹ شب) درخواست خود را ثبت کنید.">{{ $settings->get('workhours.message', '') }}</textarea>
            </div>

            <div class="st-section-foot">
                <button type="submit" class="btn-primary btn-shine ui-press !py-2.5 px-7">ذخیرهٔ تنظیمات ساعت کاری</button>
            </div>
        </form>

        {{-- ---------- درگاه پرداخت (بازطراحی v13 — درگاه بانکی) ---------- --}}
        <form data-group="payment" class="st-section card ui-lift animate-fade-up hidden" id="sec-payment">
            <div class="st-section-head">
                <span class="st-section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
                </span>
                <div class="flex-1">
                    <h2 class="st-section-title">درگاه پرداخت آنلاین</h2>
                    <p class="st-section-desc">درگاه فعال را انتخاب کنید — اطلاعات پذیرندگی همان درگاه بلافاصله زیر کارت‌ها باز می‌شود. پرداخت سفارش و شارژ کیف پول مشتری از همین درگاه انجام می‌شود (پکیج shetabit/payment).</p>
                </div>
                <span class="badge {{ $payDriver === 'local' ? 'bg-stone-100 text-stone-500 border border-stone-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}" id="pay-driver-badge">{{ $payDriverLabel }}</span>
            </div>

            {{-- کارت‌های انتخاب درگاه — فعال: تنظیمات همان درگاه زیرش باز می‌شود --}}
            <div class="st-pick-head">
                <b>درگاه فعال</b>
                <span>با انتخاب کارت، اطلاعات پذیرندگی همان درگاه در ادامه نمایان می‌شود</span>
            </div>

            <div class="st-prov-grid st-prov-grid--pay" role="radiogroup" aria-label="انتخاب درگاه پرداخت">
                <label class="st-prov-card" data-driver="local">
                    <input type="radio" name="pay-driver" value="local" class="sr-only" {{ $payDriver === 'local' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--local" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 2 2 4-4"/><circle cx="10" cy="8" r="4"/><path d="M14 18a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/></svg>
                    </span>
                    <b class="st-prov-name">درگاه تست</b>
                    <span class="st-prov-sub">local · بدون کلید</span>
                </label>

                <label class="st-prov-card" data-driver="zarinpal">
                    <input type="radio" name="pay-driver" value="zarinpal" class="sr-only" {{ $payDriver === 'zarinpal' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--zarinpal" aria-hidden="true">ز</span>
                    <b class="st-prov-name">زرین‌پال</b>
                    <span class="st-prov-sub">merchantId پذیرنده</span>
                </label>

                <label class="st-prov-card" data-driver="zibal">
                    <input type="radio" name="pay-driver" value="zibal" class="sr-only" {{ $payDriver === 'zibal' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--zibal" aria-hidden="true">ض</span>
                    <b class="st-prov-name">زیبال</b>
                    <span class="st-prov-sub">merchantId پذیرنده</span>
                </label>

                <label class="st-prov-card" data-driver="behpardakht">
                    <input type="radio" name="pay-driver" value="behpardakht" class="sr-only" {{ $payDriver === 'behpardakht' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--mellat" aria-hidden="true">م</span>
                    <b class="st-prov-name">بانک ملت</b>
                    <span class="st-prov-sub">به‌پرداخت ملت · BPM</span>
                </label>

                <label class="st-prov-card" data-driver="sep">
                    <input type="radio" name="pay-driver" value="sep" class="sr-only" {{ $payDriver === 'sep' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--melli" aria-hidden="true">م</span>
                    <b class="st-prov-name">بانک ملی</b>
                    <span class="st-prov-sub">درگاه سپ · SEP</span>
                </label>

                <label class="st-prov-card" data-driver="sepehr">
                    <input type="radio" name="pay-driver" value="sepehr" class="sr-only" {{ $payDriver === 'sepehr' ? 'checked' : '' }}>
                    <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <span class="st-prov-tile st-tile--sepehr" aria-hidden="true">س</span>
                    <b class="st-prov-name">درگاه سپهر</b>
                    <span class="st-prov-sub">بانک صادرات</span>
                </label>
            </div>

            <input type="hidden" id="p-driver" data-key="payment.driver" value="{{ $payDriver }}">

            {{-- ===== درگاه تست (local) ===== --}}
            <div class="st-gw" data-gw="local">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--local" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 2 2 4-4"/><circle cx="10" cy="8" r="4"/><path d="M14 18a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/></svg>
                    </span>
                    <div class="st-gw-title flex-1">
                        <b>درگاه تست <span class="font-normal text-stone-400" dir="ltr">(local)</span></b>
                        <span>درگاه داخلی برای توسعه — بدون کلید؛ دکمه‌های «پرداخت موفق / ناموفق» واقعی</span>
                    </div>
                    <span class="badge bg-stone-100 text-stone-500 border border-stone-200">بدون کلید</span>
                </div>
                <p class="st-hint">برای بررسی صحت روند پرداخت و لغو پرداخت استفاده می‌شود؛ در پروداکشن یکی از درگاه‌های واقعی را انتخاب و پیکربندی کنید.</p>
            </div>

            {{-- ===== زرین‌پال ===== --}}
            <div class="st-gw" data-gw="zarinpal">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--zarinpal" aria-hidden="true">ز</span>
                    <div class="st-gw-title flex-1">
                        <b>زرین‌پال <span class="font-normal text-stone-400" dir="ltr">(ZarinPal)</span></b>
                        <span>درگاه رسمی زرین‌پال — نیازمند مرچنت‌کد پذیرنده</span>
                    </div>
                    @if ($zarinpalConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">مرچنت ثبت‌شده</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-field-row">
                    <label class="lbl" for="p-zarinpal-merchant">مرچنت‌کد پذیرنده</label>
                    <input id="p-zarinpal-merchant" data-key="payment.zarinpal.merchant_id" data-empty-skip dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                           placeholder="{{ $zarinpalConfigured ? '•••••••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'مرچنت‌کد ۳۶ کاراکتری زرین‌پال' }}">
                </div>

                <div class="st-switch-row !mb-0">
                    <div>
                        <p class="text-xs font-bold text-stone-700">حالت آزمایشی <span class="text-stone-400 text-[10px]" dir="ltr">(Sandbox)</span></p>
                        <p class="text-[11px] text-stone-400 mt-0.5">پرداخت‌ها به سرور تست زرین‌پال ارسال می‌شوند — برای اتصال واقعی خاموش کنید</p>
                    </div>
                    <label class="st-switch" for="p-zarinpal-sandbox">
                        <input type="checkbox" id="p-zarinpal-sandbox" data-key="payment.zarinpal.sandbox" class="peer sr-only" {{ $zarinpalSandbox ? 'checked' : '' }}>
                        <span class="st-switch-track" aria-hidden="true"></span>
                    </label>
                </div>
            </div>

            {{-- ===== زیبال ===== --}}
            <div class="st-gw" data-gw="zibal">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--zibal" aria-hidden="true">ض</span>
                    <div class="st-gw-title flex-1">
                        <b>زیبال <span class="font-normal text-stone-400" dir="ltr">(Zibal)</span></b>
                        <span>درگاه زیبال — نیازمند مرچنت‌کد پذیرنده</span>
                    </div>
                    @if ($zibalConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">مرچنت ثبت‌شده</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-field-row !mb-0">
                    <label class="lbl" for="p-zibal-merchant">مرچنت‌کد پذیرنده</label>
                    <input id="p-zibal-merchant" data-key="payment.zibal.merchant_id" data-empty-skip dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                           placeholder="{{ $zibalConfigured ? '•••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'مرچنت‌کد زیبال' }}">
                    <p class="st-hint">مرچنت <span class="font-mono text-amber-600" dir="ltr">zibal</span> برای تست درگاه زیبال است</p>
                </div>
            </div>

            {{-- ===== بانک ملت (به‌پرداخت) ===== --}}
            <div class="st-gw" data-gw="behpardakht">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--mellat" aria-hidden="true">م</span>
                    <div class="st-gw-title flex-1">
                        <b>بانک ملت <span class="font-normal text-stone-400" dir="ltr">(به‌پرداخت ملت — BPM)</span></b>
                        <span>درگاه شاپرک به‌پرداخت ملت · نیازمند ترمینال + نام کاربری + رمز</span>
                    </div>
                    @if ($behpConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">پیکربندی کامل</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-grid-2">
                    <div class="st-field-row">
                        <label class="lbl" for="p-mellat-terminal">شماره ترمینال <span class="text-stone-400 text-[10px]" dir="ltr">(terminalId)</span></label>
                        <input id="p-mellat-terminal" data-key="payment.behpardakht.terminal_id" dir="ltr" class="field font-mono !text-xs" inputmode="numeric"
                               placeholder="مثال: 1234567" value="{{ $settings->get('payment.behpardakht.terminal_id') }}">
                    </div>
                    <div class="st-field-row">
                        <label class="lbl" for="p-mellat-user">نام کاربری <span class="text-stone-400 text-[10px]" dir="ltr">(username)</span></label>
                        <input id="p-mellat-user" data-key="payment.behpardakht.username" dir="ltr" class="field font-mono !text-xs" autocomplete="off"
                               placeholder="نام کاربری پذیرنده" value="{{ $settings->get('payment.behpardakht.username') }}">
                    </div>
                </div>

                <div class="st-field-row !mb-0">
                    <label class="lbl" for="p-mellat-pass">رمز عبور <span class="text-stone-400 text-[10px]" dir="ltr">(password)</span></label>
                    <input id="p-mellat-pass" data-key="payment.behpardakht.password" data-empty-skip dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                           placeholder="{{ $behpConfigured ? '•••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'رمز عبور پذیرنده به‌پرداخت ملت' }}">
                </div>
                <p class="st-hint">
                    سه مقدار <span class="font-mono text-amber-600" dir="ltr">terminalId · username · password</span> همان‌هایی هستند که هنگام صدور درگاه در پنل به‌پرداخت ملت (bpm.shaparak.ir) دریافت کرده‌اید؛ مبلغ به تومان ارسال می‌شود.
                </p>
            </div>

            {{-- ===== بانک ملی (سپ SEP) ===== --}}
            <div class="st-gw" data-gw="sep">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--melli" aria-hidden="true">م</span>
                    <div class="st-gw-title flex-1">
                        <b>بانک ملی <span class="font-normal text-stone-400" dir="ltr">(درگاه سپ — SEP)</span></b>
                        <span>سامانه الکترونیکی پرداخت (sep.shaparak.ir) · نیازمند ترمینال</span>
                    </div>
                    @if ($sepConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">ترمینال ثبت‌شده</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-field-row !mb-0">
                    <label class="lbl" for="p-sep-terminal">شماره ترمینال <span class="text-stone-400 text-[10px]" dir="ltr">(TerminalId)</span></label>
                    <input id="p-sep-terminal" data-key="payment.sep.terminal_id" dir="ltr" class="field font-mono !text-xs" inputmode="numeric"
                           placeholder="مثال: 12345678" value="{{ $settings->get('payment.sep.terminal_id') }}">
                    <p class="st-hint">شماره ترمینال پذیرندگی که در پنل درگاه سپ بانک ملی (sep.shaparak.ir) دریافت کرده‌اید؛ مبلغ به تومان ارسال می‌شود.</p>
                </div>
            </div>

            {{-- ===== درگاه سپهر (بانک صادرات) ===== --}}
            <div class="st-gw" data-gw="sepehr">
                <div class="st-gw-head">
                    <span class="st-prov-tile st-tile--sepehr" aria-hidden="true">س</span>
                    <div class="st-gw-title flex-1">
                        <b>درگاه سپهر <span class="font-normal text-stone-400" dir="ltr">(بانک صادرات — Sepehr)</span></b>
                        <span>درگاه سپهر شاپرک (sepehr.shaparak.ir) · نیازمند ترمینال</span>
                    </div>
                    @if ($sepehrConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">ترمینال ثبت‌شده</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-field-row !mb-0">
                    <label class="lbl" for="p-sepehr-terminal">شماره ترمینال <span class="text-stone-400 text-[10px]" dir="ltr">(TerminalID)</span></label>
                    <input id="p-sepehr-terminal" data-key="payment.sepehr.terminal_id" dir="ltr" class="field font-mono !text-xs" inputmode="numeric"
                           placeholder="مثال: 1234567890" value="{{ $settings->get('payment.sepehr.terminal_id') }}">
                    <p class="st-hint">شماره ترمینال پذیرندگی که در پنل درگاه سپهر بانک صادرات (sepehr.shaparak.ir) دریافت کرده‌اید؛ مبلغ به تومان ارسال می‌شود.</p>
                </div>
            </div>

            <div class="st-section-foot">
                <button type="submit" class="btn-primary btn-shine ui-press !py-2.5 px-7">ذخیرهٔ تنظیمات درگاه</button>
            </div>
        </form>

        {{-- ---------- کارکنان کافی‌نت‌ها (سیاست تایید) ---------- --}}
        <form data-group="staff" class="st-section card ui-lift animate-fade-up hidden" id="sec-staff">
            <div class="st-section-head">
                <span class="st-section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <div class="flex-1">
                    <h2 class="st-section-title">کارکنان کافی‌نت‌ها</h2>
                    <p class="st-section-desc">سیاست افزودن کارمند (اپراتور/مدیر) توسط مدیران کافی‌نت — هر اپراتور فقط در یک کافی‌نت فعالیت می‌کند و انتقال او فقط توسط شما انجام می‌شود.</p>
                </div>
                @php($hiringMode = $settings->get('staff.hiring.mode', 'auto'))
                <span class="badge {{ $hiringMode === 'approval' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">{{ $hiringMode === 'approval' ? 'نیازمند تایید شما' : 'تایید خودکار' }}</span>
            </div>

            <div class="st-sub-card">
                <div class="st-sub-head">
                    <b>نحوهٔ تایید کارمند جدید</b>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup" aria-label="سیاست تایید کارمند">
                    <label class="cursor-pointer" data-mode="auto">
                        <input type="radio" name="staff-hiring-mode" value="auto" class="sr-only peer" {{ $hiringMode !== 'approval' ? 'checked' : '' }}>
                        <div class="flex items-start gap-3 rounded-2xl border-2 border-stone-200 p-4 transition-all duration-200 peer-checked:border-amber-400 peer-checked:bg-amber-50/60 hover:border-amber-300">
                            <span class="grid place-items-center size-10 rounded-xl bg-emerald-100 text-emerald-600 shrink-0" aria-hidden="true">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <div class="min-w-0">
                                <b class="block text-xs font-extrabold text-stone-800">تایید خودکار</b>
                                <span class="block text-[10px] text-stone-400 leading-5 mt-1">کارمند بلافاصله پس از افزودن توسط مدیر کافی‌نت فعال می‌شود — سریع و بدون دخالت شما</span>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer" data-mode="approval">
                        <input type="radio" name="staff-hiring-mode" value="approval" class="sr-only peer" {{ $hiringMode === 'approval' ? 'checked' : '' }}>
                        <div class="flex items-start gap-3 rounded-2xl border-2 border-stone-200 p-4 transition-all duration-200 peer-checked:border-amber-400 peer-checked:bg-amber-50/60 hover:border-amber-300">
                            <span class="grid place-items-center size-10 rounded-xl bg-amber-100 text-amber-600 shrink-0" aria-hidden="true">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m16.24 7.76-2.12 2.12"/><path d="M21 12h-4"/><path d="m16.24 16.24 2.12 2.12"/><path d="M12 18v4"/><path d="m7.76 16.24-2.12 2.12"/><path d="M6 12H2"/><path d="m7.76 7.76 5.66-5.66"/><circle cx="12" cy="12" r="3"/></svg>
                            </span>
                            <div class="min-w-0">
                                <b class="block text-xs font-extrabold text-stone-800">تایید توسط مدیر کل</b>
                                <span class="block text-[10px] text-stone-400 leading-5 mt-1">کارمند «در انتظار تایید» ساخته می‌شود و پس از تایید شما در صفحهٔ «کارکنان و اپراتورها» فعال می‌گردد</span>
                            </div>
                        </div>
                    </label>
                </div>

                <input type="hidden" id="s-hiring-mode" data-key="staff.hiring.mode" value="{{ $hiringMode }}">
                <p class="st-hint">کارمندان در انتظار، در صفحهٔ «کارکنان و اپراتورها» (فیلتر «در انتظار تایید») قابل تایید یا رد هستند.</p>
            </div>

            <div class="st-section-foot">
                <button type="submit" class="btn-primary btn-shine ui-press !py-2.5 px-7">ذخیرهٔ سیاست کارکنان</button>
            </div>
        </form>

        {{-- ---------- پاداش معرفی ---------- --}}
        {{-- ================== Realtime — پوشر (فاز ۱۳) ================== --}}
        {{-- $pusherOn/$pusherReady از کنترلر می‌آیند --}}
        <form data-group="realtime" class="st-section card ui-lift animate-fade-up hidden" id="sec-realtime">
            <div class="st-section-head">
                <span class="st-section-icon" aria-hidden="true" style="background:rgba(245,158,11,.12);color:#b45309">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </span>
                <div class="flex-1">
                    <h2 class="st-section-title">Realtime — سرویس پوشر (Pusher)</h2>
                    <p class="st-section-desc">لایهٔ آنی روی سیستم اعلان/چت: با هر رویداد، مرورگرها همان لحظه بیدار می‌شوند و دیگر به پولینگ پرتکرار وابسته نیستند (کاهش فشار MySQL).</p>
                </div>
                <span class="badge {{ $pusherReady ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($pusherOn ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-stone-100 text-stone-500 border border-stone-200') }}">{{ $pusherReady ? 'فعال' : ($pusherOn ? 'ناقص' : 'خاموش') }}</span>
            </div>

            <div class="st-sub-card" style="background:linear-gradient(135deg,rgba(245,158,11,.05),transparent)">
                <div class="st-sub-head">
                    <b>چگونه کار می‌کند؟</b>
                </div>
                <p class="st-hint leading-6">
                    پوشر فقط «زنگ خبر» است؛ داده‌ها همیشه از سرور خود شما خوانده می‌شوند. با فعال بودن پوشر:
                    زنگ اعلان‌ها و پیام‌های گفتگو <b>لحظه‌ای</b> می‌رسند و بازهٔ پولینگ خودکار از ۳ ثانیه به ۱۲ ثانیه افزایش می‌یابد.
                    اگر پوشر خاموش یا قطع باشد، سیستم کاملاً مثل قبل با پولینگ سریع کار می‌کند — هیچ داده‌ای از دست نمی‌رود.
                </p>
            </div>

            <div class="st-field-row">
                <label class="lbl" for="rt-enabled">فعال‌سازی پوشر</label>
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input id="rt-enabled" data-key="realtime.pusher.enabled" type="checkbox" class="size-5 accent-amber-600" {{ $pusherOn ? 'checked' : '' }}>
                    <span class="text-xs font-bold text-stone-700">{{ $pusherOn ? 'پوشر فعال است' : 'پوشر خاموش است (فقط پولینگ)' }}</span>
                </label>
            </div>

            <div class="st-grid-2">
                <div class="st-field-row !mb-0">
                    <label class="lbl" for="rt-app-id">App ID <span class="text-stone-400 text-[10px]">(رقمی)</span></label>
                    <input id="rt-app-id" data-key="realtime.pusher.app_id" data-empty-skip dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                           placeholder="{{ trim((string) $settings->get('realtime.pusher.app_id')) !== '' ? '••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'مثال: 1234567' }}">
                </div>
                <div class="st-field-row !mb-0">
                    <label class="lbl" for="rt-cluster">Cluster</label>
                    <select id="rt-cluster" data-key="realtime.pusher.cluster" class="field">
                        @foreach (['mt1' => 'mt1 — بمبئی (توصیه‌شده برای ایران)', 'eu' => 'eu — اروپا', 'ap2' => 'ap2 — آسیا-جنوب‌شرقی', 'ap1' => 'ap1 — آسیا-شرقی', 'us2' => 'us2 — آمریکا', 'us3' => 'us3 — آمریکا'] as $c => $cl)
                            <option value="{{ $c }}" {{ (string) $settings->get('realtime.pusher.cluster', 'mt1') === $c ? 'selected' : '' }}>{{ $cl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="st-field-row !mb-0">
                    <label class="lbl" for="rt-app-key">App Key <span class="text-stone-400 text-[10px]">(عمومی)</span></label>
                    <input id="rt-app-key" data-key="realtime.pusher.app_key" dir="ltr" class="field font-mono !text-xs" autocomplete="off"
                           value="{{ (string) $settings->get('realtime.pusher.app_key') }}" placeholder="مثال: 7a1b2c3d4e5f6g7h8i9j">
                </div>
                <div class="st-field-row !mb-0">
                    <label class="lbl" for="rt-app-secret">App Secret <span class="text-stone-400 text-[10px]">(محرمانه)</span></label>
                    <input id="rt-app-secret" data-key="realtime.pusher.app_secret" data-empty-skip dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                           placeholder="{{ trim((string) $settings->get('realtime.pusher.app_secret')) !== '' ? '••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'مثال: 4f8b2c1d9e0a...' }}">
                </div>
            </div>

            <div class="st-sub-card">
                <div class="st-sub-head">
                    <b>راهنمای دریافت کلیدها</b>
                </div>
                <p class="st-hint leading-6">
                    ۱) در <b dir="ltr">pusher.com</b> ثبت‌نام کنید و یک App جدید بسازید (پلن رایگان Sandbox کافی است).
                    ۲) در تب «App Keys» چهار مقدار <span dir="ltr" class="font-mono">app_id · key · secret · cluster</span> را کپی کنید.
                    ۳) مقادیر را اینجا وارد کنید، ذخیره کنید و با دکمهٔ «تست اتصال» صحت آن‌ها را بررسی کنید.
                </p>
            </div>

            <div class="st-section-foot flex flex-wrap items-center gap-3">
                <button type="submit" class="btn-primary btn-shine ui-press !py-2.5 px-7">ذخیرهٔ تنظیمات پوشر</button>
                <button type="button" id="btn-test-pusher" class="btn-ghost ui-press !py-2.5">
                    <span class="size-4 inline-block align-middle me-1" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg></span>
                    تست اتصال (پس از ذخیره)
                </button>
            </div>
        </form>

        <form id="sec-referral" class="st-section card ui-lift animate-fade-up hidden" data-referral>
            <div class="st-section-head">
                <span class="st-section-icon st-section-icon--referral" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <div class="flex-1">
                    <h2 class="st-section-title">پاداش معرفی کافی‌نت</h2>
                    <p class="st-section-desc">پاداش اولیه هنگام تأیید کافی‌نتِ معرفی‌شده و پاداش هر سفارش هنگام تسویهٔ آن، به‌صورت خودکار به کیف پول سازمان واریز می‌شود.</p>
                </div>
                @if ($referral->is_active)
                    <span id="r-badge" class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">فعال</span>
                @else
                    <span id="r-badge" class="badge bg-stone-100 text-stone-500 border border-stone-200">غیرفعال</span>
                @endif
            </div>

            <div class="st-switch-row">
                <div>
                    <p class="text-xs font-bold text-stone-700">فعال بودن سیستم پاداش</p>
                    <p class="text-[11px] text-stone-400 mt-0.5">در صورت غیرفعالی، هیچ پاداشی پرداخت نمی‌شود</p>
                </div>
                <label class="st-switch" for="r-active">
                    <input type="checkbox" id="r-active" class="peer sr-only" {{ $referral->is_active ? 'checked' : '' }}>
                    <span class="st-switch-track" aria-hidden="true"></span>
                </label>
            </div>

            <div class="st-field-row">
                <label class="lbl" for="r-reward">پاداش اولیهٔ معرفی (تومان)</label>
                <input id="r-reward" type="number" min="0" step="1000" class="field" dir="ltr"
                       value="{{ (float) $referral->introduction_reward }}">
                <p class="st-hint">۰ = بدون پاداش اولیه — هنگام تأیید هر کافی‌نتِ معرفی‌شده واریز می‌شود</p>
            </div>

            <div class="st-grid-2">
                <div class="st-field-row !mb-0">
                    <label class="lbl" for="r-per-type">پاداش از هر سفارش (نوع)</label>
                    <select id="r-per-type" class="field">
                        @foreach (['percent' => 'درصدی از کمیسیون سازمان', 'fixed' => 'مبلغ ثابت (تومان)'] as $value => $label)
                            <option value="{{ $value }}" {{ $referral->per_order_type === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="st-field-row !mb-0">
                    <label class="lbl" for="r-per-value">مقدار</label>
                    <input id="r-per-value" type="number" min="0" step="any" class="field" dir="ltr"
                           value="{{ (float) $referral->per_order_value }}">
                    <p class="st-hint">درصد (۰ تا ۱۰۰) از کمیسیون سازمان یا مبلغ ثابت به تومان — هنگام تسویهٔ هر سفارش واریز می‌شود</p>
                </div>
            </div>

            <div class="st-section-foot">
                <button type="submit" class="btn-primary btn-shine ui-press !py-2.5 px-7">ذخیرهٔ پاداش معرفی</button>
            </div>
        </form>

    </div>
</div>

{{-- مودال پیامک آزمایشی --}}
<div id="sms-modal" class="ui-modal-backdrop hidden">
    <div data-close class="absolute inset-0" aria-hidden="true"></div>
    <form id="sms-form" class="ui-modal adm-modal-sm adm-modal-text-start" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="sms-title" novalidate>
        <div class="adm-modal-head">
            <h3 class="text-sm font-extrabold text-stone-800" id="sms-title">پیامک آزمایشی</h3>
            <button type="button" class="adm-modal-x" data-close aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="adm-modal-body">
            <div>
                <label class="lbl" for="t-mobile">شماره موبایل</label>
                <input id="t-mobile" type="tel" dir="ltr" class="field" placeholder="09123456789" required>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="mobile"></p>
            </div>
        </div>
        <div class="adm-modal-foot">
            <button type="submit" id="sms-send" class="btn-primary btn-shine w-full !py-3">ارسال</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/settings/index.js') }}?v=17"></script>
@endpush
