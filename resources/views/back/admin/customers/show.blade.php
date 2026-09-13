@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'پروفایل مشتری')
@section('page-title', 'مشتری: '.($customer->full_name ?: '—'))
@section('breadcrumb', 'پنل مدیریت کل ← مشتریان ← پروفایل')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-detail.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/pages/coffeenet-show.css') }}?v=14">
<link rel="stylesheet" href="{{ asset('assets/css/pages/operator-profile.css') }}">
@endpush

@section('content')

@php
    $badge = [
        'amber'   => 'bg-amber-50 text-amber-700 border border-amber-200',
        'emerald' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        'rose'    => 'bg-rose-50 text-rose-600 border border-rose-200',
        'stone'   => 'bg-stone-100 text-stone-500 border border-stone-200',
        'sky'     => 'bg-sky-50 text-sky-700 border border-sky-200',
        'teal'    => 'bg-teal-50 text-teal-700 border border-teal-200',
    ];

    $orderBadge = fn ($order) => $badge[match ($order->status->color()) {
        'blue' => 'teal', 'orange' => 'amber', default => $order->status->color(),
    }];

    $txBadge = fn ($t) => $t->type->value === 'credit' ? $badge['emerald'] : $badge['rose'];
    $txSign = fn ($t) => $t->type->value === 'credit' ? '+' : '−';

    $paymentStates = [
        'pending' => ['در انتظار', 'amber'],
        'success' => ['موفق', 'emerald'],
        'failed' => ['ناموفق', 'rose'],
        'cancelled' => ['لغوشده', 'stone'],
        'refunded' => ['بازگشت وجه', 'sky'],
    ];

    $eventStates = [
        'login' => ['ورود', 'emerald'],
        'logout' => ['خروج', 'stone'],
        'forced_logout' => ['خروج اجباری', 'rose'],
    ];
@endphp

<div class="cs-stack">

    {{-- ================== هدر ================== --}}
    <div class="dt-hero">
        <div class="dt-hero-top">
            <a href="{{ route('admin.customers.index') }}" class="dt-back" title="بازگشت به فهرست مشتریان">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                بازگشت
            </a>

            <span class="grid place-items-center size-12 rounded-2xl {{ $customer->is_active ? 'bg-gradient-to-br from-amber-400/80 to-amber-700/80 text-amber-50' : 'bg-gradient-to-br from-stone-300 to-stone-500 text-stone-50' }} font-extrabold shrink-0" aria-hidden="true">
                {{ mb_substr($customer->name ?? '؟', 0, 1) }}
            </span>

            <div class="cs-hero-id">
                <h1 class="dt-hero-title">{{ $customer->full_name ?: '—' }}</h1>
                <p class="dt-sub">
                    مشتری اپ · عضویت: {{ fa_date($customer->created_at, 'Y/m/d') ?? '—' }}
                    @if ($customer->last_login_at)
                        · آخرین ورود: {{ fa_date($customer->last_login_at, 'Y/m/d H:i') }}
                    @endif
                    @if ($stats['avg_rating'] !== null)
                        · میانگین امتیاز: {{ fa_number($stats['avg_rating']) }}/{{ fa_number(5) }}
                    @endif
                </p>
            </div>

            <span class="badge {{ $customer->is_active ? $badge['emerald'] : $badge['rose'] }}">
                {{ $customer->is_active ? 'فعال' : 'مسدود' }}
            </span>

            <div class="dt-actions">
                <a href="{{ route('admin.orders.index') }}" class="cs-act {{ $badge['sky'] }}" title="سفارش‌های سامانه">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    سفارش‌ها
                </a>
                @foreach ($recentOrders->take(1) as $firstOrder)
                    <a href="{{ route('admin.orders.show', $firstOrder) }}" class="cs-act {{ $badge['teal'] }}" title="آخرین سفارش او">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
                        آخرین سفارش
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ================== کارت‌های آمار ================== --}}
    <div class="dt-stats dt-stagger">

        <div class="dt-tile">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($stats['total_orders']) }}</div>
            <div class="dt-tile-label">مجموع سفارش‌ها</div>
        </div>

        <div class="dt-tile" data-tone="info">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($stats['active_orders']) }}</div>
            <div class="dt-tile-label">سفارش‌های در جریان</div>
        </div>

        <div class="dt-tile" data-tone="ok">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($stats['done_orders']) }}</div>
            <div class="dt-tile-label">تحویل‌شده / تکمیل‌شده</div>
        </div>

        <div class="dt-tile" @if ($stats['cancelled_orders'] > 0) data-tone="err" @endif>
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($stats['cancelled_orders']) }}</div>
            <div class="dt-tile-label">لغو / مرجوعی</div>
        </div>

        <div class="dt-tile" data-tone="ok">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg></span>
            <div class="dt-tile-value">{{ fa_money($stats['paid_volume'], false) }} <small>تومان</small></div>
            <div class="dt-tile-label">ارزش سفارش‌های پرداخت‌شده</div>
        </div>

        <div class="dt-tile" data-tone="info">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
            <div class="dt-tile-value">{{ fa_money((float) ($customer->wallet?->balance ?? 0), false) }} <small>تومان</small></div>
            <div class="dt-tile-label">موجودی کیف پول</div>
        </div>

        <div class="dt-tile" data-tone="info">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
            <div class="dt-tile-value">{{ $stats['avg_delivery_hours'] !== null ? fa_number($stats['avg_delivery_hours']).' ساعت' : '—' }}</div>
            <div class="dt-tile-label">میانگین زمان تحویل سفارش‌هایش</div>
        </div>

        <div class="dt-tile">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 17.27 6.18 21l1.64-6.81L2 9.64l6.91-.6L12 2.5l3.09 6.54 6.91.6-5.82 4.55L19.82 21z"/></svg></span>
            <div class="dt-tile-value">
                @if ($stats['avg_rating'] !== null)
                    {{ fa_number($stats['avg_rating']) }} <small>/ {{ fa_number(5) }}</small>
                @else
                    —
                @endif
            </div>
            <div class="dt-tile-label">امتیاز نظرسنجی ({{ fa_number($stats['ratings_count']) }} نظر)</div>
        </div>
    </div>

    {{-- ================== نمودار روند سفارش مشتری ================== --}}
    <section class="dt-section cs-trend">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                نمودار سفارش‌های او
            </h2>

            <div class="cs-trend-range" role="group" aria-label="بازهٔ زمانی روند">
                <button type="button" class="cs-range-btn is-active" data-range="daily">روزانه (۳۰ روز)</button>
                <button type="button" class="cs-range-btn" data-range="weekly">هفتگی (۱۲ هفته)</button>
                <button type="button" class="cs-range-btn" data-range="monthly">ماهانه (۱۲ ماه)</button>
                <button type="button" class="cs-range-btn" data-range="yearly">سالانه</button>
                <button type="button" class="cs-range-btn" data-range="custom">بازهٔ تاریخ</button>
            </div>
        </div>

        <div id="cs-custom-range" class="cs-custom-range hidden">
            <label class="cs-cr-field">
                <span>از تاریخ (شمسی)</span>
                <input type="text" id="cs-from" dir="ltr" class="field !py-2 !text-center" placeholder="۱۴۰۵/۰۶/۰۱"
                       data-jdp data-jdp-mode="gregorian" title="برای انتخاب تاریخ کلیک کنید">
                <input type="hidden" id="cs-from-g">
            </label>
            <label class="cs-cr-field">
                <span>تا تاریخ (شمسی)</span>
                <input type="text" id="cs-to" dir="ltr" class="field !py-2 !text-center" placeholder="۱۴۰۵/۰۶/۳۱"
                       data-jdp data-jdp-mode="gregorian" title="برای انتخاب تاریخ کلیک کنید">
                <input type="hidden" id="cs-to-g">
            </label>
            <button type="button" id="cs-apply" class="btn-primary !py-2 !px-5 !text-xs ui-press">اعمال بازه</button>
        </div>

        <div class="cs-trend-meta">
            <span class="badge bg-amber-50 text-amber-700 border border-amber-200" id="cs-range-label">—</span>
            <span class="text-[11px] text-stone-400">سفارش در بازه: <b id="cs-sum-orders" class="text-stone-600">۰</b> · تحویل‌شده: <b id="cs-sum-done" class="text-stone-600">۰</b> · ارزش: <b id="cs-sum-volume" class="text-stone-600">۰ تومان</b></span>
        </div>

        <div class="cs-trend-body">
            <div class="cs-chart-wrap"><canvas id="cs-trend-chart" height="128" role="img" aria-label="نمودار روند سفارش‌های مشتری"></canvas></div>
        </div>
    </section>

    {{-- ================== اطلاعات فردی ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                اطلاعات فردی
            </h2>
            <div class="flex items-center gap-2">
                <button type="button" id="btn-edit" class="btn-primary btn-shine !py-2 !px-4 !text-xs ui-press">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                        ویرایش اطلاعات
                    </span>
                </button>
                @if ($customer->is_active)
                    <button type="button" id="btn-ban" class="btn-ghost ui-press !py-2 !px-4 !text-xs !text-rose-600 hover:!bg-rose-50">
                        مسدودسازی (بن)
                    </button>
                @else
                    <button type="button" id="btn-unban" class="btn-ghost ui-press !py-2 !px-4 !text-xs !text-emerald-600 hover:!bg-emerald-50">
                        رفع مسدودی
                    </button>
                @endif
            </div>
        </div>
        <div class="dt-info-grid">
            <div class="dt-kv">
                <span class="dt-kv-label">نام و نام خانوادگی</span>
                <span class="dt-kv-value">{{ $customer->full_name ?: '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">موبایل (ورود)</span>
                <span class="dt-kv-value" dir="ltr">{{ $customer->mobile ?: '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">ایمیل</span>
                <span class="dt-kv-value" dir="ltr">{{ $customer->email ?: '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">جنسیت</span>
                <span class="dt-kv-value">{{ $customer->gender?->label() ?? 'نامشخص' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">تاریخ تولد (شمسی)</span>
                <span class="dt-kv-value">{{ $customer->birthdate ? fa_date($customer->birthdate, 'Y/m/d') : '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">استان / شهر</span>
                <span class="dt-kv-value">{{ ($customer->province?->name ?? '—').' / '.($customer->city?->name ?? '—') }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">وضعیت پروفایل</span>
                <span class="dt-kv-value">
                    <span class="badge {{ $customer->profile_completed ? $badge['teal'] : $badge['amber'] }}">
                        {{ $customer->profile_completed ? 'کامل (اجازهٔ ثبت سفارش)' : 'ناقص (ثبت سفارش مسدود)' }}
                    </span>
                </span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">وضعیت حساب</span>
                <span class="dt-kv-value">
                    <span class="badge {{ $customer->is_active ? $badge['emerald'] : $badge['rose'] }}">
                        {{ $customer->is_active ? 'فعال' : 'مسدود' }}
                    </span>
                </span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">موبایل تأییدشده</span>
                <span class="dt-kv-value">{{ fa_date($customer->mobile_verified_at, 'Y/m/d H:i') ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">تاریخ عضویت</span>
                <span class="dt-kv-value">{{ fa_date($customer->created_at, 'Y/m/d') ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">آخرین ورود</span>
                <span class="dt-kv-value">{{ fa_date($customer->last_login_at, 'Y/m/d H:i') ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">مجموع ورودها / تیکت‌ها</span>
                <span class="dt-kv-value">{{ fa_number($stats['total_logins']) }} ورود · {{ fa_number($stats['tickets_count']) }} تیکت</span>
            </div>
        </div>
    </section>

    {{-- ================== کیف پول و تراکنش‌ها ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                کیف پول و تراکنش‌ها
            </h2>
            <button type="button" id="btn-wallet" class="dt-section-more">تنظیم دستی موجودی</button>
        </div>

        <div class="dt-info-grid">
            <div class="dt-kv">
                <span class="dt-kv-label">موجودی فعلی</span>
                <span class="dt-kv-value">{{ fa_money((float) ($customer->wallet?->balance ?? 0)) }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">مجموع گردش موفق درگاه (سفارش + شارژ کیف)</span>
                <span class="dt-kv-value">{{ fa_money($stats['gateway_volume']) }}</span>
            </div>
        </div>

        <h3 class="cs-col-title" style="margin-top:1rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M8 12h8"/><path d="M8 8h8"/></svg>
            گردش حساب کیف پول
        </h3>

        @if ($transactions->isEmpty())
            <p class="text-xs text-stone-400 py-4">هنوز تراکنشی برای این کیف پول ثبت نشده است.</p>
        @else
            <div class="overflow-x-auto">
                <table class="cs-mini">
                    <thead>
                        <tr>
                            <th>نوع</th>
                            <th>مبلغ</th>
                            <th>موجودی پس از</th>
                            <th>شرح</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $t)
                            <tr>
                                <td><span class="badge {{ $txBadge($t) }} whitespace-nowrap">{{ $t->type->label() }}</span></td>
                                <td><span class="cs-num {{ $t->type->value === 'credit' ? 'text-emerald-600' : 'text-rose-600' }}">{{ $txSign($t) }} {{ fa_money($t->amount, false) }} <small class="text-stone-400">تومان</small></span></td>
                                <td><span class="cs-num">{{ fa_money($t->balance_after, false) }}</span></td>
                                <td class="cs-desc">{{ $t->description ?? '—' }}</td>
                                <td class="text-stone-500 whitespace-nowrap">{{ fa_date($t->created_at, 'Y/m/d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ================== پرداخت‌های درگاه ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M7 15h4"/></svg>
                تراکنش‌های درگاه پرداخت
            </h2>
        </div>

        @if ($payments->isEmpty())
            <p class="text-xs text-stone-400 py-4">پرداختی از درگاه برای این مشتری ثبت نشده است.</p>
        @else
            <div class="overflow-x-auto">
                <table class="cs-mini">
                    <thead>
                        <tr>
                            <th>نوع</th>
                            <th>سفارش</th>
                            <th>مبلغ</th>
                            <th>درگاه</th>
                            <th>وضعیت</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $p)
                            @php [$pLabel, $pTone] = $paymentStates[$p->status->value] ?? ['نامشخص', 'stone']; @endphp
                            <tr>
                                <td><span class="badge {{ $p->isForWallet() ? $badge['sky'] : $badge['teal'] }} whitespace-nowrap">{{ $p->isForWallet() ? 'شارژ کیف' : 'پرداخت سفارش' }}</span></td>
                                <td>
                                    @if ($p->order)
                                        <a href="{{ route('admin.orders.show', $p->order) }}"><span class="cs-num cs-ltr" dir="ltr">{{ $p->order->order_number }}</span></a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><span class="cs-num">{{ fa_money($p->amount, false) }} <small class="text-stone-400">تومان</small></span></td>
                                <td class="text-stone-500" dir="ltr">{{ $p->driver ?? '—' }}</td>
                                <td><span class="badge {{ $badge[$pTone] }} whitespace-nowrap">{{ $pLabel }}</span></td>
                                <td class="text-stone-500 whitespace-nowrap">{{ fa_date($p->created_at, 'Y/m/d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ================== سفارش‌های اخیر ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                سفارش‌های اخیر او
                <span class="badge bg-stone-100 text-stone-500 border border-stone-200">{{ fa_number($stats['total_orders']) }} سفارش</span>
            </h2>
            <a href="{{ route('admin.orders.index') }}" class="dt-section-more">مشاهدهٔ همه</a>
        </div>

        @if ($recentOrders->isEmpty())
            <div class="ui-empty cs-empty">
                <span class="ui-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                </span>
                <p class="text-sm font-bold text-stone-600">این مشتری هنوز سفارشی ثبت نکرده است</p>
            </div>
        @else
            <div class="table-wrap">
                <div class="overflow-x-auto">
                    <table class="table-panel table-modern">
                        <thead>
                            <tr>
                                <th>شماره</th>
                                <th>خدمت</th>
                                <th>کافی‌نت</th>
                                <th>وضعیت</th>
                                <th>مبلغ</th>
                                <th>تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentOrders as $order)
                                <tr class="ops-row" data-href="{{ route('admin.orders.show', $order) }}">
                                    <td><span class="cs-num cs-ltr" dir="ltr">{{ $order->order_number }}</span></td>
                                    <td class="font-semibold text-stone-700">{{ $order->service?->name ?? '—' }}</td>
                                    <td class="text-stone-600">{{ $order->coffeenet?->name ?? '—' }}</td>
                                    <td><span class="badge {{ $orderBadge($order) }} whitespace-nowrap">{{ $order->status->label() }}</span></td>
                                    <td><span class="cs-num">{{ fa_money($order->price, false) }} <small class="text-stone-400">تومان</small></span></td>
                                    <td class="text-stone-500 whitespace-nowrap">{{ fa_date($order->created_at, 'Y/m/d') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    {{-- ================== نظرسنجی‌های او ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 17.27 6.18 21l1.64-6.81L2 9.64l6.91-.6L12 2.5l3.09 6.54 6.91.6-5.82 4.55L19.82 21z"/></svg>
                نظرسنجی‌های او (پس از اتمام سفارش)
            </h2>
        </div>

        @if ($recentRatings->isEmpty())
            <p class="text-xs text-stone-400 py-4">این مشتری هنوز نظری ثبت نکرده است.</p>
        @else
            <div class="overflow-x-auto">
                <table class="cs-mini">
                    <thead>
                        <tr>
                            <th>سفارش</th>
                            <th>خدمت</th>
                            <th>امتیاز</th>
                            <th>نظر</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentRatings as $rating)
                            <tr>
                                <td><span class="cs-num cs-ltr" dir="ltr">{{ $rating->order?->order_number ?? '—' }}</span></td>
                                <td class="text-stone-600">{{ $rating->order?->service?->name ?? '—' }}</td>
                                <td><span class="text-amber-500 tracking-wider" aria-label="{{ $rating->rating }} از ۵">{{ str_repeat('★', $rating->rating) }}<span class="text-stone-300">{{ str_repeat('★', 5 - $rating->rating) }}</span></span></td>
                                <td class="cs-desc">{{ $rating->comment ?? '—' }}</td>
                                <td class="text-stone-500 whitespace-nowrap">{{ fa_date($rating->rated_at, 'Y/m/d') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="cs-cols">
        {{-- ================== تاریخچهٔ ورود/خروج ================== --}}
        <div class="cs-col dt-section">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                تاریخچهٔ ورود و خروج
            </h2>

            @if ($loginLogs->isEmpty())
                <p class="text-xs text-stone-400 py-4">رخدادی ثبت نشده است (ورودهای قدیمی‌تر از این قابلیت لاگ نمی‌شوند).</p>
            @else
                <div class="overflow-x-auto">
                    <table class="cs-mini">
                        <thead>
                            <tr>
                                <th>رخداد</th>
                                <th>IP</th>
                                <th>مرورگر</th>
                                <th>زمان</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($loginLogs as $log)
                                @php [$eLabel, $eTone] = $eventStates[$log->event] ?? [$log->event, 'stone']; @endphp
                                <tr>
                                    <td><span class="badge {{ $badge[$eTone] }} whitespace-nowrap">{{ $eLabel }}</span></td>
                                    <td class="cs-num cs-ltr" dir="ltr">{{ $log->ip ?? '—' }}</td>
                                    <td class="cs-desc" title="{{ $log->user_agent }}">{{ \Illuminate\Support\Str::limit($log->user_agent, 34) }}</td>
                                    <td class="text-stone-500 whitespace-nowrap">{{ fa_date($log->created_at, 'Y/m/d H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ================== لاگ تغییرات مدیریت ================== --}}
        <div class="cs-col dt-section">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                تاریخچهٔ تغییرات مدیریتی
            </h2>

            @if ($auditLogs->isEmpty())
                <p class="text-xs text-stone-400 py-4">تغییری توسط مدیریت روی این مشتری ثبت نشده است.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="cs-mini">
                        <thead>
                            <tr>
                                <th>اقدام</th>
                                <th>شرح</th>
                                <th>تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($auditLogs as $log)
                                <tr>
                                    <td><span class="badge {{ $badge['sky'] }} whitespace-nowrap font-mono" dir="ltr">{{ $log->action }}</span></td>
                                    <td class="cs-desc">{{ $log->description ?? '—' }}</td>
                                    <td class="text-stone-500 whitespace-nowrap">{{ fa_date($log->created_at, 'Y/m/d H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- داده‌های سرور برای اسکریپت صفحه --}}
<div id="page-data" hidden data-payload="{{ json_encode([
    'id' => $customer->id,
    'name' => $customer->full_name,
    'mobile' => $customer->mobile,
    'is_active' => (bool) $customer->is_active,
    'customer' => [
        'id' => $customer->id,
        'name' => $customer->name,
        'family' => $customer->family,
        'email' => $customer->email,
        'mobile' => $customer->mobile,
        'gender' => $customer->gender?->value,
        'province_id' => $customer->province_id,
        'city_id' => $customer->city_id,
        'birthdate_fa' => $customer->birthdate ? fa_date($customer->birthdate, 'Y/m/d') : null,
        'profile_completed' => (bool) $customer->profile_completed,
        'is_active' => (bool) $customer->is_active,
    ],
    'geoCitiesUrl' => route('admin.geo.cities'),
], JSON_UNESCAPED_UNICODE) }}"></div>

{{-- ================== مودال ویرایش کامل مشتری (همان فهرست) ================== --}}
<div id="edit-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="edit-title">
    <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

    <form id="edit-form" class="ui-modal adm-modal-lg adm-modal-text-start" data-tone="info" role="document" novalidate>
        <div class="adm-modal-head">
            <div>
                <h3 id="edit-title" class="text-sm font-extrabold text-stone-800">ویرایش اطلاعات مشتری</h3>
                <p id="edit-subtitle" class="text-[11px] text-stone-400 mt-0.5">—</p>
            </div>
            <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            <div class="ui-note" data-tone="info">
                موبایل، شمارهٔ ورود مشتری به اپ است؛ پس از تغییر، ورود با شمارهٔ جدید انجام می‌شود.
                همهٔ مشخصات (جغرافیا، تاریخ تولد شمسی، وضعیت پروفایل و حساب) قابل ویرایش است.
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="lbl" for="f-name">نام <span class="text-rose-500">*</span></label>
                    <input id="f-name" class="field" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
                </div>
                <div>
                    <label class="lbl" for="f-family">نام خانوادگی</label>
                    <input id="f-family" class="field" autocomplete="off">
                </div>
                <div>
                    <label class="lbl" for="f-mobile">موبایل (شمارهٔ ورود) <span class="text-rose-500">*</span></label>
                    <input id="f-mobile" type="tel" dir="ltr" class="field" placeholder="09xxxxxxxxx" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="mobile"></p>
                </div>
                <div>
                    <label class="lbl" for="f-email">ایمیل</label>
                    <input id="f-email" type="email" dir="ltr" class="field" autocomplete="off">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="email"></p>
                </div>
                <div>
                    <label class="lbl" for="f-gender">جنسیت</label>
                    <select id="f-gender" class="field">
                        <option value="">نامشخص</option>
                        <option value="male">مرد</option>
                        <option value="female">زن</option>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="f-birthdate">تاریخ تولد (شمسی)</label>
                    <input id="f-birthdate" type="text" dir="ltr" class="field num !text-center" placeholder="۱۳۷۰/۰۵/۱۲"
                           data-jdp data-jdp-min-years-ago="100" data-jdp-max-years-ago="10" title="برای انتخاب تاریخ کلیک کنید">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="birthdate"></p>
                </div>
                <div>
                    <label class="lbl" for="f-province">استان</label>
                    <select id="f-province" class="field">
                        <option value="">انتخاب استان…</option>
                        @foreach (\App\Models\Province::query()->orderBy('sort')->orderBy('name')->get(['id', 'name']) as $province)
                            <option value="{{ $province->id }}">{{ $province->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="lbl" for="f-city">شهر</label>
                    <select id="f-city" class="field">
                        <option value="">ابتدا استان را انتخاب کنید…</option>
                    </select>
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="city_id"></p>
                </div>
            </div>

            <hr class="border-stone-100">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                <div class="flex items-end gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input id="f-profile-completed" type="checkbox" class="size-4 accent-amber-600">
                        <span class="text-xs font-bold text-stone-700">پروفایل کامل</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input id="f-is-active" type="checkbox" class="size-4 accent-amber-600">
                        <span class="text-xs font-bold text-stone-700">حساب فعال</span>
                    </label>
                </div>
                <div>
                    <label class="lbl" for="f-password">رمز عبور جدید</label>
                    <input id="f-password" type="password" dir="ltr" class="field" placeholder="(خالی = بدون تغییر)" autocomplete="new-password">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="password"></p>
                </div>
            </div>

            <p id="edit-error" class="field-error hidden"></p>
        </div>

        <div class="adm-modal-foot">
            <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
            <button type="submit" id="edit-save" class="btn-primary btn-shine !py-2.5 !text-xs">ذخیرهٔ تغییرات</button>
        </div>
    </form>
</div>

{{-- ================== مودال مسدودسازی ================== --}}
<div id="ban-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="ban-title">
    <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

    <form id="ban-form" class="ui-modal adm-modal-md adm-modal-text-start" data-tone="danger" role="document" novalidate>
        <div class="adm-modal-head">
            <div>
                <h3 id="ban-title" class="text-sm font-extrabold text-stone-800">مسدودسازی مشتری</h3>
                <p id="ban-subtitle" class="text-[11px] text-stone-400 mt-0.5">—</p>
            </div>
            <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body">
            <div class="ui-note" data-tone="danger">
                مشتری مسدودشده <strong>نمی‌تواند وارد اپ شود</strong> (ورود با کد تأیید رد می‌شود) و
                همهٔ جلسه‌های فعال او فوراً بسته می‌شوند. سفارش‌ها و تاریخچه‌اش حفظ می‌شود.
            </div>

            <div class="mt-4">
                <label class="lbl" for="ban-reason">دلیل مسدودسازی <span class="text-rose-500">*</span></label>
                <textarea id="ban-reason" rows="3" maxlength="490" class="field !text-xs w-full"
                          placeholder="مثلاً: تخلف در ثبت سفارش / فیش جعلی / رفتار نامناسب با اپراتور…"></textarea>
                <p id="ban-error" class="field-error mt-2 hidden">حداقل ۳ حرف لازم است.</p>
            </div>
        </div>

        <div class="adm-modal-foot">
            <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
            <button type="submit" id="ban-save" class="ui-btn-danger !py-2.5 !text-xs">مسدودسازی حساب</button>
        </div>
    </form>
</div>

{{-- ================== مودال تنظیم دستی کیف پول ================== --}}
<div id="wallet-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="wallet-title">
    <div data-close-modal class="absolute inset-0" aria-hidden="true"></div>

    <form id="wallet-form" class="ui-modal adm-modal-md adm-modal-text-start" data-tone="info" role="document" novalidate>
        <div class="adm-modal-head">
            <div>
                <h3 id="wallet-title" class="text-sm font-extrabold text-stone-800">تنظیم دستی کیف پول</h3>
                <p id="wallet-subtitle" class="text-[11px] text-stone-400 mt-0.5">موجودی فعلی: {{ fa_money((float) ($customer->wallet?->balance ?? 0)) }}</p>
            </div>
            <button type="button" class="adm-modal-x" data-close-modal aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body">
            <div class="ui-note" data-tone="info">
                مبلغ <strong>مثبت</strong> = افزایش موجودی (بستانکار)، مبلغ <strong>منفی</strong> = کاهش موجودی (بدهکار).
                تراکنش در گردش حساب مشتری ثبت و به او پیامک می‌شود.
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3">
                <div>
                    <label class="lbl" for="w-amount">مبلغ (تومان) <span class="text-rose-500">*</span></label>
                    <input id="w-amount" type="number" step="any" dir="ltr" class="field" placeholder="مثلاً ۵۰۰۰۰ یا -۵۰۰۰۰">
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="amount"></p>
                </div>
                <div>
                    <label class="lbl" for="w-desc">شرح تراکنش <span class="text-rose-500">*</span></label>
                    <textarea id="w-desc" rows="2" maxlength="490" class="field !text-xs w-full"
                              placeholder="مثلاً: جبران غرامت سفارش CN050615-XXXX / اصلاح شارژ اشتباه…"></textarea>
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="description"></p>
                </div>
            </div>

            <p id="wallet-error" class="field-error mt-2 hidden"></p>
        </div>

        <div class="adm-modal-foot">
            <button type="button" class="btn-ghost ui-press !py-2.5 !text-xs" data-close-modal>انصراف</button>
            <button type="submit" id="wallet-save" class="btn-primary btn-shine !py-2.5 !text-xs">ثبت تراکنش</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/vendor/chart.umd.min.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/charts.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/pages/admin/customers/show.js') }}?v=1"></script>
@endpush
