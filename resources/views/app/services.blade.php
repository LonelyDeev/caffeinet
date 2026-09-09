@extends('app.layout')

@section('title', 'خدمات')
@section('active-nav', 'services')

@section('content')
{{-- جستجو --}}
<div class="search-bar fade-up">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input class="field" id="searchInput" type="search" placeholder="جستجو در همهٔ خدمات…" autocomplete="off">
</div>

{{-- دسته‌بندی‌ها --}}
<div class="chip-row" id="categoryChips" role="tablist" aria-label="دسته‌بندی خدمات">
    <button class="chip active" data-cat="0" type="button" role="tab" aria-selected="true">
        <span class="chip-icon">✨</span> همه
    </button>
</div>

{{-- شمارنده --}}
<div class="section fade-up d1">
    <h2 id="servicesTitle">همهٔ دسته‌بندی‌ها</h2>
    <span class="more" id="servicesCount"></span>
</div>

{{-- سکشن‌های دسته/زیردسته (رندر JS) --}}
<div id="groupedList" aria-live="polite">
    <div class="skeleton svc"></div>
    <div class="skeleton svc"></div>
    <div class="skeleton svc"></div>
</div>

<div class="empty-state hidden" id="servicesEmpty">
    <div class="e-icon">🔍</div>
    <div class="e-title">خدمتی پیدا نشد</div>
    <div class="e-desc">عبارت دیگری را جستجو کنید یا فیلتر دسته را بردارید.</div>
</div>
@endsection

@push('page')
    <script src="{{ asset('front/assets/js/pages/services.js') }}" defer></script>
@endpush
