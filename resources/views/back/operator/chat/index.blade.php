@extends($chatLayout ?? 'back.operator.layouts.panel')

@section('title', 'گفتگوها')
@section('page-title', 'گفتگوها')
@section('breadcrumb', ($chatPanelLabel ?? 'پنل اپراتور').' ← گفتگوها')

@section('content')

    {{-- داده‌های سرور برای JS (بدون کد درون‌خطی) --}}
    <div id="page-data" hidden data-payload="{{ json_encode([
        'base' => $chatBase ?? '/operator/chat',
        'showPattern' => ($chatBase ?? '/operator/chat').'/../orders/{id}/chat',
    ], JSON_UNESCAPED_UNICODE) }}"></div>

    {{-- سربرگ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 animate-fade-up">
        <div>
            <p class="text-sm font-extrabold text-stone-700">گفتگوهای «{{ $coffeenet->name ?? 'همهٔ کافی‌نت‌ها' }}»</p>
            <p class="text-[11px] text-stone-400 mt-1 leading-5">
                گفتگوی زنده با مشتریان — پیام‌های جدید هر چند ثانیه خودکار به‌روز می‌شوند.
            </p>
        </div>
        <span class="hidden sm:inline-flex items-center gap-2 op-status-pill" title="به‌روزرسانی خودکار">
            <span class="relative flex size-2"><span class="absolute inline-flex size-full rounded-full bg-emerald-400 opacity-60 animate-ping"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span></span>
            به‌روزرسانی زنده
        </span>
    </div>

    {{-- فیلترها --}}
    <div class="card animate-fade-up !p-0 mb-4">
        <div class="px-5 py-4 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input id="chat-search" type="text" placeholder="جستجوی شماره سفارش یا نام مشتری…"
                       class="field op-pill-input !py-2.5 !text-xs w-full pl-10" autocomplete="off">
            </div>
            <div class="flex items-center gap-1.5" id="chat-filters" role="tablist" aria-label="فیلتر گفتگوها">
                <button type="button" class="chat-filter-btn btn-ghost !py-2.5 !px-4 !text-xs" data-filter="active" aria-pressed="true">در جریان</button>
                <button type="button" class="chat-filter-btn btn-ghost !py-2.5 !px-4 !text-xs" data-filter="done" aria-pressed="false">پایان‌یافته</button>
                <button type="button" class="chat-filter-btn btn-ghost !py-2.5 !px-4 !text-xs" data-filter="all" aria-pressed="false">همه</button>
            </div>
        </div>
    </div>

    {{-- فهرست --}}
    <div class="card animate-fade-up !p-0 overflow-hidden">
        <div id="chat-list" class="divide-y divide-stone-100">
            <div class="px-6 py-12 text-center text-stone-400 text-xs">در حال بارگذاری…</div>
        </div>

        <div class="px-5 py-3 border-t border-stone-100 flex items-center justify-between gap-3">
            <p class="text-[11px] text-stone-400" id="chat-summary">—</p>
            <div class="flex items-center gap-1.5" id="chat-pagination"></div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/operator/chat/index.js') }}"></script>
@endpush
