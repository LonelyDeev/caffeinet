@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'جزئیات سازمان')
@section('page-title', 'جزئیات سازمان')
@section('breadcrumb', 'پنل مدیریت کل ← سازمان‌ها ← جزئیات')

@push('styles')
    {{-- توکن‌های تم (فاز ۱۰) — مصرف‌کننده، اگر layout قبلاً لینک کرده باشد لینک تکراری بی‌ضرر است --}}
    <link rel="stylesheet" href="{{ asset('assets/css/pages/admin-detail.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pages/org-operators.css') }}">
@endpush

@section('content')

@php
    $statusBadges = [
        'pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
        'approved' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        'rejected' => 'bg-rose-50 text-rose-600 border border-rose-200',
        'suspended' => 'bg-stone-100 text-stone-500 border border-stone-200',
    ];
    $withdrawalBadges = [
        'pending' => ['بررسی', 'bg-amber-50 text-amber-700 border border-amber-200'],
        'approved' => ['تأییدشده', 'bg-sky-50 text-sky-700 border border-sky-200'],
        'paid' => ['پرداخت‌شده', 'bg-emerald-50 text-emerald-700 border border-emerald-200'],
        'rejected' => ['ردشده', 'bg-rose-50 text-rose-600 border border-rose-200'],
    ];
@endphp

<div class="space-y-5">

    {{-- ================== هرو ================== --}}
    <section class="dt-hero">
        <div class="dt-hero-row">
            <span class="grid place-items-center size-12 rounded-2xl bg-gradient-to-br from-teal-400/80 to-teal-600/80 text-white font-extrabold text-lg shrink-0" aria-hidden="true">
                {{ mb_substr($organization->name, 0, 1) }}
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="dt-hero-title">{{ $organization->name }}</h1>
                    <span class="dt-chip">{{ $organization->status->label() }}</span>
                </div>
                <p class="dt-sub">
                    {{ $organization->type === 'legal' ? 'سازمان حقوقی' : 'سازمان حقیقی' }}
                    @if ($organization->city || $organization->province)
                        · {{ trim(($organization->city?->name ?? '').(($organization->city && $organization->province) ? '، ' : '').($organization->province?->name ?? '')) }}
                    @endif
                    · ثبت: {{ fa_date($organization->created_at, 'Y/m/d') ?? '—' }}
                </p>
            </div>

            <div class="dt-actions">
                @if ($organization->status->value !== 'approved')
                    <button type="button" class="dt-action" data-tone="ok" data-status="approved">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        تأیید
                    </button>
                @endif
                @if ($organization->status->value !== 'suspended')
                    <button type="button" class="dt-action" data-tone="warn" data-status="suspended">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                        تعلیق
                    </button>
                @endif
                @if ($organization->status->value !== 'rejected')
                    <button type="button" class="dt-action" data-tone="err" data-status="rejected">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        رد
                    </button>
                @endif
            </div>
        </div>

        <a href="{{ route('admin.organizations.index') }}" class="dt-back">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            بازگشت به فهرست سازمان‌ها
        </a>
    </section>

    {{-- ================== کارت‌های آماری ================== --}}
    <div class="dt-stats dt-stagger">

        <div class="dt-tile">
            <span class="dt-tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M5 7V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2"/></svg>
            </span>
            <p class="dt-tile-value">{{ fa_number($coffeenets->count()) }} <small>شعبه</small></p>
            <p class="dt-tile-label">کافی‌نت‌ها · {{ fa_number($coffeenets->where('status', 'approved')->count()) }} تأییدشده</p>
        </div>

        <div class="dt-tile" data-tone="info">
            <span class="dt-tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <p class="dt-tile-value">{{ fa_number($staffTotal) }} <small>نفر</small></p>
            <p class="dt-tile-label">کارکنان · {{ fa_number($staffManagers) }} مدیر · {{ fa_number($staffOperators) }} اپراتور</p>
        </div>

        <div class="dt-tile">
            <span class="dt-tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
            </span>
            <p class="dt-tile-value">{{ fa_number($ordersTotal) }} <small>سفارش</small></p>
            <p class="dt-tile-label">سفارش‌های شعبه‌ها · {{ fa_number($ordersValid) }} غیرلغو</p>
        </div>

        <div class="dt-tile" data-tone="ok">
            <span class="dt-tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8c-2.2 0-4 1.8-4 4s1.8 4 4 4 4-1.8 4-4-1.8-4-4-4Z"/><path d="M19.5 12c0-.5 0-1-.1-1.4l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2.4-1.4L14.2 2.6h-4l-.4 2.6c-.9.3-1.7.8-2.4 1.4l-2.4-1-2 3.4 2 1.6c-.1.4-.1.9-.1 1.4s0 1 .1 1.4l-2 1.6 2 3.4 2.4-1c.7.6 1.5 1.1 2.4 1.4l.4 2.6h4l.4-2.6a7 7 0 0 0 2.4-1.4l2.4 1 2-3.4-2-1.6c.1-.4.1-.9.1-1.4Z"/></svg>
            </span>
            <p class="dt-tile-value">{{ fa_money($salesTotal, false) }} <small>تومان</small></p>
            <p class="dt-tile-label">فروش کل (بدون لغو/بازگشت/در انتظار پرداخت)</p>
        </div>

        <div class="dt-tile" data-tone="info">
            <span class="dt-tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
            </span>
            <p class="dt-tile-value">{{ fa_money($balance, false) }} <small>تومان</small></p>
            <p class="dt-tile-label">موجودی کیف پول سازمان</p>
        </div>

        <div class="dt-tile" data-tone="{{ $pendingWithdrawals->count() > 0 ? 'err' : 'ok' }}">
            <span class="dt-tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
            </span>
            <p class="dt-tile-value">{{ fa_number($pendingWithdrawals->count()) }} <small>درخواست</small></p>
            <p class="dt-tile-label">
                برداشت در انتظار
                @if ($pendingWithdrawals->sum('amount') > 0)
                    · {{ fa_money($pendingWithdrawals->sum('amount')) }}
                @endif
            </p>
        </div>
    </div>

    {{-- ================== اطلاعات کلیدی ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                اطلاعات سازمان
            </h2>
        </div>
        <div class="dt-info-grid">
            <div class="dt-kv">
                <span class="dt-kv-label">نام سازمان</span>
                <span class="dt-kv-value">{{ $organization->name }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">وضعیت</span>
                <span class="dt-kv-value"><span class="badge {{ $statusBadges[$organization->status->value] ?? '' }}">{{ $organization->status->label() }}</span></span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">مالک / مدیر سازمان</span>
                <span class="dt-kv-value">
                    {{ $organization->owner?->full_name ?? '—' }}
                    @if ($organization->owner?->mobile)
                        · <span dir="ltr">{{ fa_digits($organization->owner->mobile) }}</span>
                    @endif
                </span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">استان / شهرستان</span>
                <span class="dt-kv-value">{{ ($organization->province?->name ?? '—').($organization->city ? '، '.$organization->city->name : '') }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">نوع سازمان</span>
                <span class="dt-kv-value">{{ $organization->type === 'legal' ? 'حقوقی' : 'حقیقی' }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">شناسه ملی / تلفن</span>
                <span class="dt-kv-value" dir="ltr">{{ fa_digits($organization->national_id ?: ($organization->phone ?: '—')) }}</span>
            </div>
            <div class="dt-kv">
                <span class="dt-kv-label">تاریخ ثبت</span>
                <span class="dt-kv-value">{{ fa_date($organization->created_at, 'Y/m/d H:i') ?? '—' }}</span>
            </div>
        </div>
    </section>

    {{-- ================== کافی‌نت‌های سازمان ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M5 7V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2"/></svg>
                کافی‌نت‌های سازمان
            </h2>
            <a href="{{ route('admin.coffeenets.index') }}" class="dt-section-more">
                مشاهدهٔ همهٔ کافی‌نت‌ها
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            </a>
        </div>

        @if ($coffeenets->isEmpty())
            <div class="p-6">
                <div class="ui-empty">
                    <span class="ui-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M5 7V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2"/></svg>
                    </span>
                    <p>هنوز کافی‌نتی برای این سازمان ثبت نشده است.</p>
                </div>
            </div>
        @else
            <div class="table-wrap">
                <div class="overflow-x-auto">
                    <table class="table-panel table-modern">
                        <thead>
                            <tr>
                                <th>کافی‌نت</th>
                                <th>موقعیت</th>
                                <th>وضعیت</th>
                                <th class="text-center">کارکنان</th>
                                <th class="text-center">سفارش‌ها</th>
                                <th>فروش</th>
                                <th>ثبت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($coffeenets as $net)
                                @php
                                    $stats = $validStats->get($net->id);
                                    $netOrders = (int) ($allOrdersCount[$net->id] ?? 0);
                                    $netSales = (float) ($stats->sales_sum ?? 0);
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.coffeenets.show', $net->id) }}" class="dt-net-link">
                                            <span class="grid place-items-center size-9 rounded-xl bg-gradient-to-br from-rose-400/70 to-rose-600/70 text-white font-bold text-xs shrink-0" aria-hidden="true">{{ mb_substr($net->name, 0, 1) }}</span>
                                            <span class="min-w-0">
                                                <span class="block font-bold">{{ $net->name }}</span>
                                                <span class="block text-[11px] text-stone-400">{{ fa_number($net->managers_count) }} مدیر · {{ fa_number($net->staff_count) }} کارمند</span>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="text-stone-500">{{ ($net->city?->name ? $net->city->name.'، ' : '').($net->province?->name ?? '—') }}</td>
                                    <td><span class="badge {{ $statusBadges[$net->status->value] ?? '' }}">{{ $net->status->label() }}</span></td>
                                    <td class="text-center font-bold tabular-nums text-stone-700">{{ fa_number($net->staff_count) }}</td>
                                    <td class="text-center font-bold tabular-nums text-stone-700">{{ fa_number($netOrders) }}</td>
                                    <td class="font-bold tabular-nums text-stone-700">{{ fa_money($netSales, false) }} <span class="text-[10px] font-normal text-stone-400">تومان</span></td>
                                    <td class="text-stone-500 text-xs">{{ fa_date($net->created_at, 'Y/m/d') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    {{-- ================== مالی ================== --}}
    <section class="dt-section">
        <div class="dt-section-head">
            <h2 class="dt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                مالی سازمان
            </h2>
        </div>

        <div class="p-5 space-y-5">
            {{-- بنر موجودی --}}
            <div class="fin-balance">
                <span class="fin-balance-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                </span>
                <div>
                    <p class="fin-balance-value">{{ fa_money($balance) }}</p>
                    <p class="fin-balance-label">موجودی فعلی کیف پول · {{ fa_number($transactions->count()) }} تراکنش اخیر</p>
                </div>
            </div>

            <div class="fin-grid">
                {{-- آخرین تراکنش‌ها --}}
                <div class="fin-col">
                    <h3 class="fin-col-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v8"/><path d="M8 12h8"/><circle cx="12" cy="12" r="10"/></svg>
                        آخرین تراکنش‌ها
                    </h3>
                    @if ($transactions->isEmpty())
                        <div class="ui-empty">
                            <span class="ui-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v8"/><path d="M8 12h8"/><circle cx="12" cy="12" r="10"/></svg>
                            </span>
                            <p>تراکنشی ثبت نشده است.</p>
                        </div>
                    @else
                        <div class="table-wrap">
                            <div class="overflow-x-auto">
                                <table class="table-panel table-modern">
                                    <thead>
                                        <tr>
                                            <th>نوع</th>
                                            <th>مبلغ</th>
                                            <th>مانده پس از</th>
                                            <th>شرح</th>
                                            <th>تاریخ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($transactions as $tx)
                                            <tr>
                                                <td><span class="badge {{ $tx->type->value === 'credit' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' }}">{{ $tx->type->label() }}</span></td>
                                                <td class="font-bold tabular-nums">{{ fa_money($tx->amount, false) }}</td>
                                                <td class="tabular-nums text-stone-500">{{ fa_money($tx->balance_after, false) }}</td>
                                                <td class="text-stone-500 text-xs">{{ $tx->description ?? '—' }}</td>
                                                <td class="text-stone-500 text-xs">{{ fa_date($tx->created_at, 'Y/m/d H:i') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- برداشت‌های اخیر --}}
                <div class="fin-col">
                    <h3 class="fin-col-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                        برداشت‌های اخیر
                    </h3>
                    @if ($withdrawals->isEmpty())
                        <div class="ui-empty">
                            <span class="ui-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                            </span>
                            <p>درخواست برداشتی ثبت نشده است.</p>
                        </div>
                    @else
                        <div class="table-wrap">
                            <div class="overflow-x-auto">
                                <table class="table-panel table-modern">
                                    <thead>
                                        <tr>
                                            <th>مبلغ</th>
                                            <th>وضعیت</th>
                                            <th>درخواست‌دهنده</th>
                                            <th>تاریخ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($withdrawals as $wd)
                                            <tr>
                                                <td class="font-bold tabular-nums">{{ fa_money($wd->amount, false) }}</td>
                                                <td><span class="badge {{ ($withdrawalBadges[$wd->status] ?? ['—', 'bg-stone-100 text-stone-500 border border-stone-200'])[1] }}">{{ ($withdrawalBadges[$wd->status] ?? ['—'])[0] }}</span></td>
                                                <td class="text-stone-500">{{ $wd->requester?->full_name ?? '—' }}</td>
                                                <td class="text-stone-500 text-xs">{{ fa_date($wd->created_at, 'Y/m/d H:i') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>

{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode([
    'id' => $organization->id,
    'status' => $organization->status->value,
]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/organizations/show.js') }}"></script>
@endpush
