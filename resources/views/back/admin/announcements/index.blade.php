@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'اطلاعیه‌های سامانه')
@section('page-title', 'اطلاعیه‌های سامانه')
@section('breadcrumb', 'پنل مدیریت کل ← اطلاعیه‌ها')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/announcements.css') }}?v=16">
@endpush

@section('content')

<div class="an-stack">

    {{-- ================== هدر ================== --}}
    <div class="card ui-lift animate-fade-up an-hero">
        <div class="an-hero-main">
            <span class="an-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
            </span>
            <div class="flex-1 min-w-0">
                <h1 class="an-hero-title">اطلاعیه‌های سامانه</h1>
                <p class="an-hero-sub">
                    ارسال اطلاعیه با <b>متن، تصویر یا ویدیو</b> به پنل‌ها یا مشتریان —
                    هر مخاطب یک بار آن را به شکل مودال زیبا می‌بیند و وضعیت «دیده‌شده» ثبت می‌شود.
                </p>
            </div>
            <div class="an-hero-side">
                <button type="button" id="btn-new-announcement" class="btn-primary btn-shine ui-press !py-2.5">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    اطلاعیه جدید
                </button>
            </div>
        </div>

        <div class="an-toolbar">
            <div class="an-cats" role="tablist" aria-label="فیلتر مخاطب">
                <button type="button" class="an-cat is-active" data-filter="all">همه <span class="an-cat-count" id="cnt-all">۰</span></button>
                <button type="button" class="an-cat" data-filter="customers">مشتریان</button>
                <button type="button" class="an-cat" data-filter="panels">پنل‌ها</button>
                <button type="button" class="an-cat" data-filter="active">فعال</button>
            </div>
            <div class="an-toolbar-left">
                <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200" id="stat-active">۰ فعال</span>
                <span class="badge bg-stone-100 text-stone-500 border border-stone-200" id="stat-total">۰ اطلاعیه</span>
            </div>
        </div>
    </div>

    {{-- ================== لیست ================== --}}
    <div id="an-list" class="an-grid">
        <div class="an-loading"><span class="size-4 border-2 border-amber-400/40 border-t-amber-500 rounded-full animate-spin inline-block align-middle me-2"></span>در حال بارگذاری…</div>
    </div>

    {{-- حالت خالی --}}
    <div id="an-empty" class="ui-empty hidden">
        <span class="ui-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
        </span>
        <p class="font-bold text-sm">هنوز اطلاعیه‌ای ثبت نشده است</p>
        <p class="text-xs mt-1">اولین اطلاعیه را برای مشتریان یا پنل‌ها ارسال کنید.</p>
        <button type="button" class="btn-primary btn-shine ui-press !py-2.5 mt-4" onclick="document.getElementById('btn-new-announcement').click()">
            ارسال اولین اطلاعیه
        </button>
    </div>
</div>

{{-- ================== مودال ایجاد/ویرایش ================== --}}
<div id="an-modal" class="ui-modal-backdrop hidden">
    <div data-close-an class="absolute inset-0" aria-hidden="true"></div>
    <form id="an-form" class="ui-modal adm-modal-text-start !max-w-2xl" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="an-modal-title" novalidate>
        <input type="hidden" id="an-id" value="">

        <div class="adm-modal-head">
            <h3 id="an-modal-title" class="text-sm font-extrabold text-stone-800 flex items-center gap-2">
                <svg class="size-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                <span id="an-modal-title-text">اطلاعیه جدید</span>
            </h3>
            <button type="button" class="adm-modal-x" data-close-an aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">

            {{-- عنوان --}}
            <div>
                <label class="lbl" for="an-title">عنوان اطلاعیه <span class="text-rose-500">*</span></label>
                <input id="an-title" class="field" maxlength="150" placeholder="مثلاً: به‌روزرسانی ساعت کاری سامانه">
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="title"></p>
            </div>

            {{-- متن --}}
            <div>
                <label class="lbl" for="an-body">متن اطلاعیه</label>
                <textarea id="an-body" rows="4" class="field !leading-7" maxlength="4000" placeholder="متن کامل اطلاعیه… (اگر تصویر یا ویدیو دارید، متن اختیاری است)"></textarea>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="body"></p>
            </div>

            {{-- نوع رسانه --}}
            <div>
                <p class="lbl mb-2">نوع رسانه <span class="font-normal text-stone-400">— اطلاعیه با متن، تصویر یا ویدیو نمایش داده می‌شود</span></p>
                <div class="an-pick-grid" id="an-media-picks" role="radiogroup" aria-label="نوع رسانه">
                    <button type="button" class="st-prov-card is-selected" data-media="none" role="radio" aria-checked="true">
                        <span class="st-prov-tile st-tile--local" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h12"/></svg>
                        </span>
                        <span class="st-prov-name">فقط متن</span>
                        <span class="st-prov-sub">بدون رسانه</span>
                        <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    </button>
                    <button type="button" class="st-prov-card" data-media="image" role="radio" aria-checked="false">
                        <span class="st-prov-tile st-tile--zarinpal" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
                        </span>
                        <span class="st-prov-name">تصویر</span>
                        <span class="st-prov-sub">JPG / PNG / WebP — ۱۵MB</span>
                        <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    </button>
                    <button type="button" class="st-prov-card" data-media="video" role="radio" aria-checked="false">
                        <span class="st-prov-tile st-tile--mellat" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 13 5.2-3.1a.6.6 0 0 1 .8.5v3.2a.6.6 0 0 1-.8.5L16 11"/><rect width="14" height="10" x="2" y="7" rx="2"/></svg>
                        </span>
                        <span class="st-prov-name">ویدیو</span>
                        <span class="st-prov-sub">آپلود MP4 یا لینک مستقیم</span>
                        <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    </button>
                </div>
                <input type="hidden" id="an-media-type" value="none">
            </div>

            {{-- آپلود تصویر --}}
            <div id="an-image-group" class="an-media-box hidden">
                <label class="lbl" for="an-image-file">تصویر اطلاعیه</label>
                <div class="an-upzone" id="an-image-zone" role="button" tabindex="0" aria-label="انتخاب تصویر">
                    <input type="file" id="an-image-file" accept=".jpg,.jpeg,.png,.webp,.gif" hidden>
                    <span class="an-up-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 8 5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg>
                    </span>
                    <span class="an-up-txt">
                        <strong>انتخاب تصویر یا رها کردن در اینجا</strong>
                        <small>JPG، PNG، WebP — حداکثر ۱۵ مگابایت</small>
                    </span>
                </div>
                <div class="an-media-preview hidden" id="an-image-preview">
                    <img id="an-image-preview-img" src="" alt="پیش‌نمایش تصویر اطلاعیه">
                    <button type="button" class="an-media-rm" id="an-image-remove" aria-label="حذف تصویر">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="media_file"></p>
            </div>

            {{-- ویدیو --}}
            <div id="an-video-group" class="an-media-box hidden">
                <div class="an-video-tabs" role="tablist" aria-label="روش افزودن ویدیو">
                    <button type="button" class="an-vtab is-active" data-vtab="upload" role="tab" aria-selected="true">آپلود فایل</button>
                    <button type="button" class="an-vtab" data-vtab="link" role="tab" aria-selected="false">لینک مستقیم</button>
                </div>

                <div id="an-video-upload" class="an-upzone" role="button" tabindex="0" aria-label="انتخاب ویدیو">
                    <input type="file" id="an-video-file" accept=".mp4,.webm,.mov" hidden>
                    <span class="an-up-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 5 5"/><path d="M17 3v5h-5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7l8-6 4 3"/></svg>
                    </span>
                    <span class="an-up-txt">
                        <strong>انتخاب ویدیو یا رها کردن در اینجا</strong>
                        <small>MP4، WebM — حداکثر ۱۵ مگابایت</small>
                    </span>
                </div>
                <div class="an-media-preview hidden" id="an-video-preview">
                    <video id="an-video-preview-el" src="" controls preload="metadata"></video>
                    <button type="button" class="an-media-rm" id="an-video-remove" aria-label="حذف ویدیو">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>

                <div id="an-video-link" class="hidden">
                    <label class="lbl" for="an-video-url">آدرس مستقیم ویدیو (MP4)</label>
                    <input id="an-video-url" class="field font-mono !text-xs" dir="ltr" placeholder="https://example.com/video.mp4">
                    <p class="text-[11px] text-stone-400 mt-1">لینک مستقیم فایل ویدیو — در مودال با پخش‌کنندهٔ داخلی نمایش داده می‌شود.</p>
                </div>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="video_url"></p>
            </div>

            {{-- مخاطب --}}
            <div>
                <p class="lbl mb-2">مخاطبان <span class="text-rose-500">*</span></p>
                <div class="an-pick-grid an-pick-grid--audience" id="an-audience-picks" role="radiogroup" aria-label="مخاطبان اطلاعیه">
                    @foreach ($audiences as $key => $label)
                        <button type="button" class="st-prov-card {{ $key === 'customers' ? 'is-selected' : '' }}" data-audience="{{ $key }}" role="radio" aria-checked="{{ $key === 'customers' ? 'true' : 'false' }}">
                            <span class="st-prov-tile {{ ['customers' => 'st-tile--zarinpal', 'admins' => 'st-tile--mellat', 'org_managers' => 'st-tile--melli', 'coffeenet_managers' => 'st-tile--sepehr', 'operators' => 'st-tile--zibal', 'all_panels' => 'st-tile--ippanel', 'everyone' => 'st-tile--kavenegar'][$key] ?? 'st-tile--local' }} an-aud-ico" aria-hidden="true">
                                @if ($key === 'customers')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                @elseif ($key === 'admins')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                @elseif ($key === 'org_managers')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                @elseif ($key === 'coffeenet_managers')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M5 7V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2"/></svg>
                                @elseif ($key === 'operators')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm18 0h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-5Z"/><path d="M21 11a9 9 0 0 0-18 0"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/></svg>
                                @elseif ($key === 'all_panels')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                                @endif
                            </span>
                            <span class="st-prov-name">{{ $label }}</span>
                            <span class="st-prov-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                        </button>
                    @endforeach
                </div>
                <input type="hidden" id="an-audience" value="customers">
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="audience"></p>
            </div>

            {{-- پنجره نمایش --}}
            <div class="an-window-box">
                <p class="lbl">پنجرهٔ نمایش (اختیاری) <span class="font-normal text-stone-400">— خارج از این بازه، اطلاعیه نمایش داده نمی‌شود</span></p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="lbl !text-[10px]" for="an-start-date">از تاریخ</label>
                        <input id="an-start-date" class="field num" data-jdp placeholder="۱۴۰۵/۰۶/۱۲" dir="ltr" style="text-align:center">
                        <input id="an-start-time" type="time" class="field !text-center mt-2" dir="ltr" aria-label="ساعت شروع">
                    </div>
                    <div>
                        <label class="lbl !text-[10px]" for="an-end-date">تا تاریخ</label>
                        <input id="an-end-date" class="field num" data-jdp placeholder="۱۴۰۵/۰۷/۰۱" dir="ltr" style="text-align:center">
                        <input id="an-end-time" type="time" class="field !text-center mt-2" dir="ltr" aria-label="ساعت پایان">
                    </div>
                </div>
            </div>

            {{-- فعال --}}
            <label class="an-active-row" for="an-active">
                <span class="min-w-0">
                    <b class="block text-xs">اطلاعیه فعال باشد</b>
                    <small class="text-[10px] text-stone-400">غیرفعال = برای هیچ مخاطبی نمایش داده نمی‌شود</small>
                </span>
                <span class="st-switch">
                    <input type="checkbox" id="an-active" checked>
                    <span class="st-switch-track" aria-hidden="true"></span>
                </span>
            </label>
        </div>

        <div class="adm-modal-foot">
            <button type="button" class="btn-ghost ui-press !py-3 !px-5" data-close-an>انصراف</button>
            <button type="submit" id="an-save" class="btn-primary btn-shine ui-press !py-3 flex-1">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                ارسال اطلاعیه
            </button>
        </div>
    </form>
</div>

{{-- داده‌های سرور برای اسکریپت صفحه --}}
<div id="page-data" hidden data-payload="{{ json_encode(['audiences' => $audiences]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/announcements/index.js') }}?v=16"></script>
@endpush
