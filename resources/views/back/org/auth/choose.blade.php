<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- تم ذخیره‌شده قبل از رندر اعمال می‌شود (ضد-FOUC) --}}
    <script src="{{ asset('assets/js/theme-boot.js') }}?v=1"></script>

    <title>انتخاب سازمان — {{ config('app.name') }}</title>

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

<main class="no-theme-org relative min-h-screen grid place-items-center overflow-hidden no-auth-bg-org px-4 py-10">

    <div class="pointer-events-none fixed inset-0" aria-hidden="true">
        <span class="ui-blob" data-tone="teal" data-pos="1"></span>
        <span class="ui-blob" data-tone="forest" data-pos="2"></span>
        <span class="no-auth-stripes"></span>
    </div>

    <div class="relative w-full max-w-lg">

        <div class="text-center mb-7 animate-fade-up">
            <span class="inline-grid place-items-center size-14 rounded-3xl bg-gradient-to-br from-teal-400 to-teal-700 shadow-xl shadow-black/40 mb-4">
                <svg class="size-7 text-teal-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect width="4" height="16" x="4" y="2" rx="1"/><rect width="4" height="16" x="10" y="2" rx="1"/><path d="M20 4v14"/><path d="M2 20h20"/>
                </svg>
            </span>
            <h1 class="text-xl font-extrabold text-teal-50 tracking-tight">سازمان خود را انتخاب کنید</h1>
            <p class="mt-1.5 text-xs text-teal-200/60 font-light">{{ auth()->user()?->full_name }} عزیز، چند سازمان با حساب شما ثبت شده است</p>
        </div>

        <div class="space-y-3 ui-stagger">
            @foreach ($organizations as $org)
                <button type="button" data-org="{{ $org->id }}"
                        class="org-card no-choice-card w-full text-right rounded-2xl glass-warm p-5 flex items-center gap-4">
                    <span class="grid place-items-center size-12 rounded-2xl bg-gradient-to-br from-teal-400 to-teal-700 text-white font-extrabold shrink-0">
                        {{ mb_substr($org->name, 0, 1) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-teal-50 truncate">{{ $org->name }}</p>
                        <p class="text-[11px] text-stone-400 mt-1">
                            {{ $org->province?->name ?? '—' }} ·
                            {{ $org->status->label() }}
                        </p>
                    </div>
                    <svg class="size-5 text-teal-200/60 no-choice-arrow shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
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
<script src="{{ asset('back/assets/js/pages/org/auth/choose.js') }}?v=10"></script>
</body>
</html>
