@extends('app.layout')

@section('title', 'سفارش‌های من')
@section('active-nav', 'orders')

@section('content')
<div class="section fade-up">
    <h2>سفارش‌های من</h2>
</div>

{{-- فیلتر وضعیت --}}
<div class="chip-row fade-up d1" id="statusChips">
    <button class="chip active" data-status="" type="button">همه</button>
    <button class="chip" data-status="pending_payment" type="button">در انتظار پرداخت</button>
    <button class="chip" data-status="paid" type="button">پرداخت‌شده</button>
    <button class="chip" data-status="in_progress" type="button">در حال انجام</button>
    <button class="chip" data-status="delivered" type="button">تحویل‌شده</button>
    <button class="chip" data-status="completed" type="button">تکمیل‌شده</button>
    <button class="chip" data-status="cancelled" type="button">لغوشده</button>
</div>

<div id="ordersList" aria-live="polite">
    <div class="skeleton svc"></div>
    <div class="skeleton svc"></div>
</div>

<div class="empty-state hidden" id="ordersEmpty">
    <div class="e-icon">🧾</div>
    <div class="e-title">هنوز سفارشی ندارید</div>
    <div class="e-desc">از فهرست خدمات، اولین سفارش خود را ثبت کنید.</div>
    <a class="btn btn-primary btn-sm mt-2" href="{{ route('app.home') }}">مشاهده خدمات</a>
</div>

<div class="center-loader hidden" id="ordersMoreLoader"><span class="spinner"></span></div>

<button class="btn btn-outline btn-block mt-2 hidden" id="ordersLoadMore" type="button">
    سفارش‌های بیشتر
</button>
@endsection

@push('page')
    <script src="{{ asset('front/assets/js/pages/orders.js') }}?v=3" defer></script>
@endpush
