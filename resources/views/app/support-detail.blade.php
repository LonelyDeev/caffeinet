@extends('app.layout')

@section('title', 'تیکت پشتیبانی')

@section('content')
<a href="{{ route('app.support') }}" class="back-link fade-up" id="backLink">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
    تیکت‌های پشتیبانی
</a>

{{-- هدر تیکت --}}
<div class="tkd-head fade-up" id="tkdHead">
    <div class="tkd-skeleton">
        <div class="skeleton" style="height:18px;width:60%"></div>
        <div class="skeleton" style="height:12px;width:40%;margin-top:8px"></div>
    </div>
</div>

{{-- گفتگو --}}
<div class="tkd-thread-wrap fade-up d1">
    <div class="tkd-thread" id="tkdThread" aria-live="polite">
        <div class="skeleton" style="height:56px"></div>
        <div class="skeleton" style="height:56px"></div>
        <div class="skeleton" style="height:56px"></div>
    </div>

    {{-- پاسخ‌دهنده --}}
    <form class="tkd-composer" id="tkdComposer" novalidate>
        <div class="tkd-input-row">
            <textarea class="field" id="tkdMessage" rows="2" maxlength="3000" placeholder="پاسخ خود را بنویسید…"></textarea>
            <label class="tkd-attach-btn" for="tkdFile" title="پیوست">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                <span class="tkd-file-name" id="tkdFileName"></span>
                <input type="file" id="tkdFile" class="sr-only" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.mp3,.mp4,.webm">
            </label>
            <button type="submit" class="btn btn-primary btn-sm" id="tkdSend" aria-label="ارسال">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                ارسال
            </button>
        </div>
        <p class="tkd-hint">پیام شما روی تیکت بسته، آن را بازگشایی می‌کند.</p>
    </form>
</div>
@endsection

@push('page')
<div id="page-data" hidden data-ticket-id="{{ $ticketId }}"></div>
<script src="{{ asset('front/assets/js/pages/support-detail.js') }}" defer></script>
@endpush
