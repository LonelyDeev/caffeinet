@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'لاگ سیستمی')
@section('page-title', 'لاگ سیستمی لاراول')
@section('breadcrumb', 'پنل مدیریت کل ← تنظیمات ← لاگ سیستمی')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/system-logs.css') }}?v=1">
@endpush

@section('content')

<div class="slg-stack">

    {{-- ================== هدر قهرمان ================== --}}
    <div class="card ui-lift animate-fade-up slg-hero">
        <div class="slg-hero-main">
            <span class="slg-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17l6-6-6-6"/><path d="M12 19h8"/></svg>
            </span>
            <div class="flex-1 min-w-0">
                <h1 class="slg-hero-title">لاگ سیستمی لاراول</h1>
                <p class="slg-hero-sub">
                    رخدادهای ثبت‌شده در فایل‌های لاگ سامانه (storage/logs) — خطاها، اخطارها و اطلاعات
                    به تفکیک <b>سطح</b> دسته‌بندی شده‌اند؛ با کلیک روی هر ردیف، جزئیات کامل و stack trace دیده می‌شود.
                </p>
            </div>
            <div class="slg-hero-side">
                <a href="{{ route('admin.settings.edit') }}" class="btn-ghost !py-2 !px-4 !text-xs ui-press">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    بازگشت به تنظیمات
                </a>
            </div>
        </div>

        {{-- آمار فایل انتخابی --}}
        @if ($summary)
            <div class="slg-stats">
                <div class="slg-stat">
                    <span class="slg-stat-num">{{ $summary['count_fa'] }}</span>
                    <span class="slg-stat-label">ورودی در این فایل</span>
                </div>
                <div class="slg-stat">
                    <span class="slg-stat-num">{{ $summary['size_fa'] }}</span>
                    <span class="slg-stat-label">حجم فایل (کیلوبایت)</span>
                </div>
                <div class="slg-stat slg-stat--time">
                    <span class="slg-stat-num">{{ $summary['modified_fa'] }}</span>
                    <span class="slg-stat-label">آخرین نوشت در فایل</span>
                </div>
            </div>
        @endif
    </div>

    @if (count($files) === 0)
        {{-- حالت خالی — هیچ فایل لاگی نیست --}}
        <div class="card ui-lift animate-fade-up slg-empty">
            <span class="slg-empty-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
            </span>
            <h2 class="slg-empty-title">هیچ فایل لاگی وجود ندارد</h2>
            <p class="slg-empty-sub">
                فعلاً هیچ خطا یا رخدادی در storage/logs ثبت نشده است — این خودش خبر خوبی است!
                با اولین رخداد (مثلاً خطا یا اخطار) فایل <span dir="ltr" class="font-mono">laravel.log</span> ساخته می‌شود.
            </p>
            <a href="{{ route('admin.settings.edit') }}" class="btn-primary btn-shine ui-press !py-2.5 px-7 !text-xs">بازگشت به تنظیمات</a>
        </div>
    @else

    {{-- ================== کارت فایل‌ها + عملیات ================== --}}
    <div class="card ui-lift animate-fade-up slg-files" style="animation-delay:.04s">
        <div class="slg-files-head">
            <b>فایل‌های لاگ</b>
            <span>{{ fa_number(count($files)) }} فایل — با کلیک انتخاب می‌شود</span>
        </div>
        <div class="slg-files-list">
            @foreach ($files as $f)
                <a href="{{ route('admin.settings.logs', ['file' => $f['name']]) }}"
                   class="slg-file {{ $selected && $selected['name'] === $f['name'] ? 'slg-file--on' : '' }}">
                    <span class="slg-file-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
                    </span>
                    <span class="slg-file-body">
                        <b dir="ltr">{{ $f['name'] }}</b>
                        <i>{{ fa_number($f['size'] / 1024) }} KB · آخرین نوشت {{ fa_date($f['modified_carbon'], 'Y/m/d H:i') }}</i>
                    </span>
                    @if ($selected && $selected['name'] === $f['name'])
                        <span class="slg-file-on-badge">در حال نمایش</span>
                    @endif
                </a>
            @endforeach
        </div>

        @if ($selected)
            <div class="slg-file-actions">
                <span class="slg-file-actions-info">
                    فایل فعال: <b dir="ltr">{{ $selected['name'] }}</b>
                </span>
                <button type="button" id="slg-clear-btn" class="btn-ghost ui-press !py-2 !px-4 !text-xs" title="محتوای فایل پاک می‌شود ولی خود فایل می‌ماند">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    خالی‌کردن فایل
                </button>
                <button type="button" id="slg-delete-btn" class="btn-ghost ui-press !py-2 !px-4 !text-xs !text-red-600" title="حذف کامل فایل از دیسک">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    حذف کامل فایل
                </button>
            </div>
        @endif
    </div>

    @if ($summary && $summary['truncated'])
        <div class="card ui-lift animate-fade-up slg-note" role="status">
            فایل بزرگ است — فقط <b>آخرین ۸ مگابایت</b> آن خوانده و نمایش داده می‌شود (برای سرعت). برای دیدن کامل، فایل را خالی یا حذف کنید.
        </div>
    @endif

    {{-- ================== فیلتر سطح + جستجو + جدول ================== --}}
    <section class="card ui-lift animate-fade-up overflow-hidden" style="animation-delay:.08s" id="slg-main" data-file="{{ $selected ? $selected['name'] : '' }}">

        {{-- چیپ‌های سطح (دسته‌بندی) --}}
        <div class="slg-levels" role="group" aria-label="فیلتر سطح لاگ">
            <button type="button" class="slg-level slg-level--all is-on" data-level="">
                <span class="slg-level-dot"></span>
                همه
                <span class="slg-level-count">{{ fa_number($summary['count'] ?? 0) }}</span>
            </button>
            @foreach ($levelStats as $lvl => $count)
                <button type="button" class="slg-level slg-level--{{ $levelMeta[$lvl][1] ?? 'stone' }}" data-level="{{ $lvl }}">
                    <span class="slg-level-dot"></span>
                    {{ $levelMeta[$lvl][0] ?? $lvl }}
                    <span class="slg-level-count">{{ fa_number($count) }}</span>
                </button>
            @endforeach
        </div>

        {{-- نوار ابزار: جستجو + تعداد در صفحه --}}
        <div class="adm-card-head slg-toolbar">
            <div class="relative flex-1 min-w-44 max-w-xs">
                <input id="slg-q" type="search" class="field !py-2.5 pl-10" placeholder="جستجو در متن و stack لاگ‌ها..." aria-label="جستجو در لاگ‌ها">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <label class="slg-perpage">
                <span>نمایش</span>
                <select id="slg-perpage" class="field !py-2 !px-2 !text-xs cursor-pointer" aria-label="تعداد ردیف در صفحه">
                    <option value="10">۱۰</option>
                    <option value="25" selected>۲۵</option>
                    <option value="50">۵۰</option>
                    <option value="100">۱۰۰</option>
                </select>
                <span>ورودی</span>
            </label>
        </div>

        {{-- جدول --}}
        <div class="table-wrap">
            <div class="overflow-x-auto">
                <table class="table-panel table-modern">
                <thead>
                    <tr>
                        <th class="!w-28">سطح</th>
                        <th class="!w-24">محیط</th>
                        <th class="!w-44">زمان</th>
                        <th>متن رخداد</th>
                        <th class="!w-20 text-center">جزئیات</th>
                    </tr>
                </thead>
                <tbody id="slg-rows">
                    <tr><td colspan="5" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
                </tbody>
            </table>
        </div>

        <div id="slg-pagination" class="adm-table-foot text-xs text-stone-500"></div>
    </section>
    @endif
</div>

{{-- مودال جزئیات ورودی لاگ --}}
<div id="slg-detail-modal" class="ui-modal-backdrop hidden">
    <div data-close class="absolute inset-0" aria-hidden="true"></div>
    <div class="ui-modal adm-modal-lg adm-modal-text-start" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="slg-d-title">
        <div class="adm-modal-head">
            <h3 class="text-sm font-extrabold text-stone-800" id="slg-d-title">جزئیات رخداد</h3>
            <button type="button" class="adm-modal-x" data-close aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="adm-modal-body">
            <div class="slg-d-meta" id="slg-d-meta"></div>
            <p class="lbl !mt-4 !mb-1.5">متن کامل رخداد</p>
            <pre class="slg-d-pre" dir="ltr"><code id="slg-d-msg">—</code></pre>
            <div id="slg-d-trace-zone" class="hidden">
                <p class="lbl !mt-4 !mb-1.5">Stack trace / ادامهٔ متن</p>
                <pre class="slg-d-pre slg-d-pre--trace" dir="ltr"><code id="slg-d-trace">—</code></pre>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/system-logs/index.js') }}?v=1"></script>
@endpush
