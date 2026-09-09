@extends('back.layouts.org', ['organization' => $organization])

@section('title', 'برداشت‌ها')
@section('page-title', 'برداشت از کیف پول')
@section('breadcrumb', 'پنل سازمان ← برداشت‌ها')

@section('content')

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- فرم درخواست --}}
    <div class="card ui-lift p-6 animate-fade-up space-y-4 h-fit overflow-hidden relative">
        <span class="ui-orb" data-tone="teal" data-pos="bl" aria-hidden="true"></span>
        <h2 class="no-card-title text-sm font-extrabold text-stone-700 relative">درخواست برداشت جدید</h2>

        <div class="rounded-2xl bg-teal-50/70 border border-teal-200/60 px-4 py-3.5 relative">
            <p class="text-[11px] text-teal-800 font-semibold flex items-center gap-2">
                <span class="ui-dot text-emerald-500"></span>
                موجودی قابل برداشت
            </p>
            <p class="mt-1 text-2xl font-extrabold tabular-nums text-teal-700">{{ fa_money($balance) }}</p>
        </div>

        <form id="withdraw-form" class="space-y-4" novalidate>
            <div>
                <label class="lbl" for="w-amount">مبلغ برداشت (تومان) <span class="text-rose-500">*</span></label>
                <input id="w-amount" type="number" min="1000" step="1000" dir="ltr" class="field" placeholder="50000" required>
                <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="amount"></p>
            </div>

            <div>
                <label class="lbl" for="w-note">توضیحات (اختیاری)</label>
                <textarea id="w-note" class="field" rows="2" placeholder="مثلاً شماره حساب/شبا جهت واریز"></textarea>
            </div>

            <button type="submit" id="w-submit" class="btn-primary btn-shine no-btn-teal w-full !py-3">ثبت درخواست برداشت</button>
        </form>

        <p class="text-[11px] text-stone-400 leading-6 border-t border-stone-100 pt-3 relative">
            مبلغ درخواست بلافاصله از کیف پول شما کسر و تا پرداخت توسط مدیریت کل نزد پلتفرم بلوکه می‌شود.
            {{ $pending > 0 ? 'شما <strong class="text-amber-600">'.fa_number($pending).' درخواست در انتظار بررسی</strong> دارید.' : '' }}
        </p>
    </div>

    {{-- لیست برداشت‌ها --}}
    <section class="lg:col-span-2 card ui-lift animate-fade-up delay-1 h-fit overflow-hidden">
        <div class="px-5 py-4 border-b border-stone-100 flex flex-wrap items-center gap-3 justify-between">
            <h2 class="no-card-title text-sm font-extrabold text-stone-700">تاریخچه برداشت‌ها</h2>
            <select id="status-filter" class="field !py-2 !w-auto !text-xs min-w-36" aria-label="فیلتر وضعیت">
                <option value="">همه</option>
                <option value="pending">در انتظار</option>
                <option value="paid">پرداخت‌شده</option>
                <option value="rejected">ردشده</option>
            </select>
        </div>

        <div class="table-wrap">
            <table class="table-panel table-modern">
                <thead>
                    <tr>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th>تاریخ درخواست</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody id="rows">
                    <tr><td colspan="4" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
                </tbody>
            </table>
        </div>

        <div id="pagination" class="px-5 py-4 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500"></div>
    </section>
</div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/org/withdrawals/index.js') }}"></script>
@endpush
