@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'فرم‌ساز خدمت')
@section('page-title', $mode === 'edit' ? 'ویرایش خدمت در فرم‌ساز' : 'ایجاد خدمت جدید')
@section('breadcrumb', 'پنل مدیریت کل ← کاتالوگ ← خدمات ← فرم‌ساز')

@section('content')

<div class="space-y-4">

    {{-- ================== نوار ابزار چسبان ================== --}}
    <div class="sticky top-16 z-10 adm-toolbar card border-stone-200/80 px-3.5 sm:px-5 py-3 flex flex-wrap items-center gap-2.5">
        <a href="{{ route('admin.services.index') }}" class="grid place-items-center size-9 rounded-xl border border-stone-200 text-stone-500 hover:bg-stone-50 hover:text-amber-600 transition-colors" title="بازگشت به لیست خدمات" aria-label="بازگشت">
            <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="flex-1 min-w-40">
            <h2 class="text-sm font-extrabold text-stone-800 flex items-center gap-2 flex-wrap">
                {{ $mode === 'edit' ? 'ویرایش خدمت' : 'ایجاد خدمت جدید' }}
                <span id="version-badge" class="badge bg-amber-50 text-amber-700 border border-amber-200 font-mono hidden"></span>
            </h2>
            <p class="text-[11px] text-stone-400 truncate">
                قیمت پایه، ردیف‌های هزینه و فیلدهای فرم — همه در یک صفحه؛ با هر تغییر «مؤثر بر سفارش» نسخه جدید ثبت می‌شود.
            </p>
        </div>
        <button type="button" id="btn-scroll-preview" class="xl:hidden btn-ghost !py-2 !px-3 !text-xs" title="پرش به پیش‌نمایش">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            پیش‌نمایش
        </button>
        <button type="button" id="btn-save" class="btn-primary btn-shine ui-press !py-2.5">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
            {{ $mode === 'edit' ? 'ذخیره تغییرات' : 'ثبت خدمت' }}
        </button>
    </div>

    <div class="grid xl:grid-cols-[minmax(0,1fr)_380px] gap-4 items-start">

        {{-- ================== ستون اصلی ================== --}}
        <div class="space-y-4 min-w-0">

            {{-- ---------- بخش ۱: اطلاعات پایه ---------- --}}
            <section class="card ui-lift animate-fade-up overflow-hidden">
                <header class="adm-card-head">
                    <span class="adm-sec-icon" aria-hidden="true">
                        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><path d="M12 2a10 10 0 0 0 0 20 10 10 0 0 0 0-20Z" transform="translate(0 0)"/><path d="M12 12v10"/></svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-extrabold text-stone-800">۱) اطلاعات پایه خدمت</h3>
                        <p class="text-[11px] text-stone-400">مشخصات کلی که در کاتالوگ مشتری دیده می‌شود</p>
                    </div>
                </header>

                <div class="p-5 space-y-4">
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="lbl" for="s-name">نام خدمت <span class="text-rose-500">*</span></label>
                            <input id="s-name" class="field" maxlength="150" placeholder="مثلاً تعویض پلاک خودرو تهران" autocomplete="off">
                            <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
                        </div>
                        <div>
                            <label class="lbl" for="s-category">دسته‌بندی <span class="text-rose-500">*</span></label>
                            <select id="s-category" class="field">
                                <option value="">— انتخاب کنید —</option>
                                @foreach ($categories as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['name'] }}{{ $c['is_active'] ? '' : ' (غیرفعال)' }}</option>
                                @endforeach
                            </select>
                            <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="category_id"></p>
                        </div>
                        <div>
                            <label class="lbl" for="s-time">زمان تقریبی اجرا (دقیقه)</label>
                            <input id="s-time" type="number" min="0" max="100000" dir="ltr" class="field" placeholder="مثلاً ۴۵">
                            <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="estimated_time"></p>
                        </div>
                        <div>
                            <label class="lbl" for="s-price">قیمت پایه (تومان) <span class="text-rose-500">*</span></label>
                            <input id="s-price" type="number" min="0" step="any" dir="ltr" class="field" placeholder="مثلاً 250000">
                            <p class="text-[10px] text-stone-400 mt-1">مبلغ ثابت خدمت — ردیف‌های هزینه در بخش ۲ اضافه می‌شوند.</p>
                            <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="base_price"></p>
                        </div>
                        <div>
                            <label class="lbl" for="s-sort">ترتیب نمایش در کاتالوگ</label>
                            <input id="s-sort" type="number" min="0" max="99999" dir="ltr" class="field" value="0" placeholder="۰">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="lbl" for="s-desc">توضیحات</label>
                            <textarea id="s-desc" class="field min-h-20" rows="3" maxlength="3000" placeholder="توضیح کوتاه برای مشتری — چه کاری انجام می‌شود، مدارک لازم و…"></textarea>
                        </div>
                    </div>

                    {{-- سوییچ‌ها --}}
                    <div class="flex flex-wrap gap-2 pt-1">
                        <label class="switch-chip" title="خدمت در کاتالوگ مشتری نمایش داده شود">
                            <input id="s-active" type="checkbox" class="size-4 accent-amber-600" checked>
                            <span>✅ فعال</span>
                        </label>
                        <label class="switch-chip" title="نمایش در صدر کاتالوگ">
                            <input id="s-featured" type="checkbox" class="size-4 accent-amber-600">
                            <span>⭐ خدمت ویژه</span>
                        </label>
                        <label class="switch-chip" title="مشتری حتماً فایل/مدرک بارگذاری کند">
                            <input id="s-upload" type="checkbox" class="size-4 accent-amber-600">
                            <span>📎 نیازمند آپلود فایل</span>
                        </label>
                        <label class="switch-chip" title="قبل از شروع، اپراتور باید اطلاعات را تأیید کند">
                            <input id="s-verify" type="checkbox" class="size-4 accent-amber-600">
                            <span>🛡️ نیازمند تأیید اپراتور</span>
                        </label>
                    </div>
                </div>
            </section>

            {{-- ---------- بخش ۲: هزینه‌ها و کمیسیون ---------- --}}
            <section class="card ui-lift animate-fade-up overflow-hidden">
                <header class="adm-card-head">
                    <span class="adm-sec-icon" aria-hidden="true">
                        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>
                    </span>
                    <div class="flex-1">
                        <h3 class="text-sm font-extrabold text-stone-800">۲) هزینه‌ها و کارمزدها</h3>
                        <p class="text-[11px] text-stone-400">ردیف‌های مبلغی که به قیمت پایه اضافه می‌شوند</p>
                    </div>
                    <button type="button" id="btn-add-cost" class="btn-ghost !py-2 !px-3 !text-xs">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        ردیف هزینه
                    </button>
                </header>

                <div class="p-4 sm:p-5">
                    <div id="costs-info" class="mb-4 flex gap-2.5 rounded-2xl bg-amber-50/60 border border-amber-100 px-4 py-3">
                        <svg class="size-4.5 text-amber-500 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        <p class="text-[11px] text-amber-800 leading-6">
                            ردیف‌های دارای فلگ <strong>«جزو کمیسیون»</strong> مبنای محاسبه سهم کافی‌نت/اپراتور در تسویه (فاز ۸) هستند — مابه‌ازای هزینه‌های متغیر خدمت. ردیف‌های بدون فلگ صرفاً در مبلغ نهایی مشتری تأثیر دارند.
                        </p>
                    </div>

                    <div id="costs-list" class="space-y-2.5"></div>

                    {{-- جمع‌بندی --}}
                    <div id="costs-summary" class="mt-4 rounded-2xl border border-stone-200 bg-stone-50/70 divide-y divide-stone-100 text-xs"></div>
                </div>
            </section>

            {{-- ---------- بخش ۳: فرم‌ساز ---------- --}}
            <section class="card ui-lift animate-fade-up overflow-hidden">
                <header class="adm-card-head">
                    <span class="adm-sec-icon" aria-hidden="true">
                        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/></svg>
                    </span>
                    <div class="flex-1">
                        <h3 class="text-sm font-extrabold text-stone-800">۳) فرم سفارش مشتری (فرم‌ساز)</h3>
                        <p class="text-[11px] text-stone-400">فیلدهایی که مشتری هنگام ثبت سفارش پر می‌کند — با درگ‌اند‌دراپ مرتب کنید</p>
                    </div>
                    <span id="fields-count" class="badge bg-stone-50 text-stone-500 border border-stone-200">۰ فیلد</span>
                </header>

                <div class="p-4 sm:p-5 space-y-4">
                    {{-- پالت فیلدها --}}
                    <div>
                        <p class="lbl mb-2">افزودن فیلد <span class="font-normal text-stone-400">— کلیک کنید یا داخل لیست بکشید</span></p>
                        <div id="palette" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-1.5" role="toolbar" aria-label="پالت انواع فیلد"></div>
                    </div>

                    {{-- لیست فیلدها (قابل درگ) --}}
                    <div id="fields-list" class="min-h-24 space-y-2" aria-label="فیلدهای فرم"></div>
                </div>
            </section>
        </div>

        {{-- ================== ستون پیش‌نمایش ================== --}}
        <aside class="xl:sticky xl:top-32 space-y-3" id="preview-anchor">
            <div class="card ui-lift overflow-hidden animate-fade-up">
                <header class="adm-card-head">
                    <span class="relative flex size-2.5 shrink-0">
                        <span class="absolute inline-flex size-full rounded-full bg-emerald-400 opacity-60 animate-ping"></span>
                        <span class="relative inline-flex size-2.5 rounded-full bg-emerald-400"></span>
                    </span>
                    <h3 class="text-sm font-extrabold text-stone-800">پیش‌نمایش زنده</h3>
                    <span class="badge bg-stone-50 text-stone-400 border border-stone-100 ms-auto !text-[10px]">نمای مشتری</span>
                </header>
                <div class="p-4 bg-stone-100/70">
                    {{-- قاب موبایل --}}
                    <div class="rounded-[1.75rem] border border-stone-200 bg-white shadow-xl shadow-stone-900/5 overflow-hidden">
                        <div class="h-6 bg-stone-800/90 flex items-center justify-center">
                            <span class="w-16 h-1.5 rounded-full bg-stone-600"></span>
                        </div>
                        <div id="preview" class="p-4 max-h-[32rem] overflow-y-auto"></div>
                    </div>
                </div>
            </div>
            <p class="text-center text-[10px] text-stone-400 px-2 leading-5">این دقیقاً همان فرمی است که مشتری در فاز ۵ (API) هنگام ثبت سفارش می‌بیند.</p>
        </aside>
    </div>
</div>


{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode(['mode' => $mode, 'payload' => $payload, 'field_types' => $fieldTypes, 'category_names' => collect($categories)->pluck('name', 'id')]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/services/builder.js') }}"></script>
@endpush
