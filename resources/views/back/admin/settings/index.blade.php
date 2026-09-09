@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'تنظیمات')
@section('page-title', 'تنظیمات سیستم')
@section('breadcrumb', 'پنل مدیریت کل ← تنظیمات')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/settings.css') }}?v=14">
@endpush

@section('content')

@php
    $provider = $settings->get('sms.provider', 'log');
    $kvnConfigured = (bool) $settings->get('sms.kavenegar.api_key');
    $fraaConfigured = (bool) $settings->get('sms.fraasms.api_key');
    $providerLabel = match ($provider) {
        'kavenegar' => 'کاوه‌نگار',
        'fraasms' => 'فراز اس‌ام‌اس',
        default => 'لاگ (محیط توسعه)',
    };

    $payDriver = (string) $settings->get('payment.driver', 'local');
    $payDriverLabel = match ($payDriver) {
        'zarinpal' => 'زرین‌پال',
        'zibal' => 'زیبال',
        default => 'درگاه تست (local)',
    };
    $zarinpalConfigured = (bool) $settings->get('payment.zarinpal.merchant_id');
    $zibalConfigured = (bool) $settings->get('payment.zibal.merchant_id');
    $zarinpalSandbox = (bool) $settings->get('payment.zarinpal.sandbox', true);
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
                    <p class="st-section-desc">پرووایدر ارسال را انتخاب و کلیدهای آن را وارد کنید. مدیریت متن‌ها و پترن‌ها در «مرکز پیامک» است.</p>
                </div>
                <span class="badge {{ $provider === 'log' ? 'bg-stone-100 text-stone-500 border border-stone-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">{{ $providerLabel }}</span>
            </div>

            <div class="st-field-row">
                <label class="lbl" for="s-provider">پرووایدر فعال</label>
                <select id="s-provider" data-key="sms.provider" class="field">
                    @foreach ($providers as $value => $label)
                        <option value="{{ $value }}" {{ $provider === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="st-hint">در محیط توسعه «log» را انتخاب کنید — پیامک‌ها فقط در لاگ ثبت می‌شوند</p>
            </div>

            {{-- کاوه‌نگار --}}
            <div class="st-sub-card" id="kvn-box">
                <div class="st-sub-head">
                    <b>کاوه‌نگار (Kavenegar)</b>
                    @if ($kvnConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">کلید ثبت‌شده</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-field-row">
                    <label class="lbl" for="s-kvn-key">کلید API</label>
                    <input id="s-kvn-key" data-key="sms.kavenegar.api_key" dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                           placeholder="{{ $kvnConfigured ? '•••••••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'کلید API را وارد کنید' }}">
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
                <p class="st-hint">پترن‌های Verify Lookup این پرووایدر از «مرکز پیامک» مدیریت می‌شوند</p>
            </div>

            {{-- فراز اس‌ام‌اس --}}
            <div class="st-sub-card" id="fraa-box">
                <div class="st-sub-head">
                    <b>فراز اس‌ام‌اس (ایران‌پیامک)</b>
                    @if ($fraaConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">کلید ثبت‌شده</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-field-row">
                    <label class="lbl" for="s-api-key">کلید API (Api-Key)</label>
                    <input id="s-api-key" data-key="sms.fraasms.api_key" dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                           placeholder="{{ $fraaConfigured ? '•••••••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'کلید API پنل فراز را وارد کنید' }}">
                    <p class="st-hint">از پنل فراز اس‌ام‌اس: بخش «API» → تولید کلید (به‌صورت هدر <span class="font-mono text-amber-600" dir="ltr">Api-Key</span> ارسال می‌شود)</p>
                </div>

                <div class="st-grid-2">
                    <div class="st-field-row !mb-0">
                        <label class="lbl" for="s-sender">شماره خط (line_number)</label>
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
                    ارسال‌ها <b class="text-amber-700">فقط پترن‌محور</b> است (سرویس‌دهنده متن آزاد را نمی‌پذیرد): کد پترن و متغیرهای نام‌دار هر رویداد را در «<a href="{{ route('admin.sms-templates.index') }}" class="text-amber-700 font-semibold hover:underline">مرکز پیامک</a>» ثبت کنید.
                    نام متغیرها باید با متغیرهای تعریف‌شدهٔ پترن در پنل فراز یکی باشد (مثل <span class="font-mono text-amber-600" dir="ltr">{code}</span>).
                    قالب <b>بدون کد پترن</b> ارسال نمی‌شود و خطای آن در گزارش پیامک‌ها ثبت می‌گردد.
                </p>
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

        {{-- ---------- درگاه پرداخت (درخواست بازخوردی) ---------- --}}
        <form data-group="payment" class="st-section card ui-lift animate-fade-up hidden" id="sec-payment">
            <div class="st-section-head">
                <span class="st-section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
                </span>
                <div class="flex-1">
                    <h2 class="st-section-title">درگاه پرداخت آنلاین</h2>
                    <p class="st-section-desc">درایور shetabit/payment را انتخاب و کلید پذیرندگی آن را وارد کنید — پرداخت سفارش و شارژ کیف پول مشتری از همین درگاه انجام می‌شود.</p>
                </div>
                <span class="badge {{ $payDriver === 'local' ? 'bg-stone-100 text-stone-500 border border-stone-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">{{ $payDriverLabel }}</span>
            </div>

            {{-- کارت‌های انتخاب درایور --}}
            <div class="st-sub-card">
                <div class="st-sub-head">
                    <b>درایور فعال</b>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" role="radiogroup" aria-label="انتخاب درگاه پرداخت">
                    <label class="pay-driver-card cursor-pointer" data-driver="local">
                        <input type="radio" name="pay-driver" value="local" class="sr-only peer" {{ $payDriver === 'local' ? 'checked' : '' }}>
                        <div class="flex items-start gap-3 rounded-2xl border-2 border-stone-200 p-4 transition-all duration-200 peer-checked:border-amber-400 peer-checked:bg-amber-50/60 hover:border-amber-300">
                            <span class="grid place-items-center size-10 rounded-xl bg-stone-100 text-stone-500 shrink-0" aria-hidden="true">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 2 2 4-4"/><circle cx="10" cy="8" r="4"/><path d="M14 18a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/></svg>
                            </span>
                            <div class="min-w-0">
                                <b class="block text-xs font-extrabold text-stone-800">درگاه تست (local)</b>
                                <span class="block text-[10px] text-stone-400 leading-5 mt-1">درگاه داخلی برای توسعه — بدون کلید؛ دکمه‌های موفق/ناموفق واقعی</span>
                            </div>
                        </div>
                    </label>

                    <label class="pay-driver-card cursor-pointer" data-driver="zarinpal">
                        <input type="radio" name="pay-driver" value="zarinpal" class="sr-only peer" {{ $payDriver === 'zarinpal' ? 'checked' : '' }}>
                        <div class="flex items-start gap-3 rounded-2xl border-2 border-stone-200 p-4 transition-all duration-200 peer-checked:border-amber-400 peer-checked:bg-amber-50/60 hover:border-amber-300">
                            <span class="grid place-items-center size-10 rounded-xl bg-amber-100 text-amber-600 shrink-0" aria-hidden="true">ز</span>
                            <div class="min-w-0">
                                <b class="block text-xs font-extrabold text-stone-800">زرین‌پال</b>
                                <span class="block text-[10px] text-stone-400 leading-5 mt-1">درگاه رسمی زرین‌پال — نیازمند مرچنت‌کد پذیرنده</span>
                            </div>
                        </div>
                    </label>

                    <label class="pay-driver-card cursor-pointer" data-driver="zibal">
                        <input type="radio" name="pay-driver" value="zibal" class="sr-only peer" {{ $payDriver === 'zibal' ? 'checked' : '' }}>
                        <div class="flex items-start gap-3 rounded-2xl border-2 border-stone-200 p-4 transition-all duration-200 peer-checked:border-amber-400 peer-checked:bg-amber-50/60 hover:border-amber-300">
                            <span class="grid place-items-center size-10 rounded-xl bg-teal-100 text-teal-600 shrink-0" aria-hidden="true">ض</span>
                            <div class="min-w-0">
                                <b class="block text-xs font-extrabold text-stone-800">زیبال</b>
                                <span class="block text-[10px] text-stone-400 leading-5 mt-1">درگاه زیبال — نیازمند مرچنت‌کد پذیرنده</span>
                            </div>
                        </div>
                    </label>
                </div>
                <input type="hidden" id="p-driver" data-key="payment.driver" value="{{ $payDriver }}">
                <p class="st-hint">درایور انتخابی بلافاصله برای همهٔ پرداخت‌های آنلاین (سفارش و شارژ کیف) اعمال می‌شود</p>
            </div>

            {{-- زرین‌پال --}}
            <div class="st-sub-card" id="zarinpal-box">
                <div class="st-sub-head">
                    <b>زرین‌پال</b>
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

                <div class="st-switch-row">
                    <div>
                        <p class="text-xs font-bold text-stone-700">حالت آزمایشی (Sandbox)</p>
                        <p class="text-[11px] text-stone-400 mt-0.5">پرداخت‌ها به سرور تست زرین‌پال ارسال می‌شوند — برای اتصال واقعی خاموش کنید</p>
                    </div>
                    <label class="st-switch" for="p-zarinpal-sandbox">
                        <input type="checkbox" id="p-zarinpal-sandbox" class="peer sr-only" {{ $zarinpalSandbox ? 'checked' : '' }}>
                        <span class="st-switch-track" aria-hidden="true"></span>
                    </label>
                </div>
            </div>

            {{-- زیبال --}}
            <div class="st-sub-card" id="zibal-box">
                <div class="st-sub-head">
                    <b>زیبال (Zibal)</b>
                    @if ($zibalConfigured)
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">مرچنت ثبت‌شده</span>
                    @else
                        <span class="badge bg-amber-50 text-amber-700 border border-amber-200">پیکربندی نشده</span>
                    @endif
                </div>

                <div class="st-field-row">
                    <label class="lbl" for="p-zibal-merchant">مرچنت‌کد پذیرنده</label>
                    <input id="p-zibal-merchant" data-key="payment.zibal.merchant_id" data-empty-skip dir="ltr" class="field font-mono !text-xs" type="password" autocomplete="off"
                           placeholder="{{ $zibalConfigured ? '•••••••••••• (ذخیره‌شده — برای تغییر وارد کنید)' : 'مرچنت‌کد زیبال' }}">
                    <p class="st-hint">مرچنت «zibal» برای تست درگاه زینبال است</p>
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
<script src="{{ asset('back/assets/js/pages/admin/settings/index.js') }}?v=16"></script>
@endpush
