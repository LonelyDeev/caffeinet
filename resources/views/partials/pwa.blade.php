{{-- PWA (فاز ۱۴ — بازطراحی تفکیک‌شده) — مانیفست اختصاصی هر پنل
| هر پنل به‌عنوان یک «اپ مستقل» نصب می‌شود و آیکون نصب‌شده مستقیماً
| همان پنل را باز می‌کند (نه صفحه فرود). متغیر $panel (پیش‌فرض app):
|   app | admin | organization | coffeenet | operator
| sw.js روی scope ریشه ثبت می‌شود (صفحه آفلاین مشترک)؛ مودال نصب
| فقط در صفحات اپ مشتری (pwa.js) رندر می‌شود و تا نصب‌شدن در هر
| مراجعه تکرار می‌شود (چک‌باکس «دیگه نمایش نده»).
| صفحه فرود (landing) این پارشال را include نمی‌کند → غیرقابل نصب.
--}}
@php
    $pwaPanel  = $panel ?? 'app';
    $pwaIsApp  = $pwaPanel === 'app';
    $pwaIconDir = 'icons/panels/'.$pwaPanel;
    $pwaHasOwn = ! $pwaIsApp && is_file(public_path($pwaIconDir.'-192.png'));
    $pwaIcon   = fn (int $s) => $pwaHasOwn
        ? asset($pwaIconDir.'-'.$s.'.png')
        : asset('icons/icon-'.$s.'.png');
    $pwaTitle  = match ($pwaPanel) {
        'admin'        => 'کافینت — مدیریت کل',
        'organization' => 'کافینت — سازمان',
        'coffeenet'    => 'کافینت — کافی‌نت',
        'operator'     => 'کافینت — اپراتور',
        default        => (string) config('app.name', 'کافی‌نت آنلاین'),
    };
@endphp
<link rel="manifest" href="{{ url($pwaPanel.'/manifest.webmanifest') }}">
<meta name="theme-color" content="#a8652e">

{{-- آیکون‌ها (اختصاصی پنل؛ در نبود فایل → آیکون برند + fallback به favicon.ico) --}}
<link rel="icon" type="image/png" sizes="48x48" href="{{ $pwaIcon(48) }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ $pwaIcon(96) }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $pwaHasOwn ? asset($pwaIconDir.'-180.png') : asset('icons/apple-touch-icon.png') }}">

{{-- نصب روی موبایل: اندروید + iOS --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $pwaTitle }}">
<meta name="application-name" content="{{ $pwaTitle }}">

{{-- ران‌تایم PWA: ثبت SW + مودال نصب سمت مشتری + اعلان به‌روزرسانی --}}
<script src="{{ asset('assets/js/pwa.js') }}?v=4" defer></script>
