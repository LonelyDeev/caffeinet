@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'مرکز پیامک')
@section('page-title', 'مرکز پیامک')
@section('breadcrumb', 'پنل مدیریت کل ← مرکز پیامک')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/sms-center.css') }}?v=14">
@endpush

@section('content')

<div class="sc-stack">

    {{-- ================== هدر مرکز ================== --}}
    <div class="sc-hero card ui-lift animate-fade-up">
        <div class="sc-hero-main">
            <span class="sc-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
            </span>
            <div class="flex-1 min-w-0">
                <h1 class="sc-hero-title">مرکز پیامک سیستم</h1>
                <p class="sc-hero-sub">
                    مدیریت کامل پیامک‌های خودکار سیستم — متن، <b>پترن پرووایدر</b> و فعال/غیرفعال‌سازی تک‌تک رویدادها.
                    همهٔ حرکات مشتری (پرداخت، پذیرش، شروع کار، تحویل، تسویه، لغو، بازگشت وجه، کیف پول و تیکت) اینجا پوشش داده می‌شوند.
                </p>
            </div>
            <div class="sc-hero-side">
                <span class="badge {{ $provider === 'log' ? 'bg-stone-100 text-stone-500 border border-stone-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                    پرووایدر فعال: {{ \App\Services\Sms\SmsManager::providers()[$provider] ?? $provider }}
                </span>
                <a href="{{ route('admin.settings.edit') }}#sms" class="btn-ghost !py-2 !px-4 !text-xs ui-press">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                    تنظیمات پرووایدر
                </a>
            </div>
        </div>

        {{-- نوار ابزار: دسته‌ها + جستجو + آمار --}}
        <div class="sc-toolbar">
            <div class="sc-cats" role="tablist" aria-label="دسته‌بندی پیامک‌ها">
                <button type="button" class="sc-cat is-active" data-cat="all">
                    همه
                    <span class="sc-cat-count" id="cnt-all">۰</span>
                </button>
                <button type="button" class="sc-cat" data-cat="auth">ورود و احراز <span class="sc-cat-count" id="cnt-auth">۰</span></button>
                <button type="button" class="sc-cat" data-cat="order">سفارش‌ها <span class="sc-cat-count" id="cnt-order">۰</span></button>
                <button type="button" class="sc-cat" data-cat="wallet">کیف پول <span class="sc-cat-count" id="cnt-wallet">۰</span></button>
                <button type="button" class="sc-cat" data-cat="support">پشتیبانی <span class="sc-cat-count" id="cnt-support">۰</span></button>
            </div>

            <div class="sc-toolbar-left">
                <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200" id="stat-active" title="پیامک‌های فعال">۰ فعال</span>
                <span class="badge bg-stone-100 text-stone-500 border border-stone-200" id="stat-pattern" title="پیامک‌های دارای پترن">۰ پترن</span>
                <div class="relative">
                    <input id="sc-search" type="search" class="field !py-2 !pl-9 !w-40 sm:!w-52" placeholder="جستجوی قالب..." aria-label="جستجوی قالب پیامک">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </div>
            </div>
        </div>
    </div>

    {{-- ================== گرید قالب‌ها ================== --}}
    <div id="sc-list" class="sc-grid">
        <div class="sc-loading"><div class="sc-spin"></div>در حال بارگذاری…</div>
    </div>

    {{-- راهنمای پترن --}}
    <div class="card ui-lift sc-help">
        <div class="sc-help-head">
            <svg class="size-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <b>پترن (قالب ثبت‌شده در پنل پیامک) چگونه کار می‌کند؟</b>
        </div>
        <p class="text-xs text-stone-500 leading-6">
            برای هر پیامک، علاوه بر متن، «کد پترن» ثبت‌شده در پنل پرووایدر را وارد کنید:
            کاوه‌نگار ← <b>نام قالب Verify Lookup</b> · فراز/ایران‌پیامک و آی‌پی‌پنل ← <b>pattern_code</b> ·
            ملی‌پیامک ← <b>شناسه متن ثابت (bodyId)</b> · ایده‌پردازان ← <b>TemplateId</b>.
            <b class="text-amber-700">ارسال پیش‌فرض پترنی است</b> — سرویس‌دهنده‌ها پیامک با متن آزاد را دیگر نمی‌پذیرند:
            اگر کد پترن پر شده باشد، ارسال از مسیر پترن پرووایدر انجام می‌شود (متغیرهای نام‌دار به‌ترتیب تعریف قالب ارسال می‌گردند).
            <b>فراز/آی‌پی‌پنل/ملی‌پیامک/ایده‌پردازان:</b> قالب بدون کد پترن یا با خطای پترن <b>ارسال نمی‌شود</b> و خطا در گزارش ثبت می‌گردد.
            <b>کاوه‌نگار:</b> در خطای پترن به‌صورت خودکار به ارسال متنی برمی‌گردد.
            اگر قالبی را <b>غیرفعال</b> کنید، آن رویداد با متن پیش‌فرض امن سیستم ارسال می‌شود.
        </p>
    </div>
</div>

{{-- ================== مودال ویرایش قالب ================== --}}
<div id="sc-modal" class="ui-modal-backdrop hidden">
    <div data-close class="absolute inset-0" aria-hidden="true"></div>
    <form id="sc-form" class="ui-modal adm-modal-text-start !max-w-2xl" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="sc-modal-title" novalidate>
        <input type="hidden" id="sc-id" value="">
        <div class="adm-modal-head">
            <h3 id="sc-modal-title" class="text-sm font-extrabold text-stone-800">ویرایش قالب پیامک</h3>
            <button type="button" class="adm-modal-x" data-close-sc aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            {{-- وضعیت + کلید --}}
            <div class="sc-edit-head">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="badge bg-stone-100 text-stone-500 border border-stone-200 font-mono text-[10px]" dir="ltr" id="sc-key-badge">—</span>
                    <span class="badge bg-sky-50 text-sky-700 border border-sky-200" id="sc-cat-badge">—</span>
                </div>
                <label class="sc-switch" for="sc-active">
                    <input type="checkbox" id="sc-active" class="peer sr-only">
                    <span class="sc-switch-track" aria-hidden="true"></span>
                    <span class="text-xs font-bold text-stone-600">فعال</span>
                </label>
            </div>

            <div>
                <label class="lbl" for="sc-title">عنوان رویداد</label>
                <input id="sc-title" class="field" maxlength="150" required>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="title"></p>
            </div>

            {{-- کد پترن پرووایدر (درخواست بازخوردی ۶-۶) --}}
            <div class="sc-pattern-box">
                <label class="lbl" for="sc-pattern">
                    <svg class="size-3.5 inline-block align-[-2px] text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>
                    کد پترن پرووایدر (الزامی برای پرووایدرهای پترن‌محور — ارسال پیش‌فرض پترنی است)
                </label>
                <input id="sc-pattern" class="field font-mono !text-xs" dir="ltr" placeholder="مثلاً: orderPaidNotify" maxlength="100" autocomplete="off">
                <p class="text-[11px] text-stone-400 mt-1.5 leading-5">
                    کد/نام قالب ثبت‌شده در پنل پیامک. نام متغیرها باید با متغیرهای پترن یکی باشد.
                    اگر خالی بماند: کاوه‌نگار با متن ارسال می‌کند؛ فراز/ایران‌پیامک ارسال نمی‌کند (خطا در گزارش).
                </p>
            </div>

            <div>
                <label class="lbl" for="sc-body">متن پیامک</label>
                <textarea id="sc-body" rows="4" class="field !leading-7" maxlength="1000" required></textarea>
                <div class="flex items-center justify-between mt-1.5">
                    <p class="err text-[11px] text-rose-500 hidden" data-for="body"></p>
                    <p class="text-[11px] text-stone-400" id="sc-chars">۰ / ۱۰۰۰</p>
                </div>
            </div>

            {{-- متغیرها --}}
            <div>
                <p class="lbl">متغیرهای قابل استفاده (کلیک = درج در متن)</p>
                <div id="sc-vars" class="sc-vars"></div>
            </div>

            {{-- پیش‌نمایش زنده --}}
            <div class="sc-preview">
                <p class="sc-preview-label">پیش‌نمایش زنده (با مقادیر نمونه):</p>
                <p class="sc-preview-text" id="sc-live-preview">—</p>
            </div>
        </div>

        <div class="adm-modal-foot">
            <button type="button" id="sc-test-btn" class="btn-ghost !py-3 !px-5">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4Z"/></svg>
                ارسال آزمایشی
            </button>
            <button type="submit" id="sc-save" class="btn-primary btn-shine !py-3 flex-1">ذخیره قالب</button>
        </div>
    </form>
</div>

{{-- ================== مودال ارسال آزمایشی ================== --}}
<div id="sc-test-modal" class="ui-modal-backdrop hidden">
    <div data-close-test class="absolute inset-0" aria-hidden="true"></div>
    <form id="sc-test-form" class="ui-modal adm-modal-sm adm-modal-text-start" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="sc-test-title" novalidate>
        <input type="hidden" id="sc-test-id" value="">
        <div class="adm-modal-head">
            <h3 class="text-sm font-extrabold text-stone-800" id="sc-test-title">تست قالب پیامک</h3>
            <button type="button" class="adm-modal-x" data-close-test aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            <div class="sc-preview">
                <p class="sc-preview-label">پیش‌نمایش با متغیرهای نمونه:</p>
                <p class="sc-preview-text" id="sc-test-preview">—</p>
            </div>

            <div>
                <label class="lbl" for="sc-test-mobile">موبایل آزمایشی</label>
                <input id="sc-test-mobile" type="tel" dir="ltr" class="field font-mono" placeholder="09123456789" inputmode="numeric" maxlength="11">
                <p class="text-[11px] text-rose-500 mt-1 hidden" id="sc-test-err"></p>
            </div>

            <label class="flex items-center gap-2.5 cursor-pointer text-xs font-bold text-stone-600">
                <input type="checkbox" id="sc-test-send" class="size-4 accent-teal-600">
                ارسال واقعی با پرووایدر فعال (در غیر این صورت فقط پیش‌نمایش)
            </label>
        </div>

        <div class="adm-modal-foot">
            <button type="submit" id="sc-test-submit" class="btn-primary btn-shine w-full !py-3">تست</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/sms-templates/index.js') }}?v=14"></script>
@endpush
