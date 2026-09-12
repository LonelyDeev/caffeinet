{{-- ============================================================
 |  کافی‌نت آنلاین — صفحه فرود تک‌صفحه‌ای معرفی برای مشتریان
 |  طراحی مستقل (بدون وابستگی به app.css کامپایل‌شده)
 ============================================================ --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ config('app.name', 'کافی‌نت آنلاین') }} — سفارش آنلاین خدمات کافی‌نت</title>
    <meta name="description" content="سفارش آنلاین خدمات کافی‌نت؛ فرم‌ساز پویا، پرداخت آنلاین، پیگیری لحظه‌ای سفارش و چت مستقیم با اپراتور. خدمات خودرویی، اداری و پلاک خودرو در چند کلیک.">

    {{-- آیکون و رنگ تم سایت — بدون مانیفست PWA و بدون Service Worker
     | (صفحه فرود عمداً «غیرقابل نصب» است؛ نصب اپ فقط از داخل پنل‌ها انجام می‌شود) --}}
    <meta name="theme-color" content="#a8652e">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('icons/icon-48.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/icon-96.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">

    {{-- فونت وزیرمتن --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap">

    {{-- استایل مستقل صفحه فرود --}}
    <link rel="stylesheet" href="{{ asset('assets/css/landing.css') }}?v=5">

    {{-- اسکیمای SEO (JSON-LD) --}}
    <script type="application/ld+json">{!! json_encode([
        '@context'   => 'https://schema.org',
        '@type'      => 'WebSite',
        'name'       => config('app.name', 'کافی‌نت آنلاین'),
        'url'        => url('/'),
        'potentialAction' => [
            '@type' => 'SearchAction', 'target' => url('/app/services'), 'query' => 'search',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
</head>
<body>

<div class="bg-texture" aria-hidden="true"></div>
<div class="bg-fx" aria-hidden="true">
    <span class="blob blob-1"></span>
    <span class="blob blob-2"></span>
</div>

@php
    $svgAttrs = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    $checkSvg = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
@endphp

{{-- ==================== هدر ==================== --}}
<header class="site-header" id="siteHeader">
    <div class="container header-inner">
        <a class="brand" href="{{ url('/') }}" aria-label="کافی‌نت آنلاین — صفحه اصلی">
            <span class="brand-mark">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>
                </svg>
                <span class="dot"></span>
            </span>
            <span class="brand-text">
                <strong>{{ config('app.name', 'کافی‌نت آنلاین') }}</strong>
                <span>سفارش آنلاین خدمات</span>
            </span>
        </a>

        <nav class="main-nav" aria-label="ناوبری اصلی">
            <a href="#services">خدمات</a>
            <a href="#how">مراحل سفارش</a>
            <a href="#features">چرا ما؟</a>
            <a href="#pwa">نصب اپلیکیشن</a>
            <a href="#faq">سوالات متداول</a>
        </nav>

        <div class="header-actions">
            <a href="{{ route('app.auth') }}" class="btn btn-gold btn-sm">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>
                ورود / ثبت‌نام
            </a>
            <button class="burger" id="navBurger" type="button" aria-expanded="false" aria-controls="mobileNav" aria-label="باز و بسته کردن منو">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    {{-- منوی موبایل --}}
    <nav class="mobile-nav" id="mobileNav" aria-label="ناوبری موبایل">
        <div class="nav-wrap container">
            <a href="#services">خدمات کافی‌نت</a>
            <a href="#how">مراحل سفارش</a>
            <a href="#features">چرا کافی‌نت آنلاین؟</a>
            <a href="#pwa">نصب اپلیکیشن</a>
            <a href="#faq">سوالات متداول</a>
            <a href="{{ route('app.auth') }}" class="btn btn-gold nav-cta">ورود / ثبت‌نام مشتری</a>
        </div>
    </nav>
</header>

<main class="site-main" style="display:contents">

{{-- ==================== قهرمان صفحه ==================== --}}
<section class="hero" id="top">
    <div class="container hero-grid">

        <div class="hero-copy">
            @if ($workStatus['enabled'])
                <span class="live-pill {{ $workStatus['open'] ? '' : 'is-closed' }}">
                    <span class="pulse"></span>
                    @if ($workStatus['open'])
                        همین حالا آنلاین هستیم — ساعت کاری {{ $workStatus['range'] }}
                    @else
                        خارج از ساعت کاری ({{ $workStatus['range'] }}) — سفارش شما ثبت و در ابتدای ساعت کاری پردازش می‌شود
                    @endif
                </span>
            @else
                <span class="live-pill"><span class="pulse"></span> پشتیبانی ۷ روز هفته — بدون محدودیت ساعت کاری</span>
            @endif

            <h1 class="hero-title">
                خدمات کافی‌نت،
                <span class="gold">بدون مراجعه</span>
                <br>در چند کلیک
            </h1>

            <p class="hero-desc">
                از <b>پلاک خودرو</b> و <b>خدمات اداری</b> تا <b>گواهی‌ها و ثبت‌نام‌ها</b>؛
                فرم را آنلاین پر کنید، آنلاین پرداخت کنید و نتیجه را
                <b>لحظه‌به‌لحظه</b> از داخل چت پیگیری کنید. بدون صف، بدون رفت‌وآمد، بدون کاغذبازی.
            </p>

            <div class="hero-ctas">
                <a href="{{ route('app.auth') }}" class="btn btn-gold btn-lg">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/></svg>
                    شروع سفارش
                </a>
                <a href="#services" class="btn btn-ghost btn-lg">
                    مشاهده خدمات
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                </a>
            </div>

            <ul class="hero-trust">
                <li class="trust-item">{!! $checkSvg !!} پرداخت امن درگاهی</li>
                <li class="trust-item">{!! $checkSvg !!} پشتیبانی تیکت و چت</li>
                <li class="trust-item">{!! $checkSvg !!} اعلان پیامکی وضعیت سفارش</li>
                <li class="trust-item">{!! $checkSvg !!} کیف پول داخلی</li>
            </ul>
        </div>

        {{-- موکاپ گوشی --}}
        <div class="hero-visual" aria-hidden="true">
            <div class="phone">
                <div class="phone-screen">
                    <div class="phone-status"><span>{{ now()->format('H:i') }}</span><span>۵G ▮▮▮</span></div>
                    <div class="phone-card">
                        <div class="pc-head">
                            <span class="pc-ic amber">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/></svg>
                            </span>
                            <div>
                                <div class="pc-title">تعویض پلاک خودرو تهران</div>
                                <div class="pc-sub">کافی‌نت مرکزی تهران</div>
                            </div>
                        </div>
                        <div class="pc-row">
                            <span class="pc-price">۲۸۰,۰۰۰ تومان</span>
                            <span class="pc-badge step">در حال انجام</span>
                        </div>
                        <div class="phone-progress" style="margin-top:11px"><i></i></div>
                    </div>

                    <div class="phone-card phone-chat">
                        <div class="msg them">سلام! مدارک شما دریافت شد ✅ الان به سامانه پلاک ارسال می‌کنم.</div>
                        <div class="msg me">عالیه، ممنون 🙏</div>
                        <div class="msg them">پلاک در انتظار صدور است؛ نتیجه را همین‌جا اطلاع می‌دهم.</div>
                    </div>

                    <div class="phone-card">
                        <div class="pc-head">
                            <span class="pc-ic ok">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <div>
                                <div class="pc-title">سفارش شما آماده شد</div>
                                <div class="pc-sub">فایل نتیجه در گفتگو بارگذاری شد</div>
                            </div>
                        </div>
                    </div>

                    <div class="phone-bottom">
                        <span class="on">خانه</span><span>خدمات</span><span>سفارش‌ها</span>
                    </div>
                </div>
            </div>

            <div class="float-card fc-1">
                <span class="fc-ic">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <div><div class="fc-t">تخصیص هوشمند سفارش</div><div class="fc-s">کمتر از ۶۰ ثانیه</div></div>
            </div>
            <div class="float-card fc-2">
                <span class="fc-ic">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </span>
                <div><div class="fc-t">چت زنده با اپراتور</div><div class="fc-s">متن، تصویر و فایل</div></div>
            </div>
            <div class="float-card fc-3">
                <span class="fc-ic">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h2"/><path d="m4.9 4.9 2.9 2.9"/></svg>
                </span>
                <div><div class="fc-t">{{ fa_number($stats['orders']) }} سفارش ثبت‌شده</div><div class="fc-s">در {{ fa_number($stats['coffeenets']) }} کافی‌نت همکار</div></div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== نوار متحرک خدمات ==================== --}}
<div class="ticker" aria-hidden="true">
    <div class="ticker-track">
        {{-- نام‌های واقعی خدمات کاتالوگ (v21 — دیگر نیازی به فهرست ثابت نیست) --}}
        @foreach ($grouped->flatMap->services->pluck('name') as $item)
            <span class="ticker-item"><i>☕</i>{{ $item }}</span>
        @endforeach
    </div>
</div>

{{-- ==================== خدمات ==================== --}}
<section class="section" id="services">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="7" height="7" x="3" y="3" rx="1.5"/><rect width="7" height="7" x="14" y="3" rx="1.5"/><rect width="7" height="7" x="3" y="14" rx="1.5"/><rect width="7" height="7" x="14" y="14" rx="1.5"/></svg>
                کاتالوگ خدمات
            </span>
            <h2 class="section-title">دسته‌بندی <span class="gold">خدمات</span> کافی‌نت‌ها</h2>
            <p class="section-desc">
                سفارش هر خدمت با چند کلیک؛ روی دستهٔ موردنظر بزنید تا فهرست کامل خدمت‌های همان دسته
                با قیمت شفاف و زمان تقریبی در اپ نمایش داده شود.
            </p>
        </div>

        {{-- v26 — کاشی‌های عمودی دسته‌بندی‌ها: هر عنصر در ردیف خودش، بدون هیچ هم‌پوشانی --}}
        @if (count($landingCategories ?? []))
            <div class="cat-grid">
                @foreach ($landingCategories as $item)
                    <a class="cat-card reveal" href="{{ route('app.services') }}#cat-{{ $item['category']->id }}">
                        <span class="cat-card-ic" aria-hidden="true">{{ $item['category']->icon ?: '☕' }}</span>
                        <b class="cat-card-name">{{ $item['category']->name }}</b>
                        <span class="cat-card-go">
                            {{ fa_number($item['total']) }} خدمت
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="cat-block reveal" style="text-align:center;padding:40px 20px;color:var(--ink-3)">
                <p style="margin:0;font-weight:300">به‌زودی کاتالوگ خدمات فعال می‌شود؛ برای پیگیری از دکمهٔ «شروع سفارش» استفاده کنید.</p>
            </div>
        @endif

        <div class="reveal" style="text-align:center;margin-top:44px">
            <a href="{{ route('app.services') }}" class="btn btn-ghost btn-lg">
                مشاهدهٔ همهٔ {{ fa_number($stats['services']) }} خدمت در {{ fa_number($stats['categories']) }} دسته
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
            </a>
        </div>
    </div>
</section>

{{-- ==================== مراحل سفارش ==================== --}}
<section class="section" id="how">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 22h16"/><path d="M6 18v-7a6 6 0 0 1 12 0v7"/><path d="M10 8.5 12 6l2 2.5"/></svg>
                فقط ۴ قدم
            </span>
            <h2 class="section-title">سفارش تا نتیجه، <span class="gold">چقدر ساده!</span></h2>
            <p class="section-desc">از ثبت‌نام با شماره موبایل تا دریافت نتیجه در گفتگو؛ همه‌چیز آنلاین و بدون تماس تلفنی.</p>
        </div>

        <div class="steps">
            <div class="step-card reveal">
                <span class="step-ic">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/><path d="M8.5 8h7"/><path d="M8.5 11.5h4"/></svg>
                </span>
                <h3 class="step-title">ورود با شماره موبایل</h3>
                <p class="step-desc">ثبت‌نام فقط با موبایل و کد یک‌بارمصرف؛ نه فرم طولانی، نه رمز فراموش‌شدنی.</p>
            </div>
            <div class="step-card reveal" style="--rv-delay:.1s">
                <span class="step-ic">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h3"/></svg>
                </span>
                <h3 class="step-title">انتخاب خدمت و پر کردن فرم</h3>
                <p class="step-desc">فرم اختصاصی هر خدمت را ببینید؛ مدارک لازم همان‌جا مشخص است و آپلود فایل انجام می‌شود.</p>
            </div>
            <div class="step-card reveal" style="--rv-delay:.2s">
                <span class="step-ic">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
                </span>
                <h3 class="step-title">پرداخت آنلاین یا از کیف پول</h3>
                <p class="step-desc">درگاه امن بانکی یا شارژ کیف پول؛ مبلغ شفاف و پیش از ثبت نهایی نمایش داده می‌شود.</p>
            </div>
            <div class="step-card reveal" style="--rv-delay:.3s">
                <span class="step-ic">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </span>
                <h3 class="step-title">پیگیری لحظه‌ای و دریافت نتیجه</h3>
                <p class="step-desc">سفارش در کمتر از ۶۰ ثانیه به اپراتور مناسب می‌رسد؛ نتیجه و فایل‌ها داخل چت.</p>
            </div>
        </div>
    </div>
</section>

{{-- ==================== مزایا ==================== --}}
<section class="section" id="features">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                چرا کافی‌نت آنلاین؟
            </span>
            <h2 class="section-title">همه‌چیزِ یک کافی‌نت، <span class="gold">در جیب شما</span></h2>
            <p class="section-desc">امکاناتی که کار را برای شما ساده و برای اپراتورها دقیق می‌کند.</p>
        </div>

        <div class="feat-grid">
            <div class="feat-card reveal">
                <span class="feat-ic">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/><circle cx="16.5" cy="17.5" r="2"/></svg>
                </span>
                <h3 class="feat-title">فرم‌ساز پویا</h3>
                <p class="feat-desc">هر خدمت فرم مخصوص خودش را دارد؛ فیلدهای متنی، عددی، فایل و انتخاب — دقیقاً همان مدارکی که لازم است، نه بیشتر نه کمتر.</p>
            </div>
            <div class="feat-card reveal" style="--rv-delay:.05s">
                <span class="feat-ic">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </span>
                <h3 class="feat-title">تخصیص هوشمند ۶۰ ثانیه‌ای</h3>
                <p class="feat-desc">موتور تخصیص سفارش را در کمتر از یک دقیقه به اپراتور آزاد و مناسب هر کافی‌نت می‌رساند؛ بدون انتظار و بدون دنبال‌کردن.</p>
            </div>
            <div class="feat-card reveal" style="--rv-delay:.1s">
                <span class="feat-ic">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/><path d="M8 10h8"/><path d="M8 14h5"/></svg>
                </span>
                <h3 class="feat-title">گفتگوی لحظه‌ای تلگرام‌گونه</h3>
                <p class="feat-desc">متن، تصویر، صدا، ویدیو و فایل — هم با اپراتور، هم با پشتیبانی. اعلان جدید بودن پیام، همان لحظه می‌رسد.</p>
            </div>
            <div class="feat-card reveal" style="--rv-delay:.05s">
                <span class="feat-ic">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
                </span>
                <h3 class="feat-title">کیف پول و پرداخت امن</h3>
                <p class="feat-desc">شارژ کیف پول از درگاه بانکی و پرداخت سفارش‌ها با یک کلیک؛ گردش کامل حساب همیشه در دسترس شماست.</p>
            </div>
            <div class="feat-card reveal" style="--rv-delay:.1s">
                <span class="feat-ic">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                </span>
                <h3 class="feat-title">اعلان پیامکی وضعیت</h3>
                <p class="feat-desc">ثبت، شروع پردازش، اتمام یا ابطال سفارش — برای هر تغییر وضعیت، پیامک با متن اختصاصی دریافت می‌کنید.</p>
            </div>
            <div class="feat-card reveal" style="--rv-delay:.15s">
                <span class="feat-ic">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h2"/><path d="m4.9 4.9 2.9 2.9"/></svg>
                </span>
                <h3 class="feat-title">اطلاعیه‌های هدفمند</h3>
                <p class="feat-desc">اوضاع و احوال خدمات — قطع موقت، مهلت ثبت‌نام، اطلاعیه‌های متنی/تصویری — پیش از سفارش به شما اعلام می‌شود.</p>
            </div>
            <div class="feat-card reveal" style="--rv-delay:.1s">
                <span class="feat-ic">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                </span>
                <h3 class="feat-title">نظرسنجی پس از انجام</h3>
                <p class="feat-desc">پایان هر سفارش، امتیاز و نظر شما ثبت می‌شود؛ کیفیت خدمات با بازخورد واقعی مشتریان بالا می‌رود.</p>
            </div>
            <div class="feat-card reveal" style="--rv-delay:.15s">
                <span class="feat-ic">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </span>
                <h3 class="feat-title">تیکت پشتیبانی</h3>
                <p class="feat-desc">برای هر سؤال یا مشکلی، تیکت ثبت کنید و پاسخ پیوست‌دار بگیرید؛ تاریخچهٔ کامل گفتگوها حفظ می‌شود.</p>
            </div>
        </div>
    </div>
</section>

{{-- ==================== نوار آمار ==================== --}}
<section class="section stats-band" aria-label="آمار پلتفرم">
    <div class="container">
        <div class="stats-panel reveal">
            <div class="stat-item">
                <div class="stat-num" data-count="{{ $stats['coffeenets'] }}">۰</div>
                <div class="stat-label">کافی‌نت در شبکهٔ ما</div>
            </div>
            <div class="stat-item">
                <div class="stat-num" data-count="{{ $stats['services'] }}">۰</div>
                <div class="stat-label">خدمت آنلاین</div>
            </div>
            <div class="stat-item">
                <div class="stat-num" data-count="{{ $stats['orders'] }}">۰</div>
                <div class="stat-label">سفارش ثبت‌شده</div>
            </div>
            <div class="stat-item">
                <div class="stat-num" data-count="{{ $stats['customers'] }}">۰</div>
                <div class="stat-label">مشتری ثبت‌نام‌شده</div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== نصب اپلیکیشن (PWA) ==================== --}}
<section class="section" id="pwa">
    <div class="container">
        <div class="pwa-panel reveal">
            <div>
                <span class="eyebrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/></svg>
                    وب‌اپلیکیشن (PWA)
                </span>
                <h2 class="section-title" style="margin-top:14px">اپ کافی‌نت، <span class="gold">بدون دانلود از استور</span></h2>
                <p class="section-desc" style="margin-top:10px">
                    اپ مشتریان کافی‌نت آنلاین یک وب‌اپلیکیشن کامل است؛ نصب از داخل خودِ اپ انجام می‌شود.
                    بعد از نصب، آیکون صفحهٔ اصلی مستقیماً پنل مشتری را باز می‌کند (نه سایت معرفی را) —
                    سریع، تمام‌صفحه و آفلاین‌پسند. بدون به‌روزرسانی دستی، بدون مصرف حجم اضافه.
                </p>

                <div class="pwa-steps">
                    <div class="pwa-step">
                        <span class="n">۱</span>
                        <div><div class="t">دکمهٔ «ورود به اپ» را بزنید</div><div class="s">شروع از همین صفحه؛ بدون دانلود چیزی از استور.</div></div>
                    </div>
                    <div class="pwa-step">
                        <span class="n">۲</span>
                        <div><div class="t">در اپ، پیشنهاد نصب نمایش داده می‌شود</div><div class="s">اندروید: مودال «نصب اپلیکیشن»؛ iOS: اشتراک‌گذاری ← «Add to Home Screen».</div></div>
                    </div>
                    <div class="pwa-step">
                        <span class="n">۳</span>
                        <div><div class="t">آیکون کافی‌نت روی صفحهٔ اصلی</div><div class="s">اجرا یعنی مستقیم پنل مشتری؛ سریع، تمام‌صفحه و بدون نوار مرورگر.</div></div>
                    </div>
                </div>

                <div class="hero-ctas" style="margin-top:26px">
                    <a href="{{ route('app.index') }}" class="btn btn-gold">ورود به اپ و نصب</a>
                    <a href="{{ route('app.services') }}" class="btn btn-ghost">مشاهدهٔ خدمات</a>
                </div>
            </div>

            <div class="pwa-visual">
                <div class="shot">
                    <div class="notch"></div>
                    <img src="{{ asset('icons/icon-512.png') }}" alt="آیکون وب‌اپلیکیشن کافی‌نت آنلاین" width="512" height="512" style="padding:56px 74px;background:radial-gradient(ellipse 70% 55% at 50% 40%, rgba(196,127,61,.28), transparent 70%), var(--bg-2)">
                </div>
                <div class="browsers">
                    <span class="browser-chip">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                        Chrome / Edge — اندروید
                    </span>
                    <span class="browser-chip">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 18h.01"/><path d="M8 21h8"/><path d="M12 3a6 6 0 0 0-4 10.5c.8.7 1.4 1.6 1.7 2.5h4.6c.3-.9.9-1.8 1.7-2.5A6 6 0 0 0 12 3z"/></svg>
                        Safari — iOS
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== نظرات مشتریان ==================== --}}
<section class="section" id="testimonials">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11.5 3.9 9.8 2.2a1 1 0 0 0-1.4 0L6.7 3.9a1 1 0 0 1-1.4 0L3.6 2.2a1 1 0 0 0-1.4 0L.5 3.9"/><path d="M11.5 12.9 9.8 11.2a1 1 0 0 0-1.4 0l-1.7 1.7a1 1 0 0 1-1.4 0l-1.7-1.7a1 1 0 0 0-1.4 0l-1.7 1.7"/></svg>
                تجربهٔ مشتریان
            </span>
            <h2 class="section-title">آن‌ها <span class="gold">بدون صف</span> کارشان را انجام دادند</h2>
        </div>

        <div class="testi-grid">
            <div class="testi-card reveal">
                <span class="testi-quote">"</span>
                <span class="stars" aria-label="امتیاز ۵ از ۵">
                    @for ($i = 0; $i < 5; $i++)<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>@endfor
                </span>
                <p class="testi-text">برای تعویض پلاک ماشین از داخل محل کارم سفارش دادم؛ مدارک را عکس گرفتم و آپلود کردم. عصر همان روز پلاک آماده شد و همهٔ مراحل از داخل چت اطلاع داده شد. دیگر هیچ‌وقت سرصف کافی‌نت نمی‌ایستم.</p>
                <div class="testi-user">
                    <span class="testi-avatar">م</span>
                    <div><div class="tu-name">مریم احمدی</div><div class="tu-meta">تهران · تعویض پلاک خودرو</div></div>
                </div>
            </div>
            <div class="testi-card reveal" style="--rv-delay:.1s">
                <span class="testi-quote">"</span>
                <span class="stars" aria-label="امتیاز ۵ از ۵">
                    @for ($i = 0; $i < 5; $i++)<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>@endfor
                </span>
                <p class="testi-text">گواهی سوءپیشینه را ساعت ۸ صبح سفارش دادم؛ ساعت 9 و 20 دقیقه فایل PDF در چت بود. پرداخت هم از کیف پول انجام شد که قبلاً شارژ کرده بودم. سرعت و پیگیری عالی بود.</p>
                <div class="testi-user">
                    <span class="testi-avatar">ع</span>
                    <div><div class="tu-name">علی رضایی</div><div class="tu-meta">تهران · گواهی سوءپیشینه</div></div>
                </div>
            </div>
            <div class="testi-card reveal" style="--rv-delay:.2s">
                <span class="testi-quote">"</span>
                <span class="stars" aria-label="امتیاز ۵ از ۵">
                    @for ($i = 0; $i < 5; $i++)<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>@endfor
                </span>
                <p class="testi-text">سایت را روی گوشی نصب کردم (Add to Home Screen) و حالا مثل یک اپ استفاده می‌کنم. وقتی وضعیت سفارش عوض می‌شود پیامک می‌آید و لازم نیست حتی سایت را باز کنم.</p>
                <div class="testi-user">
                    <span class="testi-avatar">س</span>
                    <div><div class="tu-name">سارا موسوی</div><div class="tu-meta">تهران · خدمات اداری</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== سوالات متداول ==================== --}}
<section class="section" id="faq">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                سوالات متداول
            </span>
            <h2 class="section-title">شاید سؤال <span class="gold">شما هم</span> اینجا باشد</h2>
        </div>

        <div class="faq-wrap">
            @foreach ([
                ['چطور ثبت‌نام کنم؟', 'فقط شمارهٔ موبایل خود را وارد کنید؛ کد تأیید پیامکی می‌شود و حساب شما ساخته می‌شود. تکمیل پروفایل (نام و شهر) تنها در سفارش اول لازم است و کمتر از یک دقیقه وقت می‌گیرد.'],
                ['پرداخت چطور انجام می‌شود؟', 'پس از پر کردن فرم سفارش، مبلغ نهایی نمایش داده می‌شود و می‌توانید با درگاه امن بانکی یا کیف پول داخلی (در صورت موجودی) پرداخت کنید. رسید هر پرداخت در بخش کیف پول قابل مشاهده است.'],
                ['مدارک لازم را از کجا بفهمم؟', 'روی کارت هر خدمت و صفحهٔ جزئیات آن، فیلدهای متنی و فایل‌های لازم دقیقاً مشخص است. اگر خدمتی نیاز به آپلود مدارک داشته باشد، نشان «همراه با مدارک» روی کارتش درج می‌شود.'],
                ['پیگیری سفارش چگونه است؟', 'هر سفارش یک صفحهٔ اختصاصی با تاریخچهٔ وضعیت و چت زنده دارد؛ سفارش شما حداکثر تا ۶۰ ثانیه پس از پرداخت به اپراتور مناسب تخصیص می‌یابد و در هر تغییر وضعیت، پیامک دریافت می‌کنید.'],
                ['اگر خدمت قطع یا مهلتش تمام شده باشد چه می‌شود؟', 'وضعیت لحظه‌ای هر خدمت (قطع موقت یا پایان مهلت ثبت) با برچسب روی کارت و پیام داخل صفحهٔ خدمت اعلام می‌شود؛ در این حالت امکان ثبت سفارش برای همان خدمت موقتاً غیرفعال است تا وقت و هزینهٔ شما تلف نشود.'],
                ['این اپ را می‌شود روی گوشی نصب کرد؟', 'بله؛ اپ مشتریان کافی‌نت یک وب‌اپلیکیشن (PWA) است. کافی است از دکمهٔ «ورود به اپ» وارد اپ شوید؛ در اندروید پیشنهاد نصب نمایش داده می‌شود و در iOS از دکمهٔ اشتراک، «Add to Home Screen» را بزنید. آیکون نصب‌شده مستقیماً پنل مشتری را باز می‌کند.'],
            ] as [$q, $a])
                <div class="faq-item reveal">
                    <button class="faq-q" type="button" aria-expanded="false">
                        {{ $q }}
                        <span class="chev">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </span>
                    </button>
                    <div class="faq-a"><div class="faq-a-inner">{{ $a }}</div></div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ==================== CTA نهایی ==================== --}}
<section class="section final-cta">
    <div class="container">
        <div class="cta-panel reveal">
            <div class="cta-cup" aria-hidden="true">
                <span class="steam"><i></i><i></i><i></i></span>
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><path d="M6 2v2"/><path d="M10 2v2"/><path d="M14 2v2"/></svg>
            </div>
            <h2 class="cta-title">امروز اولین سفارش‌تان را <span class="gold">آنلاین</span> ثبت کنید</h2>
            <p class="cta-desc">
                با یک شماره موبایل شروع کنید؛ فرم، پرداخت و پیگیری — همه در یک جا. اگر سوالی دارید، تیم پشتیبانی در تیکت‌ها پاسخگوست.
            </p>
            <div class="cta-actions">
                <a href="{{ route('app.auth') }}" class="btn btn-gold btn-lg">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>
                    ورود / ثبت‌نام
                </a>
                <a href="#services" class="btn btn-ghost btn-lg">مرور خدمات</a>
            </div>
            <div class="cta-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                ثبت‌نام رایگان است — هزینه فقط برای سفارشِ انتخابی شما
            </div>
        </div>
    </div>
</section>
</main>

{{-- ==================== فوتر ==================== --}}
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <a class="brand" href="{{ url('/') }}" aria-label="کافی‌نت آنلاین">
                    <span class="brand-mark">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>
                        </svg>
                        <span class="dot"></span>
                    </span>
                    <span class="brand-text">
                        <strong>{{ config('app.name', 'کافی‌نت آنلاین') }}</strong>
                        <span>سفارش آنلاین خدمات</span>
                    </span>
                </a>
                <p class="footer-brand-desc">
                    پلتفرم اتصال مشتریان به کافی‌نت‌ها؛ سفارش آنلاین خدمات با فرم‌ساز پویا، پرداخت امن، تخصیص هوشمند و گفتگوی لحظه‌ای.
                </p>
            </div>

            <div>
                <h4 class="footer-title">دسترسی سریع</h4>
                <nav class="footer-links" aria-label="لینک‌های سریع">
                    <a href="#services"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>خدمات</a>
                    <a href="#how"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>مراحل سفارش</a>
                    <a href="#features"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>مزایا</a>
                    <a href="#faq"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>سوالات متداول</a>
                    <a href="{{ route('app.auth') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>ورود / ثبت‌نام</a>
                </nav>
            </div>

            <div>
                <h4 class="footer-title">خدمات</h4>
                <nav class="footer-links" aria-label="لینک خدمات">
                    @forelse ($grouped as $group)
                        <a href="{{ route('app.services') }}#cat-{{ $group['category']->id }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                            {{ $group['category']->name }}
                        </a>
                    @empty
                        <a href="{{ route('app.services') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>کاتالوگ خدمات</a>
                    @endforelse
                    <a href="{{ route('app.services') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>همهٔ خدمات</a>
                </nav>
            </div>

            <div>
                <h4 class="footer-title">ارتباط با ما</h4>
                <div class="footer-contact">
                    <div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <span>
                            @if ($workStatus['enabled'])
                                ساعت کاری: {{ $workStatus['range'] }}<br>{{ $workStatus['open'] ? 'در حال حاضر باز هستیم' : 'در حال حاضر بسته هستیم' }}
                            @else
                                پاسخگویی: ۷ روز هفته، ۲۴ ساعته
                            @endif
                        </span>
                    </div>
                    <div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                        <span>پشتیبانی از طریق تیکت — بعد از ورود به حساب</span>
                    </div>
                    <div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                        <span>پلتفرم تحت وب — روی همهٔ دستگاه‌ها</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-bottom-inner">
                <span>© {{ fa_digits(now()->year) }} {{ config('app.name', 'کافی‌نت آنلاین') }} — تمامی حقوق محفوظ است.</span>
                <nav class="footer-panels" aria-label="ورود پنل‌ها">
                    <a class="fp-chip" href="{{ route('app.auth') }}">پنل مشتری</a>
                    <a class="fp-chip" href="{{ route('operator.login') }}">پنل اپراتور</a>
                    <a class="fp-chip" href="{{ route('admin.login') }}">پنل مدیریت</a>
                </nav>
            </div>
        </div>
    </div>
</footer>

{{-- دکمه بازگشت به بالا --}}
<button class="to-top" id="toTop" type="button" aria-label="بازگشت به بالای صفحه">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
</button>

<script src="{{ asset('assets/js/landing.js') }}?v=2" defer></script>
</body>
</html>
