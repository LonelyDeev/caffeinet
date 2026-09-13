@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'پروفایل کارمند')
@section('page-title', 'پروفایل: '.($user->full_name ?: '—'))
@section('breadcrumb', 'پنل مدیریت کل ← کارکنان و اپراتورها ← پروفایل')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-detail.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/pages/coffeenet-show.css') }}?v=14">
<link rel="stylesheet" href="{{ asset('assets/css/pages/operator-profile.css') }}">
@endpush

@section('content')

@php
    $position = $assignment->position;

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

    $withdrawalStates = [
        'pending'  => ['در انتظار بررسی', 'amber'],
        'approved' => ['تأییدشده', 'sky'],
        'paid'     => ['پرداخت‌شده', 'emerald'],
        'rejected' => ['ردشده', 'rose'],
    ];

    $salaryTypeLabels = [
        'percent' => 'درصدی از مبلغ سفارش',
        'fixed_per_order' => 'مبلغ ثابت به ازای هر سفارش',
        'monthly' => 'ماهانه (حقوق ثابت)',
    ];
@endphp

<div class="cs-stack">

    {{-- ================== هدر ================== --}}
    <div class="dt-hero">
        <div class="dt-hero-top">
            <a href="{{ route('admin.operators.index') }}" class="dt-back" title="بازگشت به فهرست کارکنان">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                بازگشت
            </a>

            <span class="grid place-items-center size-12 rounded-2xl {{ $position->value === 'manager' ? 'bg-gradient-to-br from-amber-400/80 to-amber-700/80 text-amber-50' : 'bg-gradient-to-br from-teal-400/80 to-teal-600/80 text-teal-50' }} font-extrabold shrink-0" aria-hidden="true">
                {{ mb_substr($user->name ?? '؟', 0, 1) }}
            </span>

            <div class="cs-hero-id">
                <h1 class="dt-hero-title">{{ $user->full_name ?: '—' }}</h1>
                <p class="dt-sub">
                    {{ $position->label() }}
                    @if ($assignment->coffeenet)
                        · کافی‌نت «{{ $assignment->coffeenet->name }}»
                    @endif
                    · عضویت: {{ fa_date($user->created_at, 'Y/m/d') ?? '—' }}
                    @if ($user->last_login_at)
                        · آخرین ورود: {{ fa_date($user->last_login_at, 'Y/m/d H:i') }}
                    @endif
                </p>
            </div>

            <span class="badge {{ $assignment->is_active ? $badge['emerald'] : $badge['stone'] }}">
                {{ $assignment->is_active ? 'فعال' : 'غیرفعال' }}
            </span>

            @if ($assignment->coffeenet)
                <div class="dt-actions">
                    <a href="{{ route('admin.coffeenets.show', $assignment->coffeenet) }}" class="cs-act {{ $badge['sky'] }}" title="مشاهدهٔ صفحهٔ کافی‌نت">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M5 7V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2"/></svg>
                        صفحهٔ کافی‌نت
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- ================== کارت‌های آمار عملکرد ================== --}}
    <div class="dt-stats dt-stagger">

        <div class="dt-tile">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($stats['total_orders']) }}</div>
            <div class="dt-tile-label">مجموع سفارش‌های انجام‌شده</div>
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
            <div class="dt-tile-value">{{ fa_money($stats['sales_volume'], false) }} <small>تومان</small></div>
            <div class="dt-tile-label">ارزش سفارش‌های پرداخت‌شدهٔ او</div>
        </div>

        <div class="dt-tile" data-tone="info">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
            <div class="dt-tile-value">{{ $stats['avg_delivery_hours'] !== null ? fa_number($stats['avg_delivery_hours']).' ساعت' : '—' }}</div>
            <div class="dt-tile-label">میانگین زمان تحویل</div>
        </div>

        <div class="dt-tile">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($stats['chat_messages']) }}</div>
            <div class="dt-tile-label">پیام‌های ارسالی در گفتگوها</div>
        </div>

        <div class="dt-tile">
            <span class="dt-tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 10h8"/><path d="M8 14h8"/><rect width="18" height="18" x="3" y="3" rx="2"/></svg></span>
            <div class="dt-tile-value">{{ fa_number($stats['conversations']) }}</div>
            <div class="dt-tile-label">گفتگوهای شرکت‌کرده</div>
        </div>
    </div>

    {{-- ================== روند کاری (چارت بازه‌ای) ================== --}}
    <section class="dt-section cs-trend">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                روند کاری
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
            <div class="cs-chart-wrap"><canvas id="cs-trend-chart" height="128" role="img" aria-label="نمودار روند کاری اپراتور"></canvas></div>
        </div>
    </section>

    {{-- ================== اطلاعات فردی و کافی‌نت ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                اطلاعات فردی و محل کار
            </h2>
        </div>
        <div class="dt-info-grid">
            <div class="dt-kv">
                <span class="dt-kv-label">نام و نام خانوادگی</span>
                <span class="dt-kv-value">{{ $user->full_name ?: '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">ایمیل</span>
                <span class="dt-kv-value" dir="ltr">{{ $user->email ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">موبایل</span>
                <span class="dt-kv-value" dir="ltr">{{ $user->mobile ?: '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">سمت</span>
                <span class="dt-kv-value"><span class="dt-chip">{{ $position->label() }}</span></span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">کافی‌نت محل کار</span>
                <span class="dt-kv-value">
                    @if ($assignment->coffeenet)
                        <a href="{{ route('admin.coffeenets.show', $assignment->coffeenet) }}">{{ $assignment->coffeenet->name }}</a>
                    @else
                        —
                    @endif
                </span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">وضعیت کافی‌نت</span>
                <span class="dt-kv-value">{{ $assignment->coffeenet?->status->label() ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">استان / شهر</span>
                <span class="dt-kv-value">{{ ($assignment->coffeenet?->province?->name ?? '—').' / '.($assignment->coffeenet?->city?->name ?? '—') }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">سازمان (در صورت وجود)</span>
                <span class="dt-kv-value">{{ $assignment->coffeenet?->organization?->name ?? 'مستقل' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">تاریخ عضویت در سیستم</span>
                <span class="dt-kv-value">{{ fa_date($user->created_at, 'Y/m/d') ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">آخرین ورود</span>
                <span class="dt-kv-value">{{ fa_date($user->last_login_at, 'Y/m/d H:i') ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">افزاوده توسط</span>
                <span class="dt-kv-value">{{ $assignment->assignedBy?->full_name ?? '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">تاریخ شروع همکاری</span>
                <span class="dt-kv-value">{{ fa_date($assignment->created_at, 'Y/m/d') ?? '—' }}</span>
            </div>
        </div>
    </section>

    {{-- ================== دسترسی‌ها و مجوزها ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
                دسترسی‌ها و مجوزها
            </h2>
        </div>

        <div class="cs-cols">
            <div class="cs-col">
                <h3 class="cs-col-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    نقش‌های سیستمی
                </h3>
                @if (empty($roles))
                    <p class="text-xs text-stone-400 py-4">نقشی ثبت نشده است.</p>
                @else
                    <div class="flex flex-wrap gap-2 pt-1">
                        @foreach ($roles as $role)
                            <span class="badge {{ $badge['sky'] }}">{{ $role }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($operatorPermissions)
                    <h3 class="cs-col-title" style="margin-top: 1rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11.5V5a2 2 0 0 1 4 0v6"/><path d="M15 9.5a2 2 0 0 1 4 0V13a8 8 0 0 1-16 0v-1a2 2 0 0 1 4 0"/></svg>
                        دسترسی‌های پنل اپراتور (در همین کافی‌نت)
                    </h3>
                    <div class="flex flex-wrap gap-2 pt-1">
                        @foreach ($operatorPermissions as $perm)
                            <span class="badge {{ $badge['teal'] }}">{{ $permissionCatalog[$perm] ?? $perm }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="cs-col">
                <h3 class="cs-col-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    کاتالوگ کامل دسترسی‌های اپراتور
                </h3>
                <div class="op-perm-catalog">
                    @foreach ($permissionCatalog as $key => $label)
                        @php $has = in_array($key, $operatorPermissions, true); @endphp
                        <div class="op-perm-row {{ $has ? 'has' : '' }}">
                            <span class="op-perm-check" aria-hidden="true">{{ $has ? '✓' : '—' }}</span>
                            <span>{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-[11px] text-stone-400 mt-2 leading-5">مدیریت این دسترسی‌ها از پنل کافی‌نت (کارکنان) انجام می‌شود.</p>
            </div>
        </div>
    </section>

    {{-- ================== مالی و حقوق ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                مالی، حقوق و برداشت‌ها
            </h2>
        </div>

        <div class="dt-info-grid">
            <div class="dt-kv">
                <span class="dt-kv-label">مدل حقوق</span>
                <span class="dt-kv-value">{{ $salarySetting?->type ? ($salaryTypeLabels[$salarySetting->type->value] ?? $salarySetting->type->value) : '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">نرخ حقوق</span>
                <span class="dt-kv-value">
                    @if ($salarySetting)
                        {{ $salarySetting->type->value === 'percent' ? fa_number($salarySetting->rate).'٪' : fa_money($salarySetting->rate) }}
                    @else
                        —
                    @endif
                </span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">نرخ اضافه‌کاری</span>
                <span class="dt-kv-value">{{ $salarySetting?->overtime_rate ? fa_money($salarySetting->overtime_rate) : '—' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">مجموع پرداختی‌های ثبت‌شده</span>
                <span class="dt-kv-value">{{ fa_money($salaryTotal) }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">ارزش سفارش‌های پرداخت‌شده</span>
                <span class="dt-kv-value">{{ fa_money($stats['sales_volume']) }}</span>
            </div>
        </div>

        <div class="cs-cols">
            {{-- لاگ‌های حقوق --}}
            <div class="cs-col">
                <h3 class="cs-col-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M8 12h8"/><path d="M8 8h8"/></svg>
                    سابقهٔ پرداخت حقوق
                </h3>

                @if ($salaryLogs->isEmpty())
                    <p class="text-xs text-stone-400 py-4">هنوز پرداختی برای این کارمند ثبت نشده است.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="cs-mini">
                            <thead>
                                <tr>
                                    <th>دوره</th>
                                    <th>مبلغ</th>
                                    <th>شرح</th>
                                    <th>تاریخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($salaryLogs as $log)
                                    <tr>
                                        <td><span class="cs-num">{{ $log->period }}</span></td>
                                        <td><span class="cs-num">{{ fa_money($log->amount, false) }} <small class="text-stone-400">تومان</small></span></td>
                                        <td class="cs-desc">{{ $log->description ?? '—' }}</td>
                                        <td class="text-stone-500 whitespace-nowrap">{{ fa_date($log->created_at, 'Y/m/d') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- برداشت‌ها --}}
            <div class="cs-col">
                <h3 class="cs-col-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    برداشت‌های ثبت‌شده توسط او (از کیف کافی‌نت)
                </h3>

                @if ($withdrawals->isEmpty())
                    <p class="text-xs text-stone-400 py-4">برداشتی به نام این کارمند ثبت نشده است.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="cs-mini">
                            <thead>
                                <tr>
                                    <th>مبلغ</th>
                                    <th>وضعیت</th>
                                    <th>یادداشت</th>
                                    <th>تاریخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($withdrawals as $wd)
                                    @php [$label, $tone] = $withdrawalStates[$wd->status] ?? ['نامشخص', 'stone']; @endphp
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
                <p class="text-sm font-bold text-stone-600">هنوز سفارشی به این کارمند سپرده نشده است</p>
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

    {{-- داده‌های سرور برای اسکریپت صفحه --}}
    <div id="page-data" hidden data-payload="{{ json_encode([
        'id' => $assignment->id,
        'user_id' => $user->id,
        'coffeenet_id' => $assignment->coffeenet_id,
        'name' => $user->full_name,
    ]) }}"></div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/vendor/chart.umd.min.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/charts.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/pages/admin/operators/show.js') }}?v=14"></script>
@endpush
