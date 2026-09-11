@extends('app.layout')

@section('title', 'پشتیبانی')
@section('active-nav', 'support')

@section('content')
<div class="support-hero fade-up">
    <div class="sh-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm18 0h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-5Z"/><path d="M21 11a9 9 0 0 0-18 0"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/></svg>
    </div>
    <div class="sh-text">
        <h1>پشتیبانی</h1>
        <p>مشکل یا سؤالی دارید؟ تیکت ثبت کنید تا کارشناسان پاسخ دهند.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="btnNewTicket">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        تیکت جدید
    </button>
</div>

<div class="chip-row fade-up d1" role="tablist" aria-label="فیلتر وضعیت">
    <button type="button" class="chip is-on" data-status="" role="tab" aria-selected="true">همه</button>
    <button type="button" class="chip" data-status="open" role="tab" aria-selected="false">باز</button>
    <button type="button" class="chip" data-status="answered" role="tab" aria-selected="false">پاسخ داده‌شده</button>
    <button type="button" class="chip" data-status="customer_reply" role="tab" aria-selected="false">در گفتگو</button>
    <button type="button" class="chip" data-status="closed" role="tab" aria-selected="false">بسته</button>
</div>

<div class="card fade-up d2">
    <div id="ticketList" aria-live="polite">
        <div class="skeleton" style="height:72px"></div>
        <div class="skeleton" style="height:72px"></div>
    </div>

    <div class="empty-state hidden" id="ticketEmpty">
        <div class="e-icon">🎧</div>
        <div class="e-title">هنوز تیکتی ثبت نکرده‌اید</div>
        <div class="e-desc">با دکمه «تیکت جدید» مشکل خود را برای کارشناسان ارسال کنید.</div>
    </div>
</div>
@endsection

{{-- شیت ساخت تیکت جدید --}}
@push('page')
<div class="sheet-backdrop" id="newSheetBackdrop" aria-hidden="true"></div>
<div class="sheet" id="newSheet" role="dialog" aria-modal="true" aria-labelledby="newSheetTitle">
    <div class="sheet-grip" aria-hidden="true"></div>
    <div class="sheet-head">
        <h2 id="newSheetTitle">تیکت جدید</h2>
        <button type="button" class="sheet-x" id="closeNewSheet" aria-label="بستن">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>

    <form id="newTicketForm" novalidate>
        <div class="form-group">
            <label class="lbl" for="nt-subject">موضوع</label>
            <input type="text" class="field" id="nt-subject" maxlength="150" placeholder="مثلاً: مشکل در پرداخت سفارش">
            <p class="field-error" id="err_subject"></p>
        </div>

        <div class="form-group">
            <label class="lbl" for="nt-message">توضیح مشکل</label>
            <textarea class="field" id="nt-message" rows="4" maxlength="3000" placeholder="شرح کامل مشکل یا سؤال خود را بنویسید…"></textarea>
            <p class="field-error" id="err_message"></p>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="lbl" for="nt-priority">اولویت</label>
                <select class="field" id="nt-priority">
                    <option value="normal">معمولی</option>
                    <option value="low">کم</option>
                    <option value="high">زیاد — فوری</option>
                </select>
            </div>
            <div class="form-group">
                <label class="lbl" for="nt-order">سفارش مرتبط (اختیاری)</label>
                <select class="field" id="nt-order">
                    <option value="">بدون سفارش</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" id="nt-submit">
            ثبت تیکت
        </button>
    </form>
</div>

<script src="{{ asset('front/assets/js/pages/support.js') }}?v=2" defer></script>
@endpush
