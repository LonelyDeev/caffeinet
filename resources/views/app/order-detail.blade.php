@extends('app.layout')

@section('title', 'جزئیات سفارش')
@section('active-nav', 'orders')

@section('content')
{{-- سربرگ سفارش --}}
<div class="card fade-up" id="orderHeadCard">
    <div class="row-between mb-2">
        <strong class="text-brand num" id="orderNumber">—</strong>
        <span class="badge badge-stone" id="orderStatusBadge">…</span>
    </div>
    <div class="row" style="gap:8px">
        <span class="svc-icon" style="display:grid;place-items:center;width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,var(--brand-100),var(--brand-200));font-size:22px" id="orderIcon">📄</span>
        <div class="grow">
            <strong class="tiny" id="orderService" style="font-size:13.5px">—</strong>
            <div class="text-faint tiny" id="orderDate">—</div>
        </div>
    </div>
</div>

{{-- فاز ۱۱ — کارت ارسال درخواست به اپراتورها (۶۰ ثانیه) --}}
<div class="card fade-up d1 assign-card broadcasting hidden" id="broadcastCard">
    <span class="assign-glow" aria-hidden="true"></span>
    <span class="assign-icon" aria-hidden="true">📡</span>
    <h2 class="assign-title">درخواست شما در حال ارسال به اپراتورهاست</h2>
    <p class="assign-desc">درخواستتان بین اپراتورها و کافی‌نت‌های فعال پخش شده است؛<br>اولین اپراتوری که آن را بپذیرد، به شما وصل می‌شود و گفتگو آغاز می‌گردد.</p>
    <div class="assign-timer" id="broadcastTimer" role="timer" aria-label="زمان باقی‌مانده پذیرش درخواست">
        <svg viewBox="0 0 96 96" aria-hidden="true">
            <circle class="t-track" cx="48" cy="48" r="40" fill="none" stroke-width="7"></circle>
            <circle class="t-bar" id="broadcastRing" cx="48" cy="48" r="40" fill="none" stroke-width="7" stroke-linecap="round"></circle>
        </svg>
        <span class="t-num"><span id="broadcastSeconds">۶۰</span><small>ثانیه</small></span>
    </div>
    <p class="assign-desc" id="broadcastAttemptsNote" style="margin-top:8px"></p>
</div>

{{-- فاز ۶ — کارت صف تعیین‌تکلیف --}}
<div class="card fade-up d1 assign-card queued hidden" id="queuedCard">
    <span class="assign-glow" aria-hidden="true"></span>
    <span class="assign-icon" aria-hidden="true">⏳</span>
    <h2 class="assign-title">در صف بررسی کارشناسان</h2>
    <p class="assign-desc">
        سفارش شما در مهلت پخش توسط کافی‌نتی پذیرفته نشد و به <strong>صف تعیین‌تکلیف</strong> منتقل شد.
        کارشناسان ما آن را در اولین فرصت به یکی از کافی‌نت‌ها تخصیص می‌دهند و نتیجه برایتان پیامک می‌شود.
    </p>
    <p class="assign-desc" id="queuedAtNote" style="margin-top:8px;color:var(--ink-faint)"></p>
</div>

{{-- فاز ۱۲+ — گفتگو با اپراتور: اتصال و اطلاعات اپراتور داخل خود چت (سربرگ + رویداد) --}}
<div class="card fade-up d1 hidden" id="chatCard">
    <div class="cnchat cnchat--front" id="cnchat" aria-label="گفتگوی سفارش">
        {{-- سربرگ گفتگو — اطلاعات اپراتور متصل با تصویر/آواتار --}}
        <div class="cnchat-head" id="chatHead">
            <span class="ch-avatar" id="chAvatar" aria-hidden="true">💬</span>
            <div class="ch-info">
                <strong class="ch-name" id="chName">گفتگو با اپراتور</strong>
                <span class="ch-sub" id="chSub">در انتظار اتصال اپراتور…</span>
            </div>
            <span class="badge badge-stone" id="chatStatusBadge">…</span>
        </div>

        {{-- ناحیه پیام‌ها --}}
        <div class="cnchat-pane" id="chatPane" role="log" aria-live="polite" aria-label="پیام‌های گفتگو">
            <div class="cnchat-msgs" id="chatMsgs">
                <div class="cnchat-empty">
                    <span class="e-ico" aria-hidden="true">💬</span>
                    <p class="e-t">در حال بارگذاری گفتگو…</p>
                </div>
            </div>
        </div>

        {{-- شمارش پیام جدید --}}
        <button type="button" class="new-msgs-pill" id="newMsgsPill">↓ پیام جدید</button>

        {{-- فقط-خواندن --}}
        <div class="cnchat-readonly hidden" id="chatReadonly">
            این گفتگو بسته شده است — پیام‌های قبلی قابل مشاهده‌اند.
        </div>

        {{-- فاز ۱۲ — فاکتور پرداخت داخل چت (بعد از اتصال اپراتور) --}}
        <div class="cch-invoice hidden" id="chatInvoice" role="region" aria-label="فاکتور و پرداخت سفارش">
            <span class="inv-notch" aria-hidden="true"></span>
            <div class="inv-head">
                <span class="inv-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1.5L8 22l2-1.5L12 22l2-1.5L16 22l2-1.5L20 22V2l-2 1.5L16 2l-2 1.5L12 2l-2 1.5L8 2 6 3.5 4 2z"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>
                </span>
                <div class="inv-ttl">
                    <strong>فاکتور سفارش</strong>
                    <small id="invService">—</small>
                </div>
                <span class="inv-state" id="invState">در انتظار پرداخت</span>
                <span class="inv-state-paid" id="invStatePaid">پرداخت شد</span>
            </div>
            <div class="inv-body" id="invBody">
                <div class="inv-row">
                    <span>مبلغ قابل پرداخت</span>
                    <strong id="invAmount">—</strong>
                </div>
                <p class="inv-note" id="invNote">برای شروع کار اپراتور، پرداخت را تکمیل کنید.</p>
                <div class="inv-actions">
                    <button type="button" class="inv-btn primary" id="invPayOnline">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
                        پرداخت آنلاین
                    </button>
                    <button type="button" class="inv-btn ghost" id="invPayWallet">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
                        <span>پرداخت از کیف پول <small id="invWalletHint"></small></span>
                    </button>
                </div>
                <p class="inv-error" id="invError"></p>
            </div>
            <div class="inv-paid" id="invPaidBox">
                <span class="ip-check" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <strong>پرداخت شد</strong>
                <span id="invPaidAmount">—</span>
                <small id="invPaidAt">—</small>
            </div>
        </div>

        {{-- فاز ۱۲ — پیش‌نمایش/آپلودر زیبا (بالای نوار ارسال) --}}
        <div class="composer-preview" id="composerPreview">
            <span class="p-thumb t-file" id="pThumb">
                <img id="pThumbImg" alt="" hidden>
                <svg id="pThumbIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                <span class="p-ext" id="pExt" hidden></span>
            </span>
            <div class="p-info">
                <span id="pName">فایل</span>
                <small id="pMeta">—</small>
                <div class="p-track" id="uploadBar"><i id="uploadFill"></i></div>
            </div>
            <span class="p-pct" id="pPct">۰٪</span>
            <button type="button" class="p-rm" id="pRemove" title="حذف پیوست" aria-label="حذف پیوست">✕</button>
        </div>

        {{-- نوار ارسال --}}
        <div class="cnchat-composer" id="chatComposer">
            <button type="button" class="cch-btn attach" id="attachBtn" title="ارسال فایل" aria-label="ارسال فایل" aria-haspopup="menu" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            </button>

            <div class="attach-sheet" id="attachMenu" role="menu" aria-label="انتخاب نوع فایل">
                <span class="as-handle" aria-hidden="true"></span>
                <div class="as-head">
                    <div class="as-ttl">
                        <strong>ارسال فایل</strong>
                        <small>چه چیزی می‌خواهید بفرستید؟</small>
                    </div>
                    <button type="button" class="as-close" id="attachClose" aria-label="بستن" title="بستن">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
                <div class="as-grid">
                    <button type="button" class="as-item" data-attach="image" role="menuitem">
                        <span class="as-tile t-image" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a1.5 1.5 0 0 0-2.12 0L5 20"/></svg>
                        </span>
                        <span><strong>تصویر</strong><small>JPG · PNG · WebP — تا ۵ مگابایت</small></span>
                    </button>
                    <button type="button" class="as-item" data-attach="video" role="menuitem">
                        <span class="as-tile t-video" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 13 5.2-3.1a.6.6 0 0 1 .8.5v3.2a.6.6 0 0 1-.8.5L16 11"/><rect width="14" height="10" x="2" y="7" rx="2"/><path d="m6 11 2 2 4-4"/></svg>
                        </span>
                        <span><strong>ویدیو</strong><small>MP4 · WebM — تا ۵۰ مگابایت</small></span>
                    </button>
                    <button type="button" class="as-item" data-attach="audio" role="menuitem">
                        <span class="as-tile t-audio" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v11"/><path d="M8 6.5a6 6 0 0 0 0 11"/><path d="M16 6.5a6 6 0 0 1 0 11"/></svg>
                        </span>
                        <span><strong>صدا</strong><small>MP3 · OGG · WAV — تا ۱۰ مگابایت</small></span>
                    </button>
                    <button type="button" class="as-item" data-attach="file" role="menuitem">
                        <span class="as-tile t-file" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                        </span>
                        <span><strong>فایل</strong><small>PDF · Office · ZIP — تا ۲۰ مگابایت</small></span>
                    </button>
                </div>
            </div>

            <div class="ta-wrap">
                <textarea id="chatInput" rows="1" placeholder="پیام خود را بنویسید…" maxlength="2000" aria-label="متن پیام"></textarea>
            </div>

            <button type="button" class="cch-btn send" id="sendBtn" disabled title="ارسال" aria-label="ارسال پیام">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4Z"/></svg>
            </button>
        </div>

        {{-- ورودی‌های فایل (پنهان) --}}
        <input type="file" id="fileImage" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
        <input type="file" id="fileVideo" accept="video/mp4,video/webm,video/x-matroska,video/quicktime" hidden>
        <input type="file" id="fileAudio" accept="audio/mpeg,audio/ogg,audio/wav,audio/mp4,audio/aac,audio/opus" hidden>
        <input type="file" id="fileFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z" hidden>

        {{-- فاز ۱۲ — پس‌زمینهٔ شیت پیوست + پوشش کش‌ودرگ --}}
        <div class="attach-backdrop" id="attachBackdrop" hidden></div>
        <div class="cnchat-dropzone" id="cnchatDropzone" aria-hidden="true">
            <div class="dz-inner">
                <span class="dz-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 8 5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg>
                </span>
                <strong>فایل را اینجا رها کنید</strong>
                <small>تصویر · ویدیو · صدا · فایل</small>
            </div>
        </div>
    </div>
</div>

{{-- پرداخت (فاز ۱۱: بعد از اتصال اپراتور — یا سفارش‌های قدیمی pending_payment) --}}
<div class="card fade-up d1 hidden" id="paymentCard">
    <h2 class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
        پرداخت درخواست
    </h2>

    <p class="help-text mb-2" id="payConnectNote">
        ✅ اپراتور شما متصل شد؛ برای شروع کار، پرداخت را تکمیل کنید.
    </p>

    <div class="price-row total" style="margin-bottom:14px">
        <span class="pr-title">مبلغ قابل پرداخت</span>
        <span class="pr-amount" id="payTotal">—</span>
    </div>

    <div class="stack">
        <button class="btn btn-primary btn-block btn-lg" id="payOnlineBtn" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
            پرداخت آنلاین
        </button>

        <button class="btn btn-outline btn-block" id="payWalletBtn" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
            پرداخت از کیف پول
            <span class="tiny text-faint" id="walletBalanceHint">(موجودی: —)</span>
        </button>
    </div>
    <p class="field-error text-center" id="payError"></p>
</div>

{{-- فاز ۱۱ — لغو (تا قبل از پرداخت) --}}
<div class="card fade-up d2 hidden" id="cancelCard" style="text-align:center">
    <p class="tiny text-faint" style="margin-bottom:10px">تا پیش از پرداخت می‌توانید درخواست را لغو کنید.</p>
    <button class="btn btn-danger btn-block btn-sm" id="cancelOrderBtn" type="button">
        لغو درخواست
    </button>
</div>

{{-- نظرسنجی پس از اتمام (تحویل/تکمیل) --}}
<div class="card fade-up d1 hidden" id="surveyCard">
    <h2 class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg>
        نظرسنجی سفارش
    </h2>

    <div id="surveyFormBox">
        <p class="help-text mb-2" id="surveyIntro">سفارش شما تحویل شد! از تجربه‌تان چه امتیازی می‌دهید؟</p>

        <div class="survey-stars" id="surveyStars" role="radiogroup" aria-label="امتیاز از ۱ تا ۵">
            <button type="button" class="s-star" data-value="1" role="radio" aria-checked="false" aria-label="۱ ستاره" title="بسیار بد"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg></button>
            <button type="button" class="s-star" data-value="2" role="radio" aria-checked="false" aria-label="۲ ستاره" title="بد"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg></button>
            <button type="button" class="s-star" data-value="3" role="radio" aria-checked="false" aria-label="۳ ستاره" title="متوسط"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg></button>
            <button type="button" class="s-star" data-value="4" role="radio" aria-checked="false" aria-label="۴ ستاره" title="خوب"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg></button>
            <button type="button" class="s-star" data-value="5" role="radio" aria-checked="false" aria-label="۵ ستاره" title="عالی"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg></button>
        </div>
        <p class="help-text text-center" id="surveyRatingHint">امتیاز خود را انتخاب کنید</p>

        <div class="form-group">
            <label class="label" for="surveyComment">دیدگاه شما (اختیاری)</label>
            <textarea class="field" id="surveyComment" rows="2" maxlength="500" placeholder="تجربه‌تان از این سفارش را بنویسید…"></textarea>
        </div>

        <button class="btn btn-primary btn-block" id="surveySubmitBtn" type="button" disabled>
            ثبت نظرسنجی
        </button>
        <p class="field-error text-center" id="surveyError"></p>
    </div>

    <div id="surveyDoneBox" class="hidden" style="text-align:center;padding:8px 4px">
        <div class="survey-done-stars" id="surveyDoneStars" aria-hidden="true"></div>
        <p class="tiny" style="font-weight:700;color:var(--ink-soft);margin-top:6px">از بازخورد شما سپاسگزاریم 🌟</p>
        <p class="tiny text-faint" id="surveyDoneComment" style="margin-top:4px"></p>
    </div>
</div>

{{-- زمان‌بندی وضعیت --}}
<div class="card fade-up d2" id="timelineCard">
    <h2 class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v12"/><path d="m17 5-9.5 9.5"/><path d="M2 12h10"/><circle cx="12" cy="14" r="1"/></svg>
        روند سفارش
    </h2>
    <div class="timeline" id="timeline">
        <div class="skeleton" style="height:52px"></div>
    </div>
</div>

{{-- خلاصه و فرم --}}
<div class="card fade-up d3" id="summaryCard">
    <h2 class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/></svg>
        خلاصه سفارش
    </h2>

    <div class="price-rows" id="orderCostRows" style="margin-bottom:12px"></div>

    <div class="data-list" id="orderFormData">
        <div class="skeleton" style="height:40px"></div>
    </div>

    <div id="cancelReasonBox" class="hidden mt-2" style="background:var(--err-50);border-radius:var(--radius);padding:10px 14px">
        <strong class="tiny text-err">دلیل لغو:</strong>
        <span class="tiny text-soft" id="cancelReasonText"></span>
    </div>
</div>

{{-- مدارک --}}
<div class="card fade-up d3 hidden" id="filesCard">
    <h2 class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
        مدارک ارسالی
    </h2>
    <div class="stack" id="filesList"></div>
</div>

{{-- پرداخت‌ها --}}
<div class="card fade-up d3 hidden" id="paymentsCard">
    <h2 class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        تاریخچه پرداخت
    </h2>
    <div class="data-list" id="paymentsList"></div>
</div>

<div class="center-loader" id="orderLoader"><span class="spinner"></span></div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/chat.css') }}?v=15">
@endpush

@push('page')
    <script src="{{ asset('front/assets/js/pages/order-detail.js') }}?v=14" defer></script>
    <script src="{{ asset('front/assets/js/pages/order-chat.js') }}?v=14" defer></script>
@endpush
