@extends($layout, $coffeenet ? ['coffeenet' => $coffeenet, 'user' => auth()->user()] : ['user' => auth()->user()])

@section('title', 'نظرسنجی‌ها')
@section('page-title', 'نظرسنجی‌های مشتریان')
@section('breadcrumb', $panelLabel.' ← نظرسنجی‌ها')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/ratings.css') }}?v=1">
@endpush

@section('content')

    {{-- داده‌های سرور برای JS --}}
    <div id="page-data" hidden data-payload="{{ json_encode([
        'base' => $ratingsBase,
        'isSuperAdmin' => $isSuperAdmin,
        'coffeenets' => $coffeenets,
        'operators' => $operators,
        'options' => $options->map(fn ($o) => ['id' => $o->id, 'title' => $o->title, 'type' => $o->type])->values(),
    ], JSON_UNESCAPED_UNICODE) }}"></div>

    {{-- ============ چیپ‌های آماری ============ --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
        <div class="card p-4 flex items-center gap-3 animate-fade-up" role="status">
            <span class="grid place-items-center size-11 rounded-2xl bg-amber-100 text-amber-600 shrink-0">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-[11px] font-semibold text-stone-500">کل نظرات ثبت‌شده</p>
                <p class="text-xl font-extrabold tabular-nums text-amber-700"><span id="stat-total">۰</span> <span class="text-[10px] font-medium">نظر</span></p>
            </div>
        </div>

        <div class="card p-4 flex items-center gap-3 animate-fade-up delay-1" role="status">
            <span class="grid place-items-center size-11 rounded-2xl bg-emerald-100 text-emerald-600 shrink-0">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-[11px] font-semibold text-stone-500">میانگین امتیاز کافی‌نت{{ $coffeenet ? ' ('.$coffeenet->name.')' : '‌ها' }}</p>
                <p class="text-xl font-extrabold tabular-nums text-emerald-700"><span id="stat-avg">—</span> <span class="text-[10px] font-medium">از ۵</span></p>
            </div>
        </div>

        <button type="button" class="stat-chip card p-4 flex items-center gap-3 text-right animate-fade-up delay-2" data-rating-filter="with_operator">
            <span class="grid place-items-center size-11 rounded-2xl bg-teal-100 text-teal-600 shrink-0">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm18 0h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-5Z"/><path d="M21 11a9 9 0 0 0-18 0"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-[11px] font-semibold text-stone-500">میانگین امتیاز اپراتورها</p>
                <p class="text-xl font-extrabold tabular-nums text-teal-700"><span id="stat-avg-operator">—</span> <span class="text-[10px] font-medium">از ۵</span></p>
            </div>
        </button>

        <button type="button" class="stat-chip card p-4 flex items-center gap-3 text-right animate-fade-up delay-3" data-rating-filter="low">
            <span class="grid place-items-center size-11 rounded-2xl bg-rose-100 text-rose-600 shrink-0">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0Z"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-[11px] font-semibold text-stone-500">نظرات منفی (۱ و ۲ ستاره)</p>
                <p class="text-xl font-extrabold tabular-nums text-rose-600"><span id="stat-low">۰</span> <span class="text-[10px] font-medium">نظر</span></p>
            </div>
        </button>
    </div>

    {{-- ============ توزیع امتیاز + برترین دلایل ============ --}}
    <div class="grid lg:grid-cols-3 gap-4 mb-5">
        <div class="card p-5 animate-fade-up delay-1 lg:col-span-2">
            <h2 class="text-sm font-extrabold text-stone-800 mb-4">توزیع امتیاز کلی (کافی‌net)</h2>
            <div class="space-y-2.5" id="dist-rows" role="list" aria-label="توزیع امتیازها">
                @foreach ([5, 4, 3, 2, 1] as $stars)
                    <div class="rt-dist-row" data-stars="{{ $stars }}">
                        <button type="button" class="rt-dist-label" data-rating-filter="{{ $stars }}" title="فقط نظرات {{ fa_digits((string) $stars) }} ستاره">{{ fa_digits((string) $stars) }} ستاره</button>
                        <div class="rt-dist-bar {{ $stars <= 2 ? 'rt-bad' : '' }}"><span style="width:0%" id="dist-{{ $stars }}"></span></div>
                        <span class="rt-dist-count tabular-nums" id="distc-{{ $stars }}">۰</span>
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-stone-400 mt-3">روی هر ردیف کلیک کنید تا لیست همان امتیاز فیلتر شود</p>
        </div>

        <div class="card p-5 animate-fade-up delay-2">
            <h2 class="text-sm font-extrabold text-stone-800 mb-4">پرتکرارترین دلایل</h2>
            <div class="space-y-2" id="top-reasons">
                <p class="text-xs text-stone-400 text-center py-4">هنوز دلیلی ثبت نشده است</p>
            </div>
        </div>
    </div>

    {{-- ============ جدول نظرات ============ --}}
    <div class="card animate-fade-up delay-2 overflow-hidden">
        <div class="adm-card-head adm-card-head-stacked">
            <div class="relative flex-1 min-w-0">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input id="rt-search" type="text" placeholder="جستجو: شماره سفارش، نام/موبایل مشتری، متن دیدگاه…"
                       class="field !py-2.5 !text-xs w-full pl-10" autocomplete="off">
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select id="rt-rating" class="field !py-2.5 !text-xs !w-auto min-w-40" aria-label="فیلتر امتیاز کلی">
                    <option value="">همهٔ امتیازها</option>
                    <option value="5">۵ ستاره</option>
                    <option value="4">۴ ستاره</option>
                    <option value="3">۳ ستاره</option>
                    <option value="2">۲ ستاره</option>
                    <option value="1">۱ ستاره</option>
                    <option value="high">عالی (۴+)</option>
                    <option value="low">ضعیف (۲ و کمتر)</option>
                    <option value="with_operator">دارای امتیاز اپراتور</option>
                    <option value="with_comment">دارای متن دیدگاه</option>
                </select>

                <select id="rt-operator-rating" class="field !py-2.5 !text-xs !w-auto min-w-36" aria-label="فیلتر امتیاز اپراتور">
                    <option value="">امتیاز اپراتور: همه</option>
                    <option value="5">اپراتور ۵★</option>
                    <option value="4">اپراتور ۴★</option>
                    <option value="3">اپراتور ۳★</option>
                    <option value="2">اپراتور ۲★</option>
                    <option value="1">اپراتور ۱★</option>
                    <option value="high">اپراتور عالی (۴+)</option>
                    <option value="low">اپراتور ضعیف (۲-)</option>
                </select>

                @if ($isSuperAdmin)
                    <select id="rt-coffeenet" class="field !py-2.5 !text-xs !w-auto min-w-36" aria-label="فیلتر کافی‌net">
                        <option value="">همهٔ کافی‌net‌ها</option>
                        @foreach ($coffeenets as $net)
                            <option value="{{ $net['id'] }}">{{ $net['name'] }}</option>
                        @endforeach
                    </select>
                @endif

                <select id="rt-operator" class="field !py-2.5 !text-xs !w-auto min-w-36" aria-label="فیلتر اپراتور">
                    <option value="">همهٔ اپراتورها</option>
                    @foreach ($operators as $op)
                        <option value="{{ $op['id'] }}">{{ $op['name'] }}</option>
                    @endforeach
                </select>

                <select id="rt-option" class="field !py-2.5 !text-xs !w-auto min-w-36" aria-label="فیلتر دلیل">
                    <option value="">همهٔ دلایل</option>
                    @foreach ($options as $option)
                        <option value="{{ $option->id }}">{{ $option->title }}</option>
                    @endforeach
                </select>

                <select id="rt-sort" class="field !py-2.5 !text-xs !w-auto min-w-32" aria-label="مرتب‌سازی">
                    <option value="newest">جدیدترین</option>
                    <option value="worst">بدترین امتیاز</option>
                    <option value="best">بهترین امتیاز</option>
                </select>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <input id="rt-from" type="date" class="field !py-2.5 !text-xs !w-auto" aria-label="از تاریخ">
                    <span class="text-[11px] text-stone-400">تا</span>
                    <input id="rt-to" type="date" class="field !py-2.5 !text-xs !w-auto" aria-label="تا تاریخ">
                </div>
                <button type="button" id="rt-clear" class="btn-ghost ui-press !py-2.5 !px-4 !text-xs">پاک‌سازی فیلترها</button>
            </div>
        </div>

        <div class="table-wrap">
            <div class="overflow-x-auto">
                <table class="table-panel table-modern">
                    <thead>
                    <tr>
                        <th>امتیاز کافی‌net</th>
                        <th>امتیاز اپراتور</th>
                        <th>دلایل</th>
                        <th>دیدگاه مشتری</th>
                        <th>مشتری</th>
                        @if ($isSuperAdmin)<th>کافی‌net</th>@endif
                        <th>اپراتور</th>
                        <th>سفارش</th>
                        <th>تاریخ</th>
                    </tr>
                    </thead>
                    <tbody id="rt-tbody">
                    <tr><td colspan="9" class="!py-10 text-center text-stone-400 text-xs">در حال بارگذاری…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="adm-table-foot">
            <p class="text-[11px] text-stone-400" id="rt-summary">—</p>
            <div class="flex items-center gap-1.5" id="rt-pagination"></div>
        </div>
    </div>

    {{-- ============ مدیریت گزینه‌های دلایل (فقط مدیر کل) ============ --}}
    @if ($isSuperAdmin)
        <div class="card p-5 mt-5 animate-fade-up delay-3" id="options-card">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-sm font-extrabold text-stone-800">گزینه‌های دلایل نظرسنجی</h2>
                    <p class="text-[11px] text-stone-400 mt-1">این گزینه‌ها به‌صورت کارت‌های تیک‌خوردنی در نظرسنجی اپ مشتری (زیر ستاره‌ها) نمایش داده می‌شوند — نقاط قوت برای امتیازهای بالا و نقاط ضعف برای امتیازهای پایین.</p>
                </div>
                <span class="badge bg-stone-50 text-stone-500 border border-stone-200" id="rt-options-count">—</span>
            </div>

            {{-- فرم افزودن --}}
            <form id="rt-option-form" class="flex flex-wrap items-center gap-2 pb-4 mb-4 border-b border-dashed border-stone-200" novalidate>
                <input id="rt-option-title" type="text" class="field !py-2.5 !text-xs flex-1 min-w-52" placeholder="مثلاً «برخورد مناسب» یا «تأخیر در انجام کار»…" maxlength="100" autocomplete="off">
                <select id="rt-option-type" class="field !py-2.5 !text-xs !w-auto" aria-label="نوع گزینه">
                    <option value="pos">نقطه قوت (امتیازهای بالا)</option>
                    <option value="neg">نقطه ضعف (امتیازهای پایین)</option>
                </select>
                <button type="submit" class="btn-primary btn-shine ui-press !py-2.5 !px-5 !text-xs">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    افزودن گزینه
                </button>
            </form>

            <div class="grid sm:grid-cols-2 gap-2.5" id="rt-options-list" role="list" aria-label="گزینه‌های نظرسنجی">
                <p class="text-xs text-stone-400 text-center py-6">در حال دریافت گزینه‌ها…</p>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/ratings/index.js') }}?v=1"></script>
@endpush
