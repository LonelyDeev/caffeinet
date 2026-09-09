@extends('back.layouts.org', ['organization' => $organization])

@section('title', 'داشبورد')
@section('page-title', 'داشبورد سازمان')
@section('breadcrumb', 'پنل سازمان ← داشبورد')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/analytics.css') }}?v=9">
@endpush

@section('content')

    {{-- دادهٔ نمودارها برای JS (فاز ۹) --}}
    <div id="page-data" hidden data-payload="{{ json_encode($chartData) }}"></div>

    {{-- کارت‌های آماری --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        @php
            $cards = [
                ['label' => 'موجودی کیف پول', 'value' => fa_money($stats['wallet_balance']), 'hint' => 'پاداش معرفی: '.fa_money($stats['total_rewards']), 'icon' => 'wallet', 'tone' => 'teal', 'orb' => 'emerald'],
                ['label' => 'کافی‌نت‌های فعال', 'value' => fa_number($stats['coffeenets_approved']), 'hint' => fa_number($stats['coffeenets_pending']).' در انتظار تأیید', 'icon' => 'store', 'tone' => 'ok', 'orb' => 'emerald'],
                ['label' => 'مجموع کافی‌نت‌ها', 'value' => fa_number($stats['coffeenets_total']), 'hint' => 'معرفی‌شده توسط شما', 'icon' => 'building', 'tone' => 'amber', 'orb' => 'amber'],
                ['label' => 'برداشت موفق', 'value' => fa_money($stats['withdrawals_total']), 'hint' => fa_number($stats['withdrawals_pending']).' درخواست در جریان', 'icon' => 'withdraw', 'tone' => 'sky', 'orb' => 'teal'],
            ];
        @endphp

        @foreach ($cards as $i => $card)
            <div class="card ui-lift p-5 animate-fade-up delay-{{ $i + 1 }} overflow-hidden relative">
                <span class="ui-orb" data-tone="{{ $card['orb'] }}" data-pos="tr" aria-hidden="true"></span>
                <div class="flex items-start justify-between relative">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-stone-500">{{ $card['label'] }}</p>
                        <p class="mt-2 {{ $i === 1 || $i === 2 ? 'text-3xl' : 'text-2xl' }} font-extrabold tabular-nums text-stone-800 truncate">{{ $card['value'] }}</p>
                        <p class="mt-1.5 text-[11px] {{ $card['tone'] === 'amber' ? 'text-amber-600' : ($card['tone'] === 'teal' ? 'text-teal-600' : ($card['tone'] === 'ok' ? 'text-emerald-600' : 'text-sky-600')) }}">{{ $card['hint'] }}</p>
                    </div>
                    <span class="ui-chip" data-tone="{{ $card['tone'] }}">
                        @if ($card['icon'] === 'wallet')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                        @elseif ($card['icon'] === 'store')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/></svg>
                        @elseif ($card['icon'] === 'building')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="4" height="16" x="4" y="2" rx="1"/><rect width="4" height="16" x="10" y="2" rx="1"/><path d="M20 4v14"/><path d="M2 20h20"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                        @endif
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- نمای تحلیلی ۳۰ روز اخیر (فاز ۹) --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-5">

        <section class="card ui-lift an-card animate-fade-up delay-3 xl:col-span-2">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                        </span>
                        واریزی کیف پول سازمان
                    </h2>
                    <p class="an-card-sub">کمیسیون و پاداش — ۳۰ روز اخیر شمسی</p>
                </div>
                <div class="an-card-tools">
                    <a href="{{ route('org.wallet.index') }}" class="an-export">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                        گردش کیف
                    </a>
                </div>
            </div>
            <div class="an-chart">
                <canvas id="org-credits" role="img" aria-label="نمودار واریزی کیف پول سازمان"></canvas>
            </div>
        </section>

        <section class="card ui-lift an-card animate-fade-up delay-4">
            <div class="an-card-head">
                <div>
                    <h2 class="an-card-title">
                        <span class="an-title-chip" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/></svg>
                        </span>
                        سفارش کافی‌نت‌های من
                    </h2>
                    <p class="an-card-sub">۳۰ روز اخیر — زیرمجموعهٔ شما</p>
                </div>
            </div>
            <div class="an-chart">
                <canvas id="org-coffeenets" role="img" aria-label="نمودار سفارش کافی‌نت‌های سازمان"></canvas>
            </div>
        </section>
    </div>

    <div class="mt-6 grid lg:grid-cols-3 gap-5">

        {{-- آخرین کافی‌نت‌های من --}}
        <div class="lg:col-span-2 card ui-lift animate-fade-up delay-3 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-stone-100">
                <h2 class="no-card-title text-sm font-extrabold text-stone-700">آخرین کافی‌نت‌های معرفی‌شده</h2>
                <a href="{{ route('org.coffeenets.index') }}" class="text-xs font-bold text-teal-600 hover:text-teal-700">مدیریت کافی‌نت‌ها →</a>
            </div>
            <ul class="divide-y divide-stone-100 max-h-96 overflow-y-auto ui-stagger">
                @forelse ($recentCoffeenets as $net)
                    <li class="px-5 py-3.5 flex items-center gap-3.5 hover:bg-stone-50/60 transition-colors">
                        <span class="grid place-items-center size-9 rounded-xl bg-gradient-to-br from-teal-400/70 to-teal-600/70 text-white font-bold text-xs shrink-0">
                            {{ mb_substr($net->name, 0, 1) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-stone-700 truncate">{{ $net->name }}</p>
                            <p class="text-[11px] text-stone-400 mt-0.5">{{ $net->province?->name ?? '—' }} · ثبت {{ jdate($net->created_at)->format('Y/m/d') }}</p>
                        </div>
                        @if ($net->status === \App\Enums\CoffeenetStatus::Approved)
                            <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0">تأییدشده</span>
                        @elseif ($net->status === \App\Enums\CoffeenetStatus::Pending)
                            <span class="badge bg-amber-50 text-amber-700 border border-amber-200 shrink-0">در انتظار</span>
                        @else
                            <span class="badge bg-rose-50 text-rose-600 border border-rose-200 shrink-0">{{ $net->status->label() }}</span>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-10">
                        <div class="ui-empty">
                            <span class="ui-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/></svg>
                            </span>
                            <p class="text-sm font-semibold text-stone-500">هنوز کافی‌نتی معرفی نکرده‌اید.</p>
                            <a href="{{ route('org.coffeenets.index') }}" class="btn-primary btn-shine !py-2 !px-4 !text-xs mt-3">معرفی اولین کافی‌نت</a>
                        </div>
                    </li>
                @endforelse
            </ul>
        </div>

        {{-- راهنمای سریع --}}
        <div class="card ui-lift p-5 animate-fade-up delay-4 space-y-4 overflow-hidden relative">
            <span class="ui-orb" data-tone="emerald" data-pos="bl" aria-hidden="true"></span>
            <h2 class="no-card-title text-sm font-extrabold text-stone-700 relative">چطور کار می‌کند؟</h2>

            <div class="space-y-3.5 relative">
                @php
                    $steps = [
                        ['معرفی کافی‌نت', 'مشخصات کافی‌نت جدید را ثبت کنید'],
                        ['تأیید مدیریت کل', 'کارشناسان ما آن را بررسی و تأیید می‌کنند'],
                        ['دریافت پاداش', 'پاداش معرفی به کیف پول شما واریز می‌شود'],
                        ['درآمد پایدار', 'از فعالیت هر کافی‌نت سهم دائمی دارید'],
                    ];
                @endphp

                @foreach ($steps as $j => $step)
                    <div class="flex items-start gap-3">
                        <span class="grid place-items-center size-8 rounded-xl bg-teal-50 text-teal-600 text-xs font-extrabold shrink-0">{{ $j + 1 }}</span>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-stone-700">{{ $step[0] }}</p>
                            <p class="text-[11px] text-stone-400 leading-5 mt-0.5">{{ $step[1] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('org.coffeenets.index') }}" class="btn-primary btn-shine no-btn-teal w-full !py-3 relative">معرفی کافی‌نت جدید</a>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/vendor/chart.umd.min.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/charts.js') }}?v=9"></script>
<script src="{{ asset('back/assets/js/pages/org/dashboard.js') }}?v=9"></script>
@endpush
