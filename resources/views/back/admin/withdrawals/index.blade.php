@extends('back.layouts.panel', ['user' => auth()->user()])

@section('title', 'برداشت‌ها')
@section('page-title', 'مدیریت برداشت‌ها')
@section('breadcrumb', 'پنل مدیریت کل ← برداشت‌ها')

@section('content')

<section class="card ui-lift animate-fade-up overflow-hidden">

    {{-- هدر جدول --}}
    <div class="adm-card-head">
        <div class="flex items-center gap-3 flex-wrap">
            <div class="text-xs text-stone-500 leading-6">
                مبلغ هر درخواست <span class="font-bold text-amber-600">از زمان ثبت بلوکه</span> شده و با رد شدن به کیف پول برمی‌گردد.
            </div>
        </div>
        <select id="status-filter" class="field !py-2.5 !w-auto min-w-40" aria-label="فیلتر وضعیت">
            <option value="">همه وضعیت‌ها</option>
            <option value="pending">در انتظار بررسی</option>
            <option value="approved">تأییدشده</option>
            <option value="paid">پرداخت‌شده</option>
            <option value="rejected">ردشده</option>
        </select>
    </div>

    {{-- جدول --}}
    <div class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table-panel table-modern">
            <thead>
                <tr>
                    <th>درخواست‌دهنده</th>
                    <th>مبلغ</th>
                    <th>موجودی فعلی کیف پول</th>
                    <th>وضعیت</th>
                    <th>تاریخ درخواست</th>
                    <th class="text-center">عملیات</th>
                </tr>
            </thead>
            <tbody id="rows">
                <tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>
            </tbody>
        </table>
    </div>

    {{-- صفحه‌بندی --}}
    <div id="pagination" class="adm-table-foot text-xs text-stone-500"></div>
</section>

{{-- مودال تعیین‌تکلیف --}}
<div id="review-modal" class="ui-modal-backdrop hidden">
    <div data-close-review class="absolute inset-0" aria-hidden="true"></div>
    <form id="review-form" class="ui-modal adm-modal-sm adm-modal-text-start" data-tone="info" role="dialog" aria-modal="true" aria-labelledby="review-title" novalidate>
        <input type="hidden" id="w-id" value="">
        <div class="adm-modal-head">
            <h3 class="text-sm font-extrabold text-stone-800" id="review-title">تعیین‌تکلیف برداشت</h3>
            <button type="button" class="adm-modal-x" data-close-review aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="adm-modal-body space-y-4">
            <div class="rounded-2xl bg-stone-50 border border-stone-100 px-4 py-3 space-y-1.5 text-xs">
                <div class="flex justify-between"><span class="text-stone-400">سازمان</span><strong id="w-holder" class="text-stone-700"></strong></div>
                <div class="flex justify-between"><span class="text-stone-400">مبلغ</span><strong id="w-amount" class="text-amber-600"></strong></div>
            </div>

            <div>
                <label class="lbl" for="w-note">یادداشت (اختیاری — مثلاً شماره پیگیری واریز)</label>
                <textarea id="w-note" class="field" rows="2"></textarea>
            </div>
        </div>

        <div class="adm-modal-foot">
            <div class="adm-modal-actions-equal">
                <button type="button" id="btn-pay" class="btn-primary btn-shine !py-2.5">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    پرداخت شد
                </button>
                <button type="button" id="btn-reject" class="btn-danger-soft !py-2.5">رد و بازگشت مبلغ</button>
            </div>
            <button type="button" class="btn-ghost !py-2.5 w-full" data-close-review>انصراف</button>
        </div>
    </form>
</div>


{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode(['status' => (string) request('status', '')]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/withdrawals/index.js') }}"></script>
@endpush
