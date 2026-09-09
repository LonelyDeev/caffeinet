@extends('back.operator.layouts.panel')

@section('title', 'درخواست‌های مشتری')
@section('page-title', 'درخواست‌های مشتری')
@section('breadcrumb', 'پنل اپراتور ← درخواست‌های مشتری')

@section('content')

    {{-- داده‌های سرور برای JS (بدون کد درون‌خطی) --}}
    <div id="page-data" hidden data-payload="{{ json_encode([
        'base' => '/operator/requests',
        'chatBase' => '/operator/orders',
        'timeout' => (int) $broadcastTimeout,
    ], JSON_UNESCAPED_UNICODE) }}"></div>

    {{-- سربرگ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 animate-fade-up">
        <div>
            <p class="text-sm font-extrabold text-stone-700">درخواست‌های تازهٔ مشتریان</p>
            <p class="text-[11px] text-stone-400 mt-1 leading-5">
                درخواستی که بپذیرید به شما سپرده می‌شود، گفتگو با مشتری باز می‌شود و
                مشتری پس از اتصالِ شما، پرداخت را انجام می‌دهد.
            </p>
        </div>
        <div class="no-tabs self-start" role="status" aria-label="تعداد درخواست‌های در انتظار">
            <span class="tab-btn no-tab is-active" aria-selected="true">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex size-full rounded-full bg-amber-400 opacity-60 animate-ping"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-amber-400"></span>
                </span>
                در انتظار پذیرش
                <span id="requests-count" class="no-tab-count">۰</span>
            </span>
        </div>
    </div>

    {{-- یادداشت جریان --}}
    <div class="ui-note mb-4 animate-fade-up">
        هر درخواست به‌مدت <strong>{{ fa_number($broadcastTimeout) }}</strong> ثانیه بین اپراتورها و کافی‌نت‌ها پخش می‌شود؛
        اولین که بپذیرد، درخواست را قفل می‌کند — مشتری بعد از اتصال، هزینه را پرداخت می‌کند.
    </div>

    {{-- لیست درخواست‌ها --}}
    <section class="animate-fade-up">
        <div id="requests-empty" class="ui-empty hidden">
            <span class="ui-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
            </span>
            <p class="text-sm font-extrabold text-stone-600">در انتظار درخواست جدید…</p>
            <p class="text-xs text-stone-400 mt-1.5 leading-6 max-w-md mx-auto">
                این صفحه به‌صورت زنده بروزرسانی می‌شود؛ به‌محض ثبت درخواست جدید توسط مشتری، کارت آن با شمارش معکوس همین‌جا ظاهر می‌شود.
            </p>
        </div>

        <div id="requests-list" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4"></div>
    </section>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/operator/requests/index.js') }}?v=11"></script>
@endpush
