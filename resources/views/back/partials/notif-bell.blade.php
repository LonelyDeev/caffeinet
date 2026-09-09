{{-- زنگ اعلان پنل‌ها (فاز ۱۰) — مشترک ۴ لایه؛ اندپوینت‌ها از data-* روی body --}}
@if (auth()->check())
    <div class="notif-wrap relative">
        <button type="button" class="notif-bell" data-nb-toggle aria-expanded="false" aria-label="اعلان‌ها" title="اعلان‌ها">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <span class="nb-count" aria-hidden="true"></span>
            <span class="nb-pulse" aria-hidden="true"></span>
        </button>
        <div class="notif-panel" data-nb-panel role="region" aria-label="فهرست اعلان‌ها">
            <div class="notif-head">
                <strong>اعلان‌ها</strong>
                <span class="nb-total"></span>
                <button type="button" class="nb-markall" title="همه را خوانده‌شده علامت‌گذاری">خواندم ✓</button>
            </div>
            <div class="notif-list nb-list"></div>
        </div>
    </div>
@endif
