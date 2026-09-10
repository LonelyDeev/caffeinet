<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- تم ذخیره‌شده قبل از رندر اعمال می‌شود (ضد-FOUC) --}}
    <script src="{{ asset('assets/js/theme-boot.js') }}?v=1"></script>

    <title>انتخاب کافی‌نت — {{ config('app.name') }}</title>
    {{-- PWA (فاز ۱۴) --}}
    @include('partials.pwa', ['panel' => 'coffeenet'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap">

    {{-- استایل و اسکریپت — کاملاً بدون Node / بدون بیلد --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=10">
    <link rel="stylesheet" href="{{ asset('assets/css/panel-ui.css') }}?v=10">
    <link rel="stylesheet" href="{{ asset('assets/css/pages/net-org.css') }}?v=10">
    {{-- سیستم تم روشن/تاریک (فاز ۱۰) — باید آخرین CSS باشد تا برنده بماند --}}
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v=10">
</head>
<body class="font-sans antialiased selection:bg-amber-200 selection:text-amber-950">

{{-- سوییچ تم روشن/تاریک — گوشهٔ بالا-چپ (در RTL دور از محتوا) --}}
<button type="button" class="theme-toggle" data-theme-toggle aria-label="تغییر تم روشن/تاریک" title="حالت روشن/تاریک" style="position:fixed;top:1rem;left:1rem;z-index:50">
    <svg class="tt-icon tt-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
    <svg class="tt-icon tt-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
</button>

<main class="no-theme-net relative min-h-screen grid place-items-center overflow-hidden bg-[#171009] px-4 py-10">

    <div class="pointer-events-none fixed inset-0" aria-hidden="true">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_45%_at_50%_-10%,rgba(196,127,61,0.25),transparent)]"></div>
        <span class="ui-blob" data-tone="amber" data-pos="1"></span>
        <span class="ui-blob" data-tone="deep" data-pos="2"></span>
    </div>

    <div class="relative w-full max-w-lg">

        <div class="text-center mb-7 animate-fade-up">
            <span class="inline-grid place-items-center size-14 rounded-3xl bg-gradient-to-br from-amber-400 to-amber-700 shadow-xl shadow-amber-950/40 mb-4">
                <svg class="size-7 text-amber-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><line x1="6" x2="6" y1="2" y2="4"/><line x1="10" x2="10" y1="2" y2="4"/><line x1="14" x2="14" y1="2" y2="4"/>
                </svg>
            </span>
            <h1 class="text-xl font-extrabold text-amber-50 tracking-tight">کافی‌نت خود را انتخاب کنید</h1>
            <p class="mt-1.5 text-xs text-amber-200/50 font-light">{{ auth()->user()?->full_name }} عزیز، مدیریت چند کافی‌نت با حساب شما ثبت شده است</p>
        </div>

        <div class="space-y-3 ui-stagger">
            @foreach ($coffeenets as $net)
                <button type="button" data-net="{{ $net->id }}"
                        class="net-card no-choice-card w-full text-right rounded-2xl glass-warm p-5 flex items-center gap-4">
                    <span class="grid place-items-center size-12 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-700 text-white font-extrabold shrink-0">
                        {{ mb_substr($net->name, 0, 1) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-amber-50 truncate">{{ $net->name }}</p>
                        <p class="text-[11px] text-stone-400 mt-1">
                            {{ $net->province?->name ?? '—' }} ·
                            {{ $net->city?->name ?? '—' }} ·
                            {{ $net->organization?->name ? 'زیرمجموعه '.$net->organization->name : 'مستقل' }}
                        </p>
                    </div>
                    <svg class="size-5 text-amber-300/60 no-choice-arrow shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </button>
            @endforeach
        </div>

        <form id="logout-form" class="mt-6 text-center">
            <button type="submit" class="text-[11px] text-stone-400 hover:text-rose-300 transition-colors">خروج از حساب</button>
        </form>
    </div>
</main>

<script src="{{ asset('assets/js/vendor/jquery.min.js') }}?v=10"></script>
<script src="{{ asset('back/assets/js/core.js') }}?v=10"></script>
<script src="{{ asset('back/assets/js/ui.js') }}?v=10"></script>
<script src="{{ asset('back/assets/js/pages/coffeenet/auth/choose.js') }}?v=10"></script>
</body>
</html>
