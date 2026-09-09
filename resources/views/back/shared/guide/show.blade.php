@extends($guideLayout ?? 'back.layouts.panel')

@section('title', $guide->title)
@section('page-title', $guide->title)
@section('breadcrumb', ($guidePanelLabel ?? 'پنل').' ← راهنمای پنل ← '.$guide->title)

@push('styles')
<style>
.gd-article{max-width:none}
.gd-article h2.gd-h{font-size:.95rem;font-weight:800;color:#292524;display:flex;align-items:center;gap:.6rem}
.gd-article h2.gd-h .gd-num{display:grid;place-items:center;width:1.85rem;height:1.85rem;border-radius:.75rem;background:linear-gradient(135deg,#f59e0b,#b45309);color:#fff;font-size:.7rem;font-weight:800;flex-shrink:0}
.gd-article p{font-size:.8rem;color:#57534e;line-height:2;margin-top:.5rem}
.gd-article ul{margin-top:.5rem;padding-inline-start:1.2rem}
.gd-article ul li{font-size:.8rem;color:#57534e;line-height:2;list-style:disc}
.gd-article code{direction:ltr;display:inline-block;background:#f5f5f4;border:1px solid #e7e5e4;border-radius:.4rem;padding:.05rem .45rem;font-size:.72rem;color:#92400e;font-family:ui-monospace,monospace}
.gd-steps{counter-reset:gdstep;margin-top:.8rem;display:flex;flex-direction:column;gap:.5rem}
.gd-step{position:relative;display:flex;gap:.8rem;align-items:flex-start;background:#fafaf9;border:1px solid #f0efee;border-radius:1rem;padding:.7rem .9rem;font-size:.78rem;color:#44403c;line-height:1.9}
.gd-step::before{counter-increment:gdstep;content:counter(gdstep);display:grid;place-items:center;min-width:1.6rem;height:1.6rem;border-radius:.65rem;background:rgba(245,158,11,.15);color:#b45309;font-weight:800;font-size:.72rem;flex-shrink:0}
.gd-step b{color:#292524}
.gd-toc a{display:flex;gap:.5rem;align-items:center;font-size:.75rem;font-weight:700;color:#78716c;padding:.45rem .6rem;border-radius:.65rem;transition:all .2s}
.gd-toc a:hover{background:rgba(245,158,11,.08);color:#b45309}
.gd-toc .n{display:grid;place-items:center;min-width:1.35rem;height:1.35rem;border-radius:.5rem;background:#f5f5f4;font-size:.65rem;font-weight:800;color:#a8a29e}
.gd-mark{transition:all .2s}
.gd-mark.done{background:#059669!important;border-color:#047857!important}
.gd-mark.done span{color:#fff}
.gd-sep{height:1px;background:linear-gradient(to left,transparent,#e7e5e4,transparent);margin:1.6rem 0}
@media print{.gd-toc,.gd-mark,.no-print{display:none!important}}
</style>
@endpush

@section('content')

<div class="grid grid-cols-1 lg:grid-cols-[1fr_270px] gap-4 items-start">

    {{-- ================== بدنهٔ راهنما ================== --}}
    <article class="card ui-lift animate-fade-up gd-article overflow-hidden" aria-labelledby="gd-title">
        {{-- سربرگ --}}
        <div class="p-6 sm:p-8 pb-0">
            <a href="{{ $guideBase }}" class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-amber-700 hover:text-amber-800 transition-colors no-print">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                همهٔ راهنماها
            </a>
            <h1 id="gd-title" class="text-lg font-black text-stone-800 mt-3">{{ $guide->title }}</h1>
            @if ($guide->description)
                <p class="text-xs text-stone-400 mt-2 leading-6">{{ $guide->description }}</p>
            @endif
            <div class="flex flex-wrap items-center gap-2 mt-4 pb-5 border-b border-stone-100">
                <span class="badge bg-amber-50 text-amber-700 border border-amber-200">{{ $guideRoleLabel }}</span>
                <span class="badge bg-stone-100 text-stone-500 border border-stone-200">{{ fa_number(count($guide->sections())) }} بخش</span>
                @if ($guide->stepsCount() > 0)
                    <span class="badge bg-stone-100 text-stone-500 border border-stone-200">{{ fa_number($guide->stepsCount()) }} گام عملی</span>
                @endif
            </div>
        </div>

        {{-- بخش‌ها --}}
        <div class="p-6 sm:p-8">
            @foreach ($guide->sections() as $i => $section)
                <section id="gd-sec-{{ $i }}" class="scroll-mt-24">
                    <h2 class="gd-h">
                        <span class="gd-num">{{ fa_number($i + 1) }}</span>
                        {{ $section['h'] }}
                    </h2>

                    <div class="gd-body">{!! ($section['body'] ?? '') !!}</div>

                    @if (! empty($section['steps']))
                        <div class="gd-steps" role="list" aria-label="گام‌های عملی">
                            @foreach ($section['steps'] as $step)
                                <div class="gd-step" role="listitem"><span>{{ $step }}</span></div>
                            @endforeach
                        </div>
                    @endif
                </section>

                @if (! $loop->last)
                    <div class="gd-sep" aria-hidden="true"></div>
                @endif
            @endforeach
        </div>

        {{-- پاورقی: خواندم + راهنمای بعدی --}}
        <div class="p-6 sm:p-8 border-t border-stone-100 bg-stone-50/60 flex flex-wrap items-center justify-between gap-4 no-print">
            <button type="button" id="gd-mark" data-slug="{{ $guide->slug }}"
                    class="gd-mark btn-primary btn-shine ui-press !py-2.5 !px-6 !text-xs">
                <svg class="size-4 inline-block align-middle me-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <span>خواندم — علامت‌گذاری</span>
            </button>

            @if ($nextGuide)
                <a href="{{ $guideBase }}/{{ $nextGuide->slug }}" class="text-xs font-extrabold text-amber-700 hover:text-amber-800 transition-colors">
                    راهنمای بعدی: «{{ $nextGuide->title }}» ←
                </a>
            @else
                <a href="{{ $guideBase }}" class="text-xs font-extrabold text-amber-700 hover:text-amber-800 transition-colors">
                    پایان راهنماهای {{ $guideRoleLabel }} — بازگشت به فهرست ←
                </a>
            @endif
        </div>
    </article>

    {{-- ================== فهرست مطالب ================== --}}
    <aside class="card ui-lift animate-fade-up p-4 lg:sticky lg:top-24 gd-toc no-print" aria-label="فهرست مطالب این راهنما">
        <p class="text-[11px] font-extrabold text-stone-500 mb-3 px-1">فهرست مطالب</p>
        <nav class="flex flex-col gap-0.5">
            @foreach ($guide->sections() as $i => $section)
                <a href="#gd-sec-{{ $i }}">
                    <span class="n">{{ fa_number($i + 1) }}</span>
                    <span class="flex-1 truncate">{{ $section['h'] }}</span>
                </a>
            @endforeach
        </nav>
        <div class="mt-4 pt-3 border-t border-stone-100 px-1">
            <p class="text-[10px] text-stone-400 leading-5">
                سهم شما از پیشرفت مطالعهٔ راهنماها در همین مرورگر ذخیره می‌شود.
            </p>
        </div>
    </aside>
</div>

@push('scripts')
<script src="{{ asset('back/assets/js/pages/guide/show.js') }}?v=1"></script>
@endpush

@endsection
