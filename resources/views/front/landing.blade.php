@extends('layouts.app')

@section('title', 'کافی‌نت آنلاین — پلتفرم خدمات آنلاین')

@section('content')
<main class="relative min-h-screen flex flex-col overflow-x-hidden">

    {{-- بافت پس‌زمینه --}}
    <div class="pointer-events-none fixed inset-0" aria-hidden="true">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_45%_at_50%_-10%,rgba(196,127,61,0.22),transparent)]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_40%_30%_at_85%_20%,rgba(211,156,92,0.08),transparent)]"></div>
        <div class="absolute inset-0 opacity-[0.05] bg-[repeating-linear-gradient(135deg,transparent_0_8px,rgba(226,186,133,0.5)_8px_9px)]"></div>
    </div>

    {{-- هدر --}}
    <header class="sticky top-0 z-40 glass-warm">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
            <a href="{{ url('/') }}" class="flex items-center gap-3 group">
                <span class="relative grid place-items-center size-10 rounded-2xl bg-gradient-to-br from-brand-400 to-brand-700 shadow-lg shadow-brand-900/40 transition-transform duration-300 group-hover:scale-105 group-hover:rotate-3">
                    {{-- آیکون فنجان قهوه --}}
                    <svg class="size-5 text-brand-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17 8h1a4 4 0 1 1 0 8h-1"/>
                        <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>
                    </svg>
                    <span class="absolute -top-1 -left-1 size-2.5 rounded-full bg-emerald-400 animate-pulse-dot" aria-hidden="true"></span>
                </span>
                <span class="leading-tight">
                    <strong class="block text-base font-extrabold tracking-tight">کافی‌نت آنلاین</strong>
                    <span class="block text-[11px] text-brand-300/80 font-medium">پلتفرم خدمات آنلاین</span>
                </span>
            </a>

            <div class="flex items-center gap-2">
                <span class="hidden sm:inline-flex items-center gap-2 rounded-full border border-emerald-300/25 bg-emerald-950/40 px-3.5 py-1.5 text-xs font-semibold text-emerald-200">
                    <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse-dot"></span>
                    فاز ۵ — اپ مشتری، سفارش و پرداخت آنلاین آماده است
                </span>
            </div>
        </div>
    </header>

    {{-- قهرمان صفحه --}}
    <section class="relative z-10 mx-auto w-full max-w-6xl px-4 sm:px-6 pt-16 pb-10 sm:pt-24 sm:pb-14">
        <div class="grid lg:grid-cols-[1.15fr_.85fr] gap-10 items-center">

            <div class="text-center lg:text-right">
                <p class="animate-fade-up inline-flex items-center gap-2 rounded-full border border-brand-300/25 bg-brand-950/50 px-4 py-1.5 text-xs font-medium text-brand-200 mb-6">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h2"/><path d="m4.9 4.9 2.9 2.9"/></svg>
                    استارتاپ Laravel 13 · Sanctum · Spatie Permission
                </p>

                <h1 class="animate-fade-up delay-1 text-4xl sm:text-5xl xl:text-6xl font-extrabold leading-[1.25] tracking-tight">
                    خدمات کافی‌نت،
                    <span class="text-gold-gradient">آنلاین و حرفه‌ای</span>
                </h1>

                <p class="animate-fade-up delay-2 mt-6 max-w-xl mx-auto lg:mx-0 text-brand-100/75 text-base sm:text-lg leading-9 font-light">
                    پلتفرمی برای اتصال مشتریان به کافی‌نت‌ها؛ با
                    <strong class="font-semibold text-brand-100">فرم‌ساز پویا</strong>،
                    <strong class="font-semibold text-brand-100">تخصیص هوشمند سفارش</strong>،
                    <strong class="font-semibold text-brand-100">چت لحظه‌ای</strong>،
                    <strong class="font-semibold text-brand-100">کیف پول و کمیسیون‌ساز</strong>
                    برای سازمان‌ها، کافی‌نت‌ها و اپراتورها.
                </p>

                <div class="animate-fade-up delay-3 mt-9 flex flex-wrap justify-center lg:justify-start gap-3">
                    <a href="{{ route('app.auth') }}" class="group inline-flex items-center gap-2.5 rounded-2xl bg-gradient-to-l from-brand-500 to-brand-600 px-6 py-3.5 text-sm font-bold text-white shadow-xl shadow-brand-900/40 transition-all duration-300 hover:shadow-2xl hover:shadow-brand-800/50 hover:-translate-y-0.5 active:translate-y-0">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/></svg>
                        ورود / ثبت‌نام مشتری
                    </a>
                    <a href="#panels" class="group inline-flex items-center gap-2.5 rounded-2xl border border-brand-300/30 bg-brand-950/40 px-6 py-3.5 text-sm font-bold text-brand-100 transition-all duration-300 hover:bg-brand-900/60 hover:-translate-y-0.5">
                        مشاهده پنل‌ها
                        <svg class="size-4 transition-transform duration-300 group-hover:-translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                    </a>
                    <a href="#roadmap" class="inline-flex items-center gap-2.5 rounded-2xl border border-brand-300/30 bg-brand-950/40 px-6 py-3.5 text-sm font-bold text-brand-100 transition-all duration-300 hover:bg-brand-900/60 hover:-translate-y-0.5">
                        نقشه راه پروژه
                    </a>
                </div>
            </div>

            {{-- فنجان متحرک --}}
            <div class="hidden lg:flex justify-center items-center animate-fade-up delay-2">
                <div class="relative animate-float-soft">
                    <div class="relative size-56 rounded-[3rem] glass-warm grid place-items-center">
                        <div class="absolute inset-0 rounded-[3rem] bg-gradient-to-br from-brand-300/10 to-transparent"></div>
                        {{-- بخار فنجان --}}
                        <div class="absolute top-10 left-1/2 -translate-x-1/2 flex gap-2.5" aria-hidden="true">
                            <span class="block w-1.5 h-8 rounded-full bg-brand-200/70 blur-[2px] animate-steam"></span>
                            <span class="block w-1.5 h-8 rounded-full bg-brand-200/70 blur-[2px] animate-steam" style="animation-delay:.7s"></span>
                            <span class="block w-1.5 h-8 rounded-full bg-brand-200/70 blur-[2px] animate-steam" style="animation-delay:1.4s"></span>
                        </div>
                        <svg class="size-24 text-brand-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17 8h1a4 4 0 1 1 0 8h-1"/>
                            <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>
                            <path d="M6 2v2"/><path d="M10 2v2"/><path d="M14 2v2"/>
                        </svg>
                    </div>
                    <div class="absolute -bottom-5 left-1/2 -translate-x-1/2 w-40 h-6 rounded-full bg-brand-950/80 blur-xl" aria-hidden="true"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- پنل‌ها --}}
    <section id="panels" class="relative z-10 mx-auto w-full max-w-6xl px-4 sm:px-6 py-12 scroll-mt-20">
        <div class="animate-fade-up flex items-end justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">پنل‌های سیستم</h2>
                <p class="mt-2 text-sm text-brand-200/60 font-light">هر نقش، داشبورد اختصاصی خودش را دارد</p>
            </div>
            <span class="text-xs text-brand-300/70 font-medium">۵ سطح دسترسی مستقل</span>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach([
                [
                    'title' => 'مدیریت کل',
                    'desc' => 'کنترل کامل پلتفرم؛ تنظیم کمیسیون‌ها، تأیید کافی‌نت‌ها، مدیریت خدمات و گزارش‌های مالی',
                    'icon' => 'shield',
                    'tag' => 'Super Admin',
                ],
                [
                    'title' => 'سازمان‌ها',
                    'desc' => 'مشاهده کافی‌نت‌های معرفی‌شده، کیف پول، برداشت وجه و معرفی کافی‌نت جدید',
                    'icon' => 'building',
                    'tag' => 'Organization',
                ],
                [
                    'title' => 'مدیر کافی‌نت',
                    'desc' => 'مدیریت کارمندان، تنظیم مدل حقوق (درصدی/ماهیانه/ثابت)، سفارش‌ها و عملکرد',
                    'icon' => 'store',
                    'tag' => 'Coffee-net',
                ],
                [
                    'title' => 'اپراتور / کارمند',
                    'desc' => 'پنل اختصاصی اپراتور؛ سفارش‌های سپرده‌شده، آمار شخصی و به‌زودی چت با مشتری',
                    'icon' => 'headset',
                    'tag' => 'Operator',
                    'login' => 'operator',
                ],
                [
                    'title' => 'API مشتری',
                    'desc' => 'ثبت‌نام OTP، کاتالوگ خدمات، ثبت سفارش، پرداخت و پیگیری — برای اپ مشتری',
                    'icon' => 'phone',
                    'tag' => 'REST API + Web',
                    'login' => 'app',
                ],
            ] as $panel)
                <article class="animate-fade-up group relative rounded-3xl glass-warm p-6 transition-all duration-300 hover:-translate-y-1.5 hover:border-brand-300/40 hover:bg-brand-950/70">
                    <div class="flex items-start justify-between">
                        <span class="grid place-items-center size-12 rounded-2xl bg-gradient-to-br from-brand-300/20 to-brand-700/30 border border-brand-300/20 text-brand-200 transition-all duration-300 group-hover:scale-110 group-hover:from-brand-300/30">
                            @if($panel['icon'] === 'shield')
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
                            @elseif($panel['icon'] === 'building')
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="4" height="16" x="4" y="2" rx="1"/><rect width="4" height="16" x="10" y="2" rx="1"/><path d="M20 4v14"/><path d="M16 4h.01"/><path d="M16 8h.01"/><path d="M16 12h.01"/><path d="M2 20h20"/></svg>
                            @elseif($panel['icon'] === 'store')
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h20"/><path d="M12 7v5"/><path d="M6 12h.01"/><path d="M18 12h.01"/><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M3 22V11a2 2 0 0 1 2-5h14a2 2 0 0 1 2 5v11"/></svg>
                            @elseif($panel['icon'] === 'headset')
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg>
                            @else
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/></svg>
                            @endif
                        </span>
                        <span class="text-[10px] font-mono tracking-wide text-brand-300/50 border border-brand-300/20 rounded-full px-2.5 py-1">{{ $panel['tag'] }}</span>
                    </div>
                    <h3 class="mt-5 text-lg font-bold">{{ $panel['title'] }}</h3>
                    <p class="mt-2.5 text-sm leading-7 text-brand-100/60 font-light">{{ $panel['desc'] }}</p>
                    <div class="mt-5 flex items-center gap-2 text-xs font-semibold text-emerald-300">
                        <span class="size-1.5 rounded-full bg-emerald-300/80"></span>
                        @if(($panel['login'] ?? '') === 'app')
                            فعال — <a href="{{ route('app.auth') }}" class="underline decoration-dotted hover:text-emerald-200">ورود به اپ مشتری</a>
                        @elseif(($panel['login'] ?? '') === 'operator')
                            فعال — <a href="{{ route('operator.login') }}" class="underline decoration-dotted hover:text-emerald-200">ورود به پنل اپراتور</a>
                        @else
                            فعال — <a href="{{ route('admin.login') }}" class="underline decoration-dotted hover:text-emerald-200">ورود به پنل مدیریت کل</a>
                        @endif
                    </div>
                </article>
            @endforeach

            {{-- کارت وضعیت زیرساخت --}}
            <article class="animate-fade-up rounded-3xl border border-emerald-300/20 bg-emerald-950/20 p-6">
                <h3 class="text-lg font-bold flex items-center gap-2.5">
                    <span class="grid place-items-center size-8 rounded-xl bg-emerald-400/15 text-emerald-300">
                        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    زیرساخت آماده
                </h3>
                <ul class="mt-4 space-y-2.5 text-sm text-brand-100/70">
                    <li class="flex items-center gap-2"><span class="text-emerald-300">✓</span> PHP 8.5 · Laravel 13.x</li>
                    <li class="flex items-center gap-2"><span class="text-emerald-300">✓</span> Sanctum · Spatie Permission</li>
                    <li class="flex items-center gap-2"><span class="text-emerald-300">✓</span> Tailwind 4 · Vite · RTL فارسی</li>
                    <li class="flex items-center gap-2"><span class="text-emerald-300">✓</span> ساختار پوشه‌ای تفکیک‌شده پنل‌ها</li>
                </ul>
            </article>
        </div>
    </section>

 {{--   --}}{{-- نقشه راه --}}{{--
    <section id="roadmap" class="relative z-10 mx-auto w-full max-w-6xl px-4 sm:px-6 py-12 scroll-mt-20">
        <div class="animate-fade-up mb-8">
            <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">نقشه راه — ۱۲ فاز</h2>
            <p class="mt-2 text-sm text-brand-200/60 font-light">هر فاز مستقل اجرا می‌شود؛ ترتیب و محتوا با انتخاب شما</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
            @foreach([
                ['فاز ۱', 'پایه داده و دسترسی‌ها', 'RBAC، اسکیمای کامل، استان/شهر', true],
                ['فاز ۲', 'سازمان‌ها و کافی‌نت‌ها', 'ثبت، تأیید، کیف پول سازمان', true],
                ['فاز ۳', 'کارمندان و حقوق', 'مدل‌های درصدی، ماهیانه، ثابت', true],
                ['فاز ۴', 'خدمات و فرم‌ساز', 'دسته‌بندی، هزینه‌ها، فرم داینامیک', true],
                ['فاز ۵', 'API مشتری', 'OTP، پروفایل، سفارش، پرداخت', true],
                ['فاز ۶', 'موتور تخصیص سفارش', 'پخش ۶۰ ثانیه‌ای و قفل سفارش', true],
                ['فاز ۷', 'چت تلگرام‌گونه', 'متن، تصویر، صدا، ویدیو', true],
                ['فاز ۸', 'مالی و کمیسیون', 'تسویه، پورسانت، گزارش مالی', true],
                ['فاز ۹', 'داشبوردها و گزارش', 'نمودارها، لاگ فعالیت‌ها', true],
                ['فاز ۱۰', 'تیکت و اعلان‌ها', 'پشتیبانی، شکایات، پیامک', true],
                ['فاز ۱۱', 'امنیت و بهینه‌سازی', 'رمزنگاری مدارک، محدودسازی‌ها', true],
                ['فاز ۱۲', 'تست و مستندسازی', 'آماده‌سازی لانچ', true],
            ] as $phase)
                <div class="animate-fade-up flex items-center gap-3.5 rounded-2xl border p-4 transition-all duration-300 hover:bg-brand-950/50 {{ $phase[3] ? 'border-emerald-300/25 bg-emerald-950/15' : 'border-brand-300/15 bg-brand-950/30' }}">
                    <span class="shrink-0 grid place-items-center size-9 rounded-xl text-xs font-extrabold {{ $phase[3] ? 'bg-emerald-400/20 text-emerald-300' : 'bg-brand-300/10 text-brand-300/80' }}">{{ $phase[0] }}</span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold truncate">{{ $phase[1] }}</h3>
                        <p class="text-xs text-brand-200/50 mt-0.5 truncate">{{ $phase[2] }}</p>
                    </div>
                    @if($phase[3])
                        <span class="ms-auto shrink-0 text-emerald-300"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg></span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
--}}
    {{-- فوتر چسبان --}}
    <footer class="relative z-10 mt-auto border-t border-brand-300/10 glass-warm">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 h-14 flex items-center justify-between text-xs text-brand-200/50">
            <span class="font-medium">ساخته‌شده با Laravel 13 · فاز ۵ از ۱۲ تکمیل شد</span>
            <span class="font-mono tracking-wide" dir="ltr">{{ config('app.name') }}</span>
        </div>
    </footer>
</main>
@endsection
