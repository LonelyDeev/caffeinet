{{-- PWA (فاز ۱۴) — متاتگ‌های مانیفست/نصب + ثبت Service Worker
| در همه layouts (landing، اپ مشتری، ۴ پنل، صفحات لاگین) include می‌شود.
| sw.js روی scope ریشه ثبت می‌شود؛ بنر نصب/به‌روزرسانی توسط pwa.js رندر می‌شود.
--}}
<link rel="manifest" href="{{ url('manifest.webmanifest') }}">
<meta name="theme-color" content="#a8652e">

{{-- آیکون‌ها (PNG مدرن + fallback به favicon.ico خود پروژه) --}}
<link rel="icon" type="image/png" sizes="48x48" href="{{ asset('icons/icon-48.png') }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/icon-96.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">

{{-- نصب روی موبایل: اندروید + iOS --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<meta name="application-name" content="{{ config('app.name') }}">

{{-- ران‌تایم PWA: ثبت SW + بنر نصب زیبا + اعلان به‌روزرسانی --}}
<script src="{{ asset('assets/js/pwa.js') }}?v=2" defer></script>
