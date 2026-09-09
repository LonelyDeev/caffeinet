@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'مستندات API')
@section('page-title', 'مستندات API مشتری (v1)')
@section('breadcrumb', 'پنل مدیریت کل ← مستندات API')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/api-docs.css') }}?v=13">
@endpush

@section('content')

@php
    $methodColors = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    $total = collect($groups)->sum(fn ($g) => count($g['items']));
@endphp

<div class="ad-tools">
    <label class="sr-only" for="ad-search">جستجوی اندپوینت</label>
    <input id="ad-search" type="search" class="field !py-2.5" placeholder="جستجو: مسیر، متد، توضیح…">
    <button type="button" id="ad-expand" class="btn-ghost !py-2.5 !px-4 !text-xs ui-press">بازکردن همه</button>
    <button type="button" id="ad-print" class="btn-ghost !py-2.5 !px-4 !text-xs ui-press">
        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/><path d="M6 9V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v5"/></svg>
        چاپ / PDF
    </button>
</div>

<div class="ad-wrap">

    {{-- ناوبری گروه‌ها --}}
    <nav class="ad-nav" aria-label="گروه‌های API">
        <p class="ad-nav-title">اندپوینت‌ها ({{ fa_digits($total) }})</p>
        @foreach ($groups as $g)
            <a href="#{{ $g['id'] }}" data-nav="{{ $g['id'] }}">
                {{ $g['title'] }}
                <span class="ad-nav-count">{{ fa_digits(count($g['items'])) }}</span>
            </a>
        @endforeach
    </nav>

    <div>
        {{-- سرآیند --}}
        <section class="ad-hero" id="ad-top">
            <h1>مرجع REST API نسخهٔ ۱ — اپ مشتری</h1>
            <p>
                همهٔ درخواست‌ها با هدر
                <code>Accept: application/json</code>
                و آدرس پایهٔ
                <code>https://YOUR-DOMAIN/api/v1</code>
                ارسال می‌شوند.
                اندپوینت‌های «توکن» نیازمند هدر
                <code>Authorization: Bearer &lt;token&gt;</code>
                هستند (توکن از otp/verify صادر می‌شود).
                سقف کلی: ۱۲۰ درخواست در دقیقه برای هر کاربر احرازشده.
                پاسخ‌ها همیشه JSON با کلید <code>message</code> برای متن فارسی خطا/موفقیت.
            </p>
        </section>

        {{-- گروه‌ها --}}
        @foreach ($groups as $g)
            <section class="ad-group" id="{{ $g['id'] }}" data-group>
                <div class="ad-group-head">
                    <span class="ad-ico">
                        @if ($g['icon'] === 'key')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>
                        @elseif ($g['icon'] === 'user')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        @elseif ($g['icon'] === 'map')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.1 6 5 10.4V4a1 1 0 0 1 1-1h6.1a1 1 0 0 1 1 1Z"/><path d="m14.1 6 4.9 2.2v9.3a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-1.4"/><path d="M21 12v7a1 1 0 0 1-2 0v-4.5"/></svg>
                        @elseif ($g['icon'] === 'orders')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/><circle cx="12" cy="12" r="1"/></svg>
                        @elseif ($g['icon'] === 'chat')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
                        @elseif ($g['icon'] === 'wallet')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                        @elseif ($g['icon'] === 'tickets')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 13a9 9 0 0 1 18 0"/><path d="M21 17v2a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Zm-18 0v2a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3Z"/></svg>
                        @elseif ($g['icon'] === 'bell')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.491 18 8A6 6 0 0 0 6 8c0 4.491-1.411 5.956-2.738 7.326"/></svg>
                        @else
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
                        @endif
                    </span>
                    <div>
                        <h2>{{ $g['title'] }}</h2>
                        <p>{{ $g['desc'] }}</p>
                    </div>
                </div>

                @foreach ($g['items'] as $ep)
                    <article class="ad-ep" data-ep>
                        <div class="ad-ep-head" data-ep-toggle role="button" tabindex="0"
                             aria-expanded="false" aria-label="جزئیات {{ $ep['path'] }}">
                            <span class="ad-method" data-m="{{ $ep['method'] }}">{{ $ep['method'] }}</span>
                            <span class="ad-path">{{ $ep['path'] }}</span>
                            <span class="ad-auth-pill" data-a="{{ $ep['auth'] ? '1' : '0' }}">{{ $ep['auth'] ? '🔒 توکن' : 'عمومی' }}</span>
                            <svg class="ad-chev size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </div>

                        <div class="ad-ep-body">
                            <p class="ad-ep-desc">{{ $ep['desc'] }}</p>

                            <div class="ad-ep-meta">
                                @if ($ep['rate'] && $ep['rate'] !== '—')
                                    <span class="ad-tag">نرخ: <b>{{ $ep['rate'] }}</b></span>
                                @endif
                                @if (! empty($ep['params']))
                                    <span class="ad-tag" dir="ltr">{{ $ep['params'] }}</span>
                                @endif
                            </div>

                            @if (! empty($ep['body']))
                                <div class="ad-code" data-code>
                                    <div class="ad-code-label">
                                        <span>REQUEST — {{ strtoupper($ep['method']) === 'GET' ? 'query' : 'body' }}</span>
                                        <button type="button" class="ad-copy" data-copy>کپی</button>
                                    </div>
                                    <pre data-raw="{{ json_encode($ep['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></pre>
                                </div>
                            @endif

                            @if (! empty($ep['response']))
                                <div class="ad-code" data-code>
                                    <div class="ad-code-label">
                                        <span>RESPONSE 200</span>
                                        <button type="button" class="ad-copy" data-copy>کپی</button>
                                    </div>
                                    <pre data-raw="{{ json_encode($ep['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}"></pre>
                                </div>
                            @endif

                            @if (! empty($ep['notes']))
                                <p class="ad-note">{{ $ep['notes'] }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>
        @endforeach

        {{-- کدهای خطا --}}
        <section class="ad-group ad-errors" id="errors" data-group>
            <div class="ad-group-head">
                <span class="ad-ico">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </span>
                <div>
                    <h2>کدهای خطا</h2>
                    <p>همهٔ خطاها JSON با متن فارسی — پیام در کلید message</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr><th scope="col">کد</th><th scope="col">معنی</th><th scope="col">نمونه</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>401</code></td><td>احراز هویت نشده / توکن نامعتبر</td><td><code>{"message": "احراز هویت نشده‌اید…"}</code></td></tr>
                    <tr><td><code>403</code></td><td>دسترسی به منبع دیگران / لینک امضاشدهٔ نامعتبر</td><td><code>{"message": "…"}</code></td></tr>
                    <tr><td><code>404</code></td><td>یافت نشد (عدم افشای وجود منبع)</td><td><code>{"message": "…"}</code></td></tr>
                    <tr><td><code>422</code></td><td>خطای اعتبارسنجی — کلید errors</td><td><code>{"message": "…", "errors": {"mobile": ["…"]}}</code></td></tr>
                    <tr><td><code>429</code></td><td>محدودیت نرخ — هدر Retry-After</td><td><code>{"message": "درخواست‌های شما بیش از حد مجاز…"}</code></td></tr>
                    <tr><td><code>500</code></td><td>خطای سرور (در پروداکشن گزارش به لاگ)</td><td><code>{"message": "خطای سرور"}</code></td></tr>
                </tbody>
            </table>
        </section>

        <div class="ad-empty" data-empty hidden>اندپوینتی با این عبارت پیدا نشد.</div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/api-docs/index.js') }}?v=14"></script>
@endpush
