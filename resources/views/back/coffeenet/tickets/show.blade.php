@extends('back.coffeenet.layouts.panel')

@section('title', 'تیکت '.$ticket->ticket_number)
@section('page-title', $ticket->subject)
@section('breadcrumb', 'پنل کافی‌نت ← تیکت‌های پشتیبانی ← '.$ticket->ticket_number)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/tickets.css') }}?v=13">
@endpush

@section('content')

<a href="{{ route('coffeenet.tickets.index', $coffeenet) }}" class="inline-flex items-center gap-2 text-xs font-bold text-stone-500 hover:text-amber-600 transition-colors mb-4">
    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
    بازگشت به لیست تیکت‌ها
</a>

<div class="tk-thread-wrap">

    {{-- ================== کارت اطلاعات و عملیات ================== --}}
    <aside class="tk-info-card ui-lift">
        <div class="tk-info-head">
            <span class="tk-number" dir="ltr">{{ $ticket->ticket_number }}</span>
            <h2>{{ $ticket->subject }}</h2>
            <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                <span class="tk-badge tk-status-{{ $ticket->status->value }}" id="tk-status-badge">{{ $ticket->status->label() }}</span>
                <span class="tk-badge tk-prio-{{ $ticket->priority }}">{{ \App\Services\Support\TicketService::priorityLabel($ticket->priority) }}</span>
            </div>
        </div>

        <div class="tk-info-body">
            <div class="tk-row"><span>مشتری</span><strong>{{ $ticket->user?->full_name ?? '—' }}</strong></div>
            @if ($ticket->user?->mobile)
                <div class="tk-row"><span>موبایل</span><strong class="font-mono" dir="ltr">{{ $ticket->user->mobile }}</strong></div>
            @endif
            <div class="tk-row"><span>سفارش مرتبط</span>
                @if ($ticket->order)
                    <a href="{{ route('coffeenet.orders.index', $coffeenet) }}?q={{ $ticket->order->order_number }}" class="font-mono" dir="ltr">{{ $ticket->order->order_number }}</a>
                @else
                    <strong class="text-stone-400 font-normal">عمومی (بدون سفارش)</strong>
                @endif
            </div>
            <div class="tk-row"><span>تاریخ ثبت</span><strong>{{ fa_date($ticket->created_at, 'Y/m/d H:i') }}</strong></div>
            <div class="tk-row"><span>کارشناس مرکزی</span><strong>{{ $ticket->assignedTo?->full_name ?? 'تعیین‌نشده' }}</strong></div>
        </div>

        <div class="tk-info-actions">
            <button type="button" id="tk-toggle-status" class="{{ $ticket->status === \App\Enums\TicketStatus::Closed ? 'btn-primary btn-shine' : 'btn-danger-soft' }} !py-2.5 !text-xs w-full ui-press">
                {{ $ticket->status === \App\Enums\TicketStatus::Closed ? 'بازگشایی تیکت' : 'بستن تیکت' }}
            </button>
        </div>
    </aside>

    {{-- ================== گفتگو ================== --}}
    <section class="card ui-lift animate-fade-up overflow-hidden flex flex-col">
        <div class="adm-card-head">
            <div class="flex items-center gap-2">
                <span class="grid place-items-center size-9 rounded-xl bg-amber-50 text-amber-600 border border-amber-100">
                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </span>
                <div class="text-xs text-stone-500 leading-5">
                    گفتگوی پشتیبانی — <span id="tk-msgs-count">۰</span> پیام
                </div>
            </div>
        </div>

        {{-- رشته پیام‌ها --}}
        <div class="tk-thread" id="tk-thread" aria-live="polite">
            <div class="nb-loading"><div class="nb-spin"></div>در حال بارگذاری…</div>
        </div>

        {{-- پاسخ‌دهنده --}}
        <form class="tk-composer" id="tk-composer" enctype="multipart/form-data"
              data-reply-url="{{ route('coffeenet.tickets.reply', [$coffeenet, $ticket]) }}"
              data-status-url="{{ route('coffeenet.tickets.status', [$coffeenet, $ticket]) }}"
              data-poll-url="{{ route('coffeenet.tickets.messages', [$coffeenet, $ticket]) }}"
              data-manage="{{ $payload['can_manage'] ? '1' : '0' }}">
            <textarea id="tk-message" rows="3" maxlength="3000" placeholder="پاسخ خود را بنویسید… (Ctrl+Enter برای ارسال)"></textarea>
            <div class="tk-composer-foot">
                <label class="tk-file-label" for="tk-file">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    پیوست (تا ۱۵ مگابایت)
                    <span class="tk-file-picked" id="tk-file-name"></span>
                    <input type="file" id="tk-file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.mp3,.mp4,.webm">
                </label>
                @if ($payload['can_manage'])
                    <label class="tk-internal-check" for="tk-internal" title="فقط کارشناسان می‌بینند">
                        <input type="checkbox" id="tk-internal">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        یادداشت داخلی
                    </label>
                @endif
                <button type="submit" class="tk-send-btn ui-press" id="tk-send">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    ارسال پاسخ
                </button>
            </div>
        </form>
    </section>
</div>

{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode($payload) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/admin/tickets/show.js') }}?v=13"></script>
@endpush
