@extends($guideLayout ?? 'back.layouts.panel')

@section('title', 'راهنمای پنل')
@section('page-title', 'آموزش استفاده از پنل')
@section('breadcrumb', ($guidePanelLabel ?? 'پنل').' ← راهنمای پنل')

@push('styles')
<style>
.guide-hero{background:linear-gradient(135deg,rgba(245,158,11,.10),rgba(217,119,6,.04));border:1px solid rgba(245,158,11,.25)}
.guide-card{transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
.guide-card:hover{transform:translateY(-4px);box-shadow:0 18px 40px -18px rgba(120,53,15,.35);border-color:rgba(245,158,11,.55)}
.guide-card:hover .guide-arrow{transform:translateX(-6px)}
.guide-arrow{transition:transform .25s ease}
.guide-ico{background:linear-gradient(135deg,#f59e0b,#b45309)}
.guide-read{outline:2px solid rgba(16,185,129,.4);outline-offset:-2px}
</style>
@endpush

@section('content')

<section class="card ui-lift animate-fade-up guide-hero overflow-hidden">
    <div class="p-6 sm:p-8 flex flex-wrap items-center gap-5">
        <span class="grid place-items-center size-16 rounded-2xl guide-ico text-white shadow-lg shadow-amber-900/30 shrink-0" aria-hidden="true">
            <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
        </span>
        <div class="flex-1 min-w-56">
            <h1 class="text-lg font-extrabold text-stone-800">آموزش استفاده از پنل</h1>
            <p class="text-xs text-stone-500 mt-1.5 leading-6">
                راهنمای گام‌به‌گام مخصوص نقش <b class="text-amber-700">{{ $guideRoleLabel }}</b> —
                {{ $guides->count() }} راهنما برای همهٔ بخش‌های پنل شما. هر مطلب را بخوانید و با دکمهٔ «خواندم» پیشرفت‌تان را ثبت کنید.
            </p>
        </div>
        <div class="flex flex-col items-center gap-1.5 shrink-0">
            <span class="text-3xl font-black text-amber-600 tabular-nums" id="guide-progress-num">۰٪</span>
            <span class="text-[10px] text-stone-400 font-bold">پیشرفت مطالعه</span>
        </div>
    </div>

    {{-- نوار پیشرفت مطالعه (localStorage) --}}
    <div class="h-1.5 bg-stone-200/70" role="progressbar" aria-label="پیشرفت مطالعه راهنماها" aria-valuemin="0" aria-valuemax="100">
        <div id="guide-progress-bar" class="h-full bg-gradient-to-l from-amber-400 to-amber-600 transition-all duration-500" style="width:0%"></div>
    </div>
</section>

@if ($guides->isEmpty())
    <section class="card ui-lift p-10 text-center">
        <p class="text-sm text-stone-400">راهنمایی برای نقش شما ثبت نشده است.</p>
    </section>
@else
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-4" aria-label="فهرست راهنماها">
        @foreach ($guides as $guide)
            <a href="{{ $guideBase }}/{{ $guide->slug }}" data-slug="{{ $guide->slug }}"
               class="guide-card card ui-lift p-5 flex flex-col gap-4 border border-stone-100 relative"
               title="خواندن راهنمای «{{ $guide->title }}»">
                {{-- نشان خوانده‌شده --}}
                <span class="guide-read-dot hidden absolute top-3 left-3 size-2.5 rounded-full bg-emerald-500" data-read="{{ $guide->slug }}" aria-hidden="true"></span>

                <div class="flex items-start gap-3.5">
                    <span class="grid place-items-center size-11 rounded-2xl guide-ico/90 text-white shrink-0" style="background:linear-gradient(135deg,#f59e0b,#b45309)" aria-hidden="true">
                        @php($ico = $guide->icon)
                        @if ($ico === 'rocket')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>
                        @elseif ($ico === 'building')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/></svg>
                        @elseif ($ico === 'layers')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.35a2 2 0 0 0-1.66 0L2.6 7.5a1 1 0 0 0 0 1.8l8.58 5.15a2 2 0 0 0 1.66 0l8.58-5.15a1 1 0 0 0 0-1.8Z"/><path d="m6.08 10.62-3.5 2.1a1 1 0 0 0 0 1.8l8.58 5.15a2 2 0 0 0 1.66 0l8.58-5.15a1 1 0 0 0 0-1.8l-3.5-2.1"/></svg>
                        @elseif ($ico === 'orders')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
                        @elseif ($ico === 'headset')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg>
                        @elseif ($ico === 'coins')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
                        @elseif ($ico === 'settings')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                        @elseif ($ico === 'wallet')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                        @elseif ($ico === 'users')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        @elseif ($ico === 'chat')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/></svg>
                        @else
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-extrabold text-stone-800 leading-6">{{ $guide->title }}</h2>
                        <p class="text-[11px] text-stone-400 leading-5 mt-1">{{ $guide->description }}</p>
                    </div>
                </div>

                <div class="mt-auto flex items-center justify-between border-t border-stone-100 pt-3">
                    <div class="flex items-center gap-3 text-[10px] text-stone-400 font-bold">
                        <span class="inline-flex items-center gap-1">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                            {{ fa_number(count($guide->sections())) }} بخش
                        </span>
                        @if ($guide->stepsCount() > 0)
                            <span class="inline-flex items-center gap-1">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                {{ fa_number($guide->stepsCount()) }} گام
                            </span>
                        @endif
                    </div>
                    <span class="guide-arrow inline-flex items-center gap-1 text-[11px] font-extrabold text-amber-700">
                        خواندن
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                    </span>
                </div>
            </a>
        @endforeach
    </section>
@endif

@push('scripts')
<script src="{{ asset('back/assets/js/pages/guide/index.js') }}?v=1"></script>
@endpush

@endsection
