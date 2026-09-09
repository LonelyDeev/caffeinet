@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'وضعیت سیستم')
@section('page-title', 'وضعیت سیستم و نگهداشت')
@section('breadcrumb', 'پنل مدیریت کل ← وضعیت سیستم')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/system.css') }}?v=13">
@endpush

@section('content')

@php
    $encPct = $files['total'] > 0 ? round($files['encrypted'] * 100 / $files['total']) : 100;
    $last = $lastCleanup;
    $removedTotal = $last ? array_sum($last['removed'] ?? []) : null;
@endphp

{{-- ================== کارت‌های آماری ================== --}}
<section class="sy-stats" aria-label="خلاصهٔ وضعیت">
    <div class="sy-stat" data-tone="secure">
        <strong>{{ fa_digits($files['encrypted']) }}</strong>
        <span>فایل رمزنگاری‌شده روی دیسک</span>
    </div>
    <div class="sy-stat" data-tone="plain">
        <strong>{{ fa_digits($files['plain']) }}</strong>
        <span>فایل خام (نیازمند رمزنگاری)</span>
    </div>
    <div class="sy-stat" data-tone="storage">
        <strong>{{ fa_digits(round($files['bytes'] / 1024)) }}<small class="text-[10px] font-semibold"> KB</small></strong>
        <span>حجم فایل‌های خصوصی</span>
    </div>
    <div class="sy-stat" data-tone="cleanup">
        <strong>{{ $last ? fa_date($last['ran_at'] ?? null, 'Y/m/d H:i') : '—' }}</strong>
        <span>آخرین پاکسازی دوره‌ای{{ $removedTotal !== null ? ' ('.fa_digits($removedTotal).' ردیف حذف)' : '' }}</span>
    </div>
</section>

<div class="sy-grid">

    {{-- ================== چک‌لیست امنیت ================== --}}
    <section class="sy-card" aria-label="امنیت">
        <div class="sy-card-head">
            <span class="sy-ico">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
            </span>
            <div>
                <h2>امنیت و هاردنینگ</h2>
                <p>وضعیت امنیتی بر اساس محیط فعلی — موارد زرد فقط در پروداکشن مسئله‌اند.</p>
            </div>
        </div>

        @foreach ($security as $check)
            <div class="sy-check" data-ok="{{ $check['ok'] ? '1' : '0' }}">
                <span class="sy-check-ico">
                    @if ($check['ok'])
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    @else
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z"/></svg>
                    @endif
                </span>
                <div>
                    <div class="sy-check-title">{{ $check['label'] }}</div>
                    <div class="sy-check-hint">{{ $check['hint'] }}</div>
                </div>
            </div>
        @endforeach
    </section>

    {{-- ================== رمزنگاری فایل‌ها ================== --}}
    <section class="sy-card" aria-label="رمزنگاری">
        <div class="sy-card-head">
            <span class="sy-ico">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z"/><path d="M12 12v4"/><path d="M12 9v.01"/></svg>
            </span>
            <div>
                <h2>رمزنگاری مدارک روی دیسک</h2>
                <p>AES-256-GCM — مدارک سفارش، پیوست گفتگو و پیوست تیکت</p>
            </div>
        </div>

        <div class="sy-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $encPct }}" aria-label="پوشش رمزنگاری">
            <div class="sy-meter-fill" data-enc-fill style="width: {{ $encPct }}%"></div>
        </div>
        <div class="sy-meter-label">
            <span>پوشش رمزنگاری: <b data-enc-pct>{{ fa_digits($encPct) }}٪</b></span>
            <span dir="ltr">{{ fa_digits($files['encrypted']) }} / {{ fa_digits($files['total']) }}</span>
        </div>

        <div class="sy-check {{ $files['plain'] === 0 ? '' : 'mt-2' }}" data-ok="{{ $files['plain'] === 0 ? '1' : '0' }}">
            <span class="sy-check-ico">
                @if ($files['plain'] === 0)
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                @else
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z"/></svg>
                @endif
            </span>
            <div>
                <div class="sy-check-title" data-enc-state>
                    {{ $files['plain'] === 0 ? 'همهٔ فایل‌ها رمزنگاری‌شده‌اند' : fa_digits($files['plain']).' فایل هنوز خام است' }}
                </div>
                <div class="sy-check-hint">فایل‌های جدید هنگام بارگذاری خودکار رمز می‌شوند؛ کلید از FILE_ENCRYPTION_KEY یا APP_KEY.</div>
            </div>
        </div>

        @if ($files['plain'] > 0)
            <div class="sy-actions">
                <button type="button" id="sy-encrypt-btn" class="btn-primary btn-shine !py-2.5 !px-4 !text-xs ui-press" data-url="{{ route('admin.system.encrypt') }}">
                    رمزنگاری فایل‌های باقی‌مانده
                </button>
            </div>
        @endif
    </section>

    {{-- ================== زمان‌بندی ================== --}}
    <section class="sy-card" aria-label="زمان‌بندی">
        <div class="sy-card-head">
            <span class="sy-ico">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </span>
            <div>
                <h2>زمان‌بندی خودکار</h2>
                <p>برای اجرای زنده، schedule:work باید همیشه فعال باشد (راهنمای دپلوی)</p>
            </div>
        </div>

        @foreach ($scheduler as $task)
            <div class="sy-sched">
                <span class="sy-sched-cron">{{ $task['expression'] }}</span>
                <span class="sy-sched-desc">
                    {{ $task['description'] }}
                    <span class="sy-sched-note" dir="ltr">artisan {{ $task['command'] }}</span>
                    @if ($task['next'])
                        <span class="sy-sched-note">اجرای بعدی: {{ $task['next'] }}</span>
                    @endif
                </span>
            </div>
        @endforeach

        <div class="sy-report" style="margin-top: 0.8rem">
            <div class="sy-report-line">
                <span>سلامت سرویس</span>
                <b>
                    <a href="/up" target="_blank" class="text-emerald-600 hover:underline" dir="ltr">/up ✓</a>
                </b>
            </div>
        </div>
    </section>

    {{-- ================== پاکسازی دوره‌ای ================== --}}
    <section class="sy-card" aria-label="پاکسازی">
        <div class="sy-card-head">
            <span class="sy-ico">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
            </span>
            <div>
                <h2>پاکسازی دوره‌ای</h2>
                <p>حذف داده‌های موقت و لاگ‌های قدیمی بر اساس نگهداشت زیر</p>
            </div>
        </div>

        <form id="sy-retention-form" data-url="{{ route('admin.system.retention') }}">
            @csrf
            <div class="sy-retention">
                <div>
                    <label for="ret-read">اعلان خوانده‌شده (روز)</label>
                    <input id="ret-read" name="notifications_read" type="number" min="1" max="3650" class="field !py-2.5 font-mono" value="{{ $retention['notifications_read'] }}" required>
                </div>
                <div>
                    <label for="ret-unread">اعلان خوانده‌نشده (روز)</label>
                    <input id="ret-unread" name="notifications_unread" type="number" min="1" max="3650" class="field !py-2.5 font-mono" value="{{ $retention['notifications_unread'] }}" required>
                </div>
                <div>
                    <label for="ret-sms">لاگ پیامک (روز)</label>
                    <input id="ret-sms" name="sms_logs" type="number" min="1" max="3650" class="field !py-2.5 font-mono" value="{{ $retention['sms_logs'] }}" required>
                </div>
                <div>
                    <label for="ret-audit">لاگ فعالیت (روز)</label>
                    <input id="ret-audit" name="audit_logs" type="number" min="7" max="3650" class="field !py-2.5 font-mono" value="{{ $retention['audit_logs'] }}" required>
                </div>
            </div>

            <div class="sy-actions">
                <button type="submit" class="btn-primary btn-shine !py-2.5 !px-4 !text-xs ui-press">ذخیرهٔ نگهداشت</button>
                <button type="button" id="sy-cleanup-btn" class="btn-ghost !py-2.5 !px-4 !text-xs ui-press" data-url="{{ route('admin.system.cleanup') }}">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                    اجرای پاکسازی الان
                </button>
            </div>
        </form>

        <div class="mt-4" data-cleanup-report>
            @include('back.admin.system._report', ['last' => $last])
        </div>
    </section>

    {{-- ================== منابع ================== --}}
    <section class="sy-card" aria-label="منابع">
        <div class="sy-card-head">
            <span class="sy-ico">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </span>
            <div>
                <h2>مصرف منابع</h2>
                <p>دیتابیس و لاگ — روتیشن لاگ خودکار بالای ۱۰ مگابایت</p>
            </div>
        </div>

        <div class="sy-kv"><span>فایل دیتابیس (SQLite)</span><b>{{ fa_digits(round($resources['database'] / 1024)) }} KB</b></div>
        <div class="sy-kv"><span>لاگ فعال لاراول</span><b>{{ fa_digits(round($resources['log'] / 1024 / 1024, 1)) }} MB</b></div>
        <div class="sy-kv"><span>بایگانی لاگ (۵ نسخهٔ اخیر)</span><b>{{ fa_digits($resources['log_archives']) }} فایل</b></div>
    </section>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/system/index.js') }}?v=13"></script>
@endpush
