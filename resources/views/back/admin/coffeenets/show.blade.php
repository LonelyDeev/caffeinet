@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'جزئیات کافی‌نت')
@section('page-title', 'جزئیات: '.$coffeenet->name)
@section('breadcrumb', 'پنل مدیریت کل ← کافی‌نت‌ها ← جزئیات')

@push('styles')
{{-- کیت صفحات جزئیات (dt-*) + استایل اختصاصی این صفحه — بعد از theme.css لود می‌شوند --}}
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-detail.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/pages/coffeenet-show.css') }}">
@endpush

@section('content')

@php
    $status = $coffeenet->status;

    // بج‌های وضعیت — همان کلاس‌های تیره‌آگاه سایر صفحات ادمین (theme.css زیر html.dark بازنویسی می‌کند)
    $badge = [
        'amber'   => 'bg-amber-50 text-amber-700 border border-amber-200',
        'emerald' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        'rose'    => 'bg-rose-50 text-rose-600 border border-rose-200',
        'stone'   => 'bg-stone-100 text-stone-500 border border-stone-200',
        'sky'     => 'bg-sky-50 text-sky-700 border border-sky-200',
        'teal'    => 'bg-teal-50 text-teal-700 border border-teal-200',
    ];

    // رنگ سفارش: blue→teal و orange→amber (هماهنگ با orders/index.js)
    $orderBadge = fn ($order) => $badge[match ($order->status->color()) {
        'blue' => 'teal', 'orange' => 'amber', default => $order->status->color(),
    }];

    $withdrawalStates = [
        'pending'  => ['در انتظار بررسی', 'amber'],
        'approved' => ['تأییدشده', 'sky'],
        'paid'     => ['پرداخت‌شده', 'emerald'],
        'rejected' => ['ردشده', 'rose'],
    ];
@endphp

<div class="cs-stack">

    {{-- ================== هدر (dt-hero) ================== --}}
    <div class="dt-hero">
        <div class="dt-hero-top">
            <a href="{{ route('admin.coffeenets.index') }}" class="dt-back" title="بازگشت به فهرست کافی‌نت‌ها">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                بازگشت
            </a>

            <span class="grid place-items-center size-12 rounded-2xl bg-gradient-to-br from-amber-400/80 to-amber-700/80 text-amber-50 font-extrabold shrink-0" aria-hidden="true">
                {{ mb_substr($coffeenet->name, 0, 1) }}
            </span>

            <div class="cs-hero-id">
                <h1 class="dt-hero-title">{{ $coffeenet->name }}</h1>
                <p class="dt-sub">
                    شناسهٔ {{ fa_digits($coffeenet->id) }}
                    · ثبت: {{ fa_date($coffeenet->created_at, 'Y/m/d') ?? '—' }}
                    @if ($coffeenet->organization)
                        · زیرمجموعهٔ سازمان «{{ $coffeenet->organization->name }}»
                    @else
                        · مستقل (بدون سازمان)
                    @endif
                </p>
            </div>

            <span class="badge {{ $badge[$status->color()] }}">{{ $status->label() }}</span>

            {{-- عملیات وضعیت — فقط موارد منطقی برای وضعیت فعلی --}}
            <div class="dt-actions">
                @if ($status->value !== 'approved')
                    <button type="button" id="act-approve" class="cs-act {{ $badge['emerald'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        {{ $status->value === 'suspended' ? 'رفع تعلیق' : 'تأیید کافی‌نت' }}
                    </button>
                @endif
                @if ($status->value !== 'suspended')
                    <button type="button" id="act-suspend" class="cs-act {{ $badge['amber'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                        تعلیق
                    </button>
                @endif
                @if ($status->value !== 'rejected')
                    <button type="button" id="act-reject" class="cs-act {{ $badge['rose'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        رد
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- ================== روند کاری (درخواست بازخوردی ۶-۲) ================== --}}
    <section class="dt-section cs-trend">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                روند کاری کافی‌نت
            </h2>

            {{-- انتخاب بازهٔ زمانی --}}
            <div class="cs-trend-range" role="group" aria-label="بازهٔ زمانی روند">
                <button type="button" class="cs-range-btn is-active" data-range="daily">روزانه (۳۰ روز)</button>
                <button type="button" class="cs-range-btn" data-range="weekly">هفتگی (۱۲ هفته)</button>
                <button type="button" class="cs-range-btn" data-range="monthly">ماهانه (۱۲ ماه)</button>
                <button type="button" class="cs-range-btn" data-range="yearly">سالانه</button>
                <button type="button" class="cs-range-btn" data-range="custom">بازهٔ تاریخ</button>
            </div>
        </div>

        {{-- بازهٔ دلخواه --}}
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
            <span class="text-[11px] text-stone-400">سفارش ثبت‌شده در بازه: <b id="cs-sum-orders" class="text-stone-600">۰</b> · تحویل‌شده: <b id="cs-sum-done" class="text-stone-600">۰</b> · گردش: <b id="cs-sum-volume" class="text-stone-600">۰ تومان</b></span>
        </div>

        <div class="cs-trend-body">
            <div class="cs-chart-wrap"><canvas id="cs-trend-chart" height="128" role="img" aria-label="نمودار روند سفارش‌های کافی‌نت"></canvas></div>
        </div>
    </section>

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
            <div class="dt-tile-label">سفارش‌های تکمیل‌شده</div>
        </div>

        <div class="dt-tile" data-tone="ok">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg></span>
            <div class="dt-tile-value">{{ fa_money($stats['total_sales'], false) }} <small>تومان</small></div>
            <div class="dt-tile-label">فروش کل (سفارش‌های مؤثر)</div>
        </div>

        <div class="dt-tile">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($activeManagers + $activeOperators) }}</div>
            <div class="dt-tile-label">کارکنان فعال ({{ fa_number($activeManagers) }} مدیر / {{ fa_number($activeOperators) }} اپراتور)</div>
        </div>

        <div class="dt-tile" data-tone="info">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg></span>
            <div class="dt-tile-value">{{ fa_money($stats['balance'], false) }} <small>تومان</small></div>
            <div class="dt-tile-label">موجودی کیف پول</div>
        </div>

        <div class="dt-tile" @if ($stats['pending_withdrawals'] > 0) data-tone="err" @endif>
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($stats['pending_withdrawals']) }}</div>
            <div class="dt-tile-label">برداشت در انتظار بررسی</div>
        </div>

        <div class="dt-tile">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($stats['conversations']) }}</div>
            <div class="dt-tile-label">گفتگوهای سفارش‌ها</div>
        </div>

        <div class="dt-tile">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($stats['broadcasts']) }}</div>
            <div class="dt-tile-label">پخش‌های دریافتی سفارش</div>
        </div>
    </div>

    {{-- ================== اطلاعات کافی‌نت ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                اطلاعات کافی‌نت
            </h2>
        </div>
        <div class="dt-info-grid">
            <div class="dt-kv">
                <span class="dt-kv-label">نام</span>
                <span class="dt-kv-value">{{ $coffeenet->name }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">تلفن</span>
                <span class="dt-kv-value" dir="ltr">{{ $coffeenet->phone ?: '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">وضعیت</span>
                <span class="dt-kv-value"><span class="dt-chip">{{ $status->label() }}</span></span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">سازمان</span>
                <span class="dt-kv-value">
                    @if ($coffeenet->organization)
                        <a href="{{ route('admin.organizations.show', $coffeenet->organization) }}">{{ $coffeenet->organization->name }}</a>
                    @else
                        مستقل (بدون سازمان)
                    @endif
                </span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">استان</span>
                <span class="dt-kv-value">{{ $coffeenet->province?->name ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">شهر</span>
                <span class="dt-kv-value">{{ $coffeenet->city?->name ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">نشانی</span>
                <span class="dt-kv-value">{{ $coffeenet->address ?: '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">تاریخ ثبت</span>
                <span class="dt-kv-value">{{ fa_date($coffeenet->created_at) ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">تأییدکننده</span>
                <span class="dt-kv-value">{{ $coffeenet->approvedBy?->full_name ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">تاریخ تأیید</span>
                <span class="dt-kv-value">{{ fa_date($coffeenet->approved_at) ?? '—' }}</span>
            </div>
        </div>
    </section>

    {{-- ================== کارکنان ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                کارکنان
                <span class="badge bg-stone-100 text-stone-500 border border-stone-200">{{ fa_number($staff->count()) }} نفر</span>
            </h2>
            <a href="{{ route('admin.operators.index') }}" class="dt-section-more">همهٔ کارکنان</a>
        </div>

        @if ($staff->isEmpty())
            <div class="ui-empty cs-empty">
                <span class="ui-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <p class="text-sm font-bold text-stone-600">هنوز کارمندی ثبت نشده است</p>
                <p class="text-xs text-stone-400">مدیر و اپراتورهای این کافی‌نت اینجا نمایش داده می‌شوند</p>
            </div>
        @else
            <div class="table-wrap">
                <div class="overflow-x-auto">
                    <table class="table-panel table-modern">
                        <thead>
                            <tr>
                                <th>نام و نام خانوادگی</th>
                                <th>موبایل</th>
                                <th>سمت</th>
                                <th>وضعیت</th>
                                <th>تاریخ عضویت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staff as $member)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="grid place-items-center size-9 rounded-xl bg-stone-100 text-stone-500 font-bold text-xs shrink-0">{{ mb_substr($member->user?->name ?? '؟', 0, 1) }}</span>
                                            <div class="min-w-0">
                                                <p class="font-bold text-stone-800">{{ $member->user?->full_name ?? '—' }}</p>
                                                <p class="text-xs text-stone-400" dir="ltr">{{ $member->user?->email ?? '—' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-stone-600 font-semibold" dir="ltr">{{ $member->user?->mobile ?: '—' }}</td>
                                    <td>
                                        @if ($member->position->value === 'manager')
                                            <span class="badge {{ $badge['amber'] }}">مدیر</span>
                                        @else
                                            <span class="badge {{ $badge['teal'] }}">اپراتور</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($member->is_active)
                                            <span class="badge {{ $badge['emerald'] }}">فعال</span>
                                        @else
                                            <span class="badge {{ $badge['stone'] }}">غیرفعال</span>
                                        @endif
                                    </td>
                                    <td class="text-stone-500">{{ fa_date($member->created_at, 'Y/m/d') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    {{-- ================== سفارش‌های اخیر ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                سفارش‌های اخیر
                <span class="badge bg-stone-100 text-stone-500 border border-stone-200">{{ fa_number($stats['total_orders']) }} سفارش</span>
            </h2>
            <a href="{{ route('admin.orders.index') }}" class="dt-section-more">مشاهدهٔ همه</a>
        </div>

        @if ($recentOrders->isEmpty())
            <div class="ui-empty cs-empty">
                <span class="ui-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                </span>
                <p class="text-sm font-bold text-stone-600">هنوز سفارشی برای این کافی‌نت ثبت نشده است</p>
            </div>
        @else
            <div class="table-wrap">
                <div class="overflow-x-auto">
                    <table class="table-panel table-modern">
                        <thead>
                            <tr>
                                <th>شماره</th>
                                <th>خدمت</th>
                                <th>مشتری</th>
                                <th>اپراتور</th>
                                <th>وضعیت</th>
                                <th>مبلغ</th>
                                <th>تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentOrders as $order)
                                <tr>
                                    <td><span class="cs-num cs-ltr" dir="ltr">{{ $order->order_number }}</span></td>
                                    <td class="font-semibold text-stone-700">{{ $order->service?->name ?? '—' }}</td>
                                    <td class="text-stone-600">{{ $order->customer?->full_name ?? '—' }}</td>
                                    <td class="text-stone-600">{{ $order->operator?->full_name ?? '—' }}</td>
                                    <td><span class="badge {{ $orderBadge($order) }} whitespace-nowrap">{{ $order->status->label() }}</span></td>
                                    <td><span class="cs-num">{{ fa_money($order->price + $order->expenses, false) }} <small class="text-stone-400">تومان</small></span></td>
                                    <td class="text-stone-500 whitespace-nowrap">{{ fa_date($order->created_at, 'Y/m/d') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    {{-- ================== مالی و کیف پول ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                مالی و کیف پول
            </h2>
            <a href="{{ route('admin.withdrawals.index') }}" class="dt-section-more">مدیریت برداشت‌ها</a>
        </div>

        <div class="dt-info-grid">
            <div class="dt-kv">
                <span class="dt-kv-label">موجودی فعلی کیف پول</span>
                <span class="dt-kv-value">{{ fa_money($stats['balance']) }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">برداشت در انتظار بررسی</span>
                <span class="dt-kv-value">{{ fa_number($stats['pending_withdrawals']) }} درخواست</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">مجموع فروش (سفارش‌های مؤثر)</span>
                <span class="dt-kv-value">{{ fa_money($stats['total_sales']) }}</span>
            </div>
        </div>

        <div class="cs-cols">
            {{-- آخرین تراکنش‌های کیف پول --}}
            <div class="cs-col">
                <h3 class="cs-col-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
                    آخرین تراکنش‌های کیف پول
                </h3>

                @if ($transactions->isEmpty())
                    <p class="text-xs text-stone-400 py-4">هنوز تراکنشی برای کیف پول این کافی‌نت ثبت نشده است.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="cs-mini">
                            <thead>
                                <tr>
                                    <th>نوع</th>
                                    <th>مبلغ</th>
                                    <th>شرح</th>
                                    <th>موجودی پس از</th>
                                    <th>تاریخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transactions as $tx)
                                    <tr>
                                        <td>
                                            @if ($tx->type->value === 'credit')
                                                <span class="badge {{ $badge['emerald'] }} whitespace-nowrap">واریز</span>
                                            @else
                                                <span class="badge {{ $badge['rose'] }} whitespace-nowrap">برداشت</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($tx->type->value === 'credit')
                                                <span class="cs-num text-emerald-700">+{{ fa_money($tx->amount, false) }}</span>
                                            @else
                                                <span class="cs-num text-rose-600">−{{ fa_money($tx->amount, false) }}</span>
                                            @endif
                                        </td>
                                        <td class="cs-desc">{{ $tx->description ?? '—' }}</td>
                                        <td><span class="cs-num">{{ fa_money($tx->balance_after, false) }}</span></td>
                                        <td class="text-stone-500 whitespace-nowrap">{{ fa_date($tx->created_at, 'Y/m/d H:i') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- برداشت‌های اخیر --}}
            <div class="cs-col">
                <h3 class="cs-col-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    برداشت‌های اخیر
                </h3>

                @if ($withdrawals->isEmpty())
                    <p class="text-xs text-stone-400 py-4">هنوز درخواست برداشتی ثبت نشده است.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="cs-mini">
                            <thead>
                                <tr>
                                    <th>مبلغ</th>
                                    <th>وضعیت</th>
                                    <th>یادداشت</th>
                                    <th>تاریخ درخواست</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($withdrawals as $wd)
                                    @php
                                        [$label, $tone] = $withdrawalStates[$wd->status] ?? ['نامشخص', 'stone'];
                                    @endphp
                                    <tr>
                                        <td><span class="cs-num">{{ fa_money($wd->amount, false) }} <small class="text-stone-400">تومان</small></span></td>
                                        <td><span class="badge {{ $badge[$tone] }} whitespace-nowrap">{{ $label }}</span></td>
                                        <td class="cs-desc">{{ $wd->note ?? '—' }}</td>
                                        <td class="text-stone-500 whitespace-nowrap">{{ fa_date($wd->created_at, 'Y/m/d') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ================== پخش‌های دریافتی ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"/></svg>
                پخش‌های دریافتی سفارش
                <span class="badge bg-stone-100 text-stone-500 border border-stone-200">{{ fa_number($stats['broadcasts']) }} مورد</span>
            </h2>
        </div>

        @if ($recentBroadcasts->isEmpty())
            <div class="ui-empty cs-empty">
                <span class="ui-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"/></svg>
                </span>
                <p class="text-sm font-bold text-stone-600">هیچ سفارشی به این کافی‌نت پخش نشده است</p>
            </div>
        @else
            <div class="table-wrap">
                <div class="overflow-x-auto">
                    <table class="table-panel table-modern">
                        <thead>
                            <tr>
                                <th>شمارهٔ سفارش</th>
                                <th>خدمت</th>
                                <th>مشاهده</th>
                                <th>تاریخ ارسال</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentBroadcasts as $broadcast)
                                <tr>
                                    <td><span class="cs-num cs-ltr" dir="ltr">{{ $broadcast->order?->order_number ?? '—' }}</span></td>
                                    <td class="font-semibold text-stone-700">{{ $broadcast->order?->service?->name ?? '—' }}</td>
                                    <td>
                                        @if ($broadcast->seen_at)
                                            <span class="badge {{ $badge['emerald'] }}">دیده‌شده</span>
                                        @else
                                            <span class="badge {{ $badge['amber'] }}">دیده‌نشده</span>
                                        @endif
                                    </td>
                                    <td class="text-stone-500 whitespace-nowrap">{{ fa_date($broadcast->sent_at, 'Y/m/d H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    {{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
    <div id="page-data" hidden data-payload="{{ json_encode([
        'id' => $coffeenet->id,
        'name' => $coffeenet->name,
        'status' => $status->value,
        'reward' => $rewardPreview,
    ]) }}"></div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/vendor/chart.umd.min.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/charts.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/pages/admin/coffeenets/show.js') }}?v=14"></script>
@endpush
