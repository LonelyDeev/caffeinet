<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'کافی‌نت آنلاین'))</title>

    {{-- PWA: مانیفست + آیکون‌ها + ثبت Service Worker (فاز ۱۴) --}}
    @include('partials.pwa', ['panel' => 'app'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap">

    {{-- استایل — کاملاً بدون Node / بدون بیلد --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="font-sans antialiased selection:bg-brand-300 selection:text-brand-950 @yield('body-class', 'bg-[#171009] text-brand-50')">

    @yield('content')

</body>
</html>
