/**
 * کافی‌نت آنلاین — اسکریپت صفحه «مدیریت سفارش‌ها» پنل مدیریت کل (فاز ۶)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 *
 * ① جدول سفارش‌ها (فیلتر چیپ/سلکت/جستجو + صفحه‌بندی + شمارش معکوس پخش)
 * ② مودال جزئیات کامل (مشتری/فرم/مدارک/پخش/پرداخت/تاریخچه)
 * ③ تعیین‌تکلیف صف: تخصیص دستی / ری‌پخش / لغو
 */
(function () {
    const PAGE = App.pageData();
    const BASE = PAGE.base;

    let currentPage = 1;
    let currentStatus = '';
    let selectedCoffeenet = null;
    let actionOrder = null; // سفارشِ در حال عملیات (assign/cancel/rebroadcast)
    let detailOrder = null;
    let lastCountsSig = '';
    let countsTimer = null;
    let tickTimer = null;

    const els = {
        tbody: document.getElementById('orders-tbody'),
        pagination: document.getElementById('orders-pagination'),
        summary: document.getElementById('orders-summary'),
        search: document.getElementById('orders-search'),
        status: document.getElementById('orders-status'),
        chips: document.querySelectorAll('.stat-chip'),

        detailModal: document.getElementById('detail-modal'),
        detailBody: document.getElementById('detail-body'),
        detailTitle: document.getElementById('detail-title'),
        detailService: document.getElementById('detail-service'),
        detailIcon: document.getElementById('detail-icon'),
        detailStatus: document.getElementById('detail-status'),
        detailAssignBtn: document.getElementById('detail-assign-btn'),
        detailRebroadcastBtn: document.getElementById('detail-rebroadcast-btn'),
        detailCancelBtn: document.getElementById('detail-cancel-btn'),
        detailViewLink: document.getElementById('detail-view-link'),

        assignModal: document.getElementById('assign-modal'),
        assignForm: document.getElementById('assign-form'),
        assignList: document.getElementById('assign-list'),
        assignSearch: document.getElementById('assign-search'),
        assignNote: document.getElementById('assign-note'),
        assignError: document.getElementById('assign-error'),
        assignSave: document.getElementById('assign-save'),
        assignSubtitle: document.getElementById('assign-subtitle'),

        cancelModal: document.getElementById('cancel-modal'),
        cancelForm: document.getElementById('cancel-form'),
        cancelReason: document.getElementById('cancel-reason'),
        cancelError: document.getElementById('cancel-error'),
        cancelSave: document.getElementById('cancel-save'),
        cancelSubtitle: document.getElementById('cancel-subtitle'),
    };

    /* ================== ① جدول ================== */
    async function load(page = 1) {
        currentPage = page;
        els.tbody.innerHTML = '<tr><td colspan="8" class="!py-10 text-center text-stone-400 text-xs">در حال بارگذاری…</td></tr>';

        const params = new URLSearchParams();
        if (els.search.value.trim()) params.set('q', els.search.value.trim());
        if (currentStatus) params.set('status', currentStatus);
        params.set('page', page);

        try {
            const res = await App.ajax(`${BASE}/data?${params}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            renderRows(data);
            renderPagination(data);
            startTicks();
        } catch {
            els.tbody.innerHTML = '<tr><td colspan="8" class="!py-10 text-center text-rose-400 text-xs">خطا در دریافت لیست.</td></tr>';
        }
    }

    const STATUS_COLORS = {
        amber: 'bg-amber-50 text-amber-700 border border-amber-200',
        sky: 'bg-sky-50 text-sky-700 border border-sky-200',
        blue: 'bg-teal-50 text-teal-700 border border-teal-200',
        orange: 'bg-amber-50 text-amber-700 border border-amber-200',
        teal: 'bg-teal-50 text-teal-700 border border-teal-200',
        emerald: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        rose: 'bg-rose-50 text-rose-600 border border-rose-200',
    };

    function renderRows(data) {
        if (!data.data.length) {
            els.tbody.innerHTML = `<tr><td colspan="8" class="!py-4">
                <div class="ui-empty">
                    <span class="ui-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 13v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M2 9h20"/><path d="M12 2 2 9l10 7 10-7-10-7Z"/></svg>
                    </span>
                    <p class="text-xs font-bold text-stone-500">سفارشی یافت نشد</p>
                    <p class="text-[11px] text-stone-400 mt-1">با تغییر فیلترها دوباره تلاش کنید.</p>
                </div>
            </td></tr>`;
            els.summary.textContent = '—';
            els.pagination.innerHTML = '';
            return;
        }

        els.tbody.innerHTML = data.data.map(row => {
            const countdown = row.status.value === 'broadcasting' && row.seconds_left > 0
                ? `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 mt-1 tabular-nums" dir="ltr">
                       <span class="ui-dot text-amber-400"></span>
                       <span class="row-countdown" data-seconds="${row.seconds_left}">${fa(row.seconds_left)}s</span>
                   </span>`
                : '';

            return `
            <tr class="group">
                <td><span class="text-[11px] font-mono text-stone-500" dir="ltr">${escapeHtml(row.order_number)}</span></td>
                <td class="text-xs font-bold text-stone-700 max-w-40 truncate">${escapeHtml(row.service_name)}</td>
                <td>
                    <p class="text-xs font-semibold text-stone-700">${escapeHtml(row.customer_name)}</p>
                    ${row.customer_mobile ? `<p class="text-[10px] text-stone-400 font-mono" dir="ltr">${escapeHtml(row.customer_mobile)}</p>` : ''}
                </td>
                <td class="text-xs text-stone-500">${escapeHtml(row.coffeenet_name || '—')}</td>
                <td class="text-xs font-extrabold text-amber-700 tabular-nums whitespace-nowrap">${App.money(row.total)}</td>
                <td>
                    <span class="badge ${STATUS_COLORS[row.status.color] || STATUS_COLORS.amber} whitespace-nowrap">${escapeHtml(row.status.label)}</span>
                    ${countdown}
                </td>
                <td class="text-[11px] text-stone-400 whitespace-nowrap">${escapeHtml(row.created_fa || '—')}</td>
                <td>
                    <div class="flex items-center justify-center gap-1.5">
                        ${row.status.value === 'queued' || row.status.value === 'broadcasting' ? `
                            <button type="button" class="act-assign btn-primary !py-1.5 !px-3 !text-[11px]" data-id="${row.id}" data-number="${escapeHtml(row.order_number)}" title="تخصیص دستی به کافی‌نت">تخصیص</button>
                        ` : ''}
                        <button type="button" class="act-detail btn-ghost !py-1.5 !px-3 !text-[11px]" data-id="${row.id}" title="جزئیات کامل">جزئیات</button>
                        <button type="button" class="act-trash btn-ghost !py-1.5 !px-3 !text-[11px] ui-press !text-rose-600 hover:!bg-rose-50" data-trash="${row.id}" data-trash-label="${escapeHtml(row.order_number)}" title="حذف سفارش و گفتگو (به حذف‌شده‌ها)">حذف</button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        els.summary.textContent = `نمایش ${fa(data.from || 0)} تا ${fa(data.to || 0)} از ${fa(data.total)} سفارش`;
    }

    function renderPagination(data) {
        const pages = data.last_page || 1;
        if (pages <= 1) { els.pagination.innerHTML = ''; return; }

        let html = `<button type="button" class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${Math.max(1, data.current_page - 1)}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>`;

        for (let p = 1; p <= pages; p++) {
            if (pages > 7 && Math.abs(p - data.current_page) > 2 && p !== 1 && p !== pages) {
                if (p === 2 || p === pages - 1) html += '<span class="text-stone-300 text-xs px-1">…</span>';
                continue;
            }
            html += `<button type="button" class="pg-btn ${p === data.current_page ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'btn-ghost'} !py-1.5 !px-3 !text-xs rounded-lg font-bold" data-page="${p}">${fa(p)}</button>`;
        }

        html += `<button type="button" class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${Math.min(pages, data.current_page + 1)}" ${data.current_page >= pages ? 'disabled' : ''}>بعدی</button>`;
        els.pagination.innerHTML = html;
    }

    /* شمارش معکوس ردیف‌های broadcasting */
    function startTicks() {
        if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
        if (!els.tbody.querySelector('.row-countdown')) return;

        tickTimer = setInterval(() => {
            document.querySelectorAll('.row-countdown').forEach(el => {
                let s = parseInt(el.dataset.seconds || '0', 10);
                if (s > 0) {
                    s -= 1;
                    el.dataset.seconds = String(s);
                    el.textContent = fa(s) + 's';
                    el.classList.toggle('text-rose-600', s <= 10);
                }
            });

            // اگر همه صفر شدند، لیست تازه شود
            const all = [...document.querySelectorAll('.row-countdown')];
            if (all.length && all.every(el => parseInt(el.dataset.seconds, 10) <= 0)) {
                clearInterval(tickTimer);
                tickTimer = null;
                setTimeout(() => load(currentPage), 1200);
            }
        }, 1000);
    }

    /* چیپ‌های آماری */
    async function loadCounts() {
        try {
            const res = await App.ajax(`${BASE}/counts`);
            if (!res.ok) return;
            const c = await res.json();

            document.getElementById('chip-broadcasting').textContent = fa(c.broadcasting);
            document.getElementById('chip-queued').textContent = fa(c.queued);
            document.getElementById('chip-active').textContent = fa(c.active);
            document.getElementById('chip-done').textContent = fa(c.done);
            document.getElementById('chip-total').textContent = fa(c.total);

            const sig = `${c.broadcasting}|${c.queued}|${c.active}|${c.done}|${c.total}`;
            if (lastCountsSig && sig !== lastCountsSig && !anyModalOpen()
                && !els.search.value.trim() && ['broadcasting', 'queued', 'active', ''].includes(currentStatus)) {
                load(currentPage); // تغییر پویا در لیست جاری
            }
            lastCountsSig = sig;
        } catch { /* بی‌صدا */ }
    }

    /* ================== ② مودال جزئیات ================== */
    async function openDetail(id) {
        detailOrder = null;
        [els.detailAssignBtn, els.detailRebroadcastBtn, els.detailCancelBtn].forEach(b => b.classList.add('hidden'));

        els.detailTitle.textContent = '—';
        els.detailService.textContent = 'در حال دریافت…';
        els.detailStatus.textContent = '—';
        els.detailIcon.textContent = '📄';
        els.detailBody.innerHTML = `<div class="py-16 text-center">
            <span class="adm-spinner"></span>
            <p class="text-xs text-stone-400 mt-3">در حال دریافت جزئیات…</p>
        </div>`;

        openModal(els.detailModal);

        try {
            const res = await App.ajax(`${BASE}/${id}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            detailOrder = data.data;
            renderDetail(detailOrder);
        } catch {
            els.detailBody.innerHTML = '<p class="text-xs text-rose-500 text-center py-10">خطا در دریافت جزئیات سفارش.</p>';
        }
    }

    function renderDetail(o) {
        els.detailTitle.textContent = o.order_number;
        els.detailService.textContent = o.service?.name || '—';
        els.detailIcon.textContent = o.service?.icon || '📄';
        els.detailStatus.textContent = o.status.label;
        els.detailStatus.className = `badge ${STATUS_COLORS[o.status.color] || STATUS_COLORS.amber} shrink-0`;

        // لینک صفحهٔ کامل جزئیات
        if (els.detailViewLink) {
            els.detailViewLink.href = `${BASE}/${o.id}/view`;
            els.detailViewLink.classList.remove('hidden');
        }

        // دکمه‌های عملیات بر اساس وضعیت
        const canAssign = ['queued', 'broadcasting', 'paid'].includes(o.status.value);
        const canRebroadcast = o.status.value === 'queued';
        const canCancel = ['paid', 'broadcasting', 'queued', 'accepted'].includes(o.status.value);

        els.detailAssignBtn.classList.toggle('hidden', !canAssign);
        els.detailRebroadcastBtn.classList.toggle('hidden', !canRebroadcast);
        els.detailCancelBtn.classList.toggle('hidden', !canCancel);

        const kv = (label, value, ltr) => `<div class="rounded-xl bg-stone-50 px-3.5 py-2.5">
            <p class="text-[10px] font-bold text-stone-400 mb-1">${label}</p>
            <p class="text-xs font-bold text-stone-700 break-words ${ltr ? 'font-mono' : ''}" ${ltr ? 'dir="ltr"' : ''}>${escapeHtml(value ?? '—')}</p>
        </div>`;

        const grid = (items) => `<div class="grid grid-cols-2 sm:grid-cols-3 gap-2">${items.join('')}</div>`;

        let html = '';

        /* سرریز اطلاعات */
        const countdownNote = o.status.value === 'broadcasting'
            ? `<div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 flex items-center gap-3">
                   <span class="grid place-items-center size-9 rounded-xl bg-sky-100 text-sky-600 shrink-0">
                       <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                   </span>
                   <p class="text-[11px] leading-6 text-sky-800">
                       سفارش در حال پخش بین کافی‌نت‌هاست —
                       ${o.seconds_left > 0
                           ? `مهلت باقی‌مانده: <strong class="tabular-nums">${fa(o.seconds_left)} ثانیه</strong>`
                           : 'مهلت در حال اتمام…'}
                       ${o.attempts > 1 ? ` (پخش ${fa(o.attempts)})` : ''}
                   </p>
               </div>` : '';

        const queuedNote = o.status.value === 'queued'
            ? `<div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 flex items-center gap-3">
                   <span class="grid place-items-center size-9 rounded-xl bg-amber-100 text-amber-600 shrink-0">
                       <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l2-3 4 6 2-3h6"/></svg>
                   </span>
                   <p class="text-[11px] leading-6 text-amber-800">
                       سفارش بعد از ${fa(o.attempts)} پخشِ بی‌پذیرش در صف تعیین‌تکلیف است (از ${escapeHtml(o.queued_at_fa || '—')}).
                       <strong>تخصیص دستی، ری‌پخش یا لغو</strong> از دکمه‌های بالا.
                   </p>
               </div>` : '';

        html += countdownNote + queuedNote;

        /* متا */
        html += grid([
            kv('مشتری', o.customer?.name),
            kv('موبایل مشتری', o.customer?.mobile, true),
            kv('شهر مشتری', o.customer?.city),
            kv('کافی‌نت پذیرنده', o.coffeenet?.name),
            kv('اپراتور', o.operator_name),
            kv('زمان تقریبی', o.service?.estimated_time ? `حدود ${fa(o.service.estimated_time)} دقیقه` : '—'),
        ]);

        /* مبالغ */
        html += `<div class="rounded-2xl border border-stone-100 p-4">
            <p class="text-[11px] font-extrabold text-stone-500 mb-3">مبالغ (اسنپ‌شات ثبت سفارش)</p>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div><p class="text-[10px] text-stone-400">کارمزد خدمت</p><p class="text-sm font-extrabold text-stone-700 tabular-nums">${App.money(o.price)}</p></div>
                <div><p class="text-[10px] text-stone-400">هزینه‌های جانبی</p><p class="text-sm font-extrabold text-stone-700 tabular-nums">${App.money(o.expenses)}</p></div>
                <div><p class="text-[10px] text-stone-400">مبلغ کل</p><p class="text-sm font-extrabold text-amber-700 tabular-nums">${App.money(o.total)}</p></div>
            </div>
        </div>`;

        /* زمان‌ها */
        html += grid([
            kv('ثبت سفارش', o.created_fa),
            kv('پرداخت', o.paid_at_fa),
            kv('پذیرش', o.accepted_at_fa),
        ]);

        /* فرم داینامیک */
        if (o.form_data_display?.length) {
            html += `<div class="rounded-2xl border border-stone-100 p-4">
                <p class="text-[11px] font-extrabold text-stone-500 mb-3">پاسخ‌های فرم مشتری</p>
                <div class="space-y-1.5">
                    ${o.form_data_display.map(f => `<div class="flex items-start justify-between gap-4 text-xs border-b border-dashed border-stone-100 pb-2">
                        <span class="text-stone-400 font-semibold shrink-0">${escapeHtml(f.label)}</span>
                        <span class="text-stone-700 font-bold text-left">${escapeHtml(f.value)}</span>
                    </div>`).join('')}
                </div>
            </div>`;
        }

        /* مدارک */
        if (o.files?.length) {
            html += `<div class="rounded-2xl border border-stone-100 p-4">
                <p class="text-[11px] font-extrabold text-stone-500 mb-3">مدارک مشتری (${fa(o.files.length)})</p>
                <div class="flex flex-wrap gap-2">
                    ${o.files.map(f => `<a href="${escapeHtml(f.url)}" target="_blank" rel="noopener" class="btn-ghost !py-2 !px-3 !text-[11px]">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                        ${escapeHtml(f.original_name)} <span class="text-stone-400">(${fa(f.size_kb)}KB)</span>
                    </a>`).join('')}
                </div>
            </div>`;
        }

        /* گیرنده‌های پخش */
        if (o.broadcasts?.length) {
            html += `<div class="rounded-2xl border border-stone-100 p-4">
                <p class="text-[11px] font-extrabold text-stone-500 mb-3">گیرنده‌های پخش (${fa(o.broadcasts.length)} کافی‌نت)</p>
                <div class="flex flex-wrap gap-2">
                    ${o.broadcasts.map(b => `<span class="badge bg-stone-50 text-stone-600 border border-stone-200 !py-2">
                        ${escapeHtml(b.coffeenet_name)}
                        <span class="text-stone-400">· ارسال ${escapeHtml(b.sent_fa)}${b.seen_fa ? ` · دیده‌شده ${escapeHtml(b.seen_fa)}` : ''}</span>
                    </span>`).join('')}
                </div>
            </div>`;
        }

        /* پرداخت‌ها */
        if (o.payments?.length) {
            html += `<div class="rounded-2xl border border-stone-100 p-4">
                <p class="text-[11px] font-extrabold text-stone-500 mb-3">پرداخت‌ها</p>
                <div class="space-y-1.5">
                    ${o.payments.map(p => `<div class="flex items-center justify-between gap-3 text-xs border-b border-dashed border-stone-100 pb-2">
                        <span class="text-stone-600 font-semibold">${escapeHtml(p.driver === 'wallet' ? 'کیف پول' : (p.driver === 'local' ? 'درگاه تست' : p.driver))}${p.ref_id ? ` <span class="text-stone-400 font-mono" dir="ltr">${escapeHtml(String(p.ref_id).slice(0, 18))}</span>` : ''}</span>
                        <span class="text-stone-500">${App.money(p.amount)} · ${escapeHtml(p.status_label)}${p.paid_at_fa ? ` · ${escapeHtml(p.paid_at_fa)}` : ''}</span>
                    </div>`).join('')}
                </div>
            </div>`;
        }

        /* دلیل لغو */
        if (o.cancel_reason) {
            html += `<div class="rounded-2xl bg-rose-50 border border-rose-100 px-4 py-3 text-xs leading-6 text-rose-700">
                <strong>دلیل لغو:</strong> ${escapeHtml(o.cancel_reason)}
            </div>`;
        }

        /* تاریخچه وضعیت */
        if (o.status_history?.length) {
            html += `<div class="rounded-2xl border border-stone-100 p-4">
                <p class="text-[11px] font-extrabold text-stone-500 mb-4">تاریخچه وضعیت</p>
                <div class="space-y-3">
                    ${o.status_history.map((h, i) => `<div class="flex gap-3">
                        <div class="flex flex-col items-center shrink-0">
                            <span class="size-2.5 rounded-full ${i === 0 ? 'bg-amber-400' : 'bg-stone-300'} mt-1"></span>
                            ${i > 0 ? '<span class="w-px flex-1 bg-stone-200 my-0.5"></span>' : ''}
                        </div>
                        <div class="pb-1">
                            <p class="text-xs font-bold ${i === 0 ? 'text-amber-700' : 'text-stone-600'}">${escapeHtml(h.to_status_label || '—')}
                                <span class="text-[10px] font-normal text-stone-400">${escapeHtml(h.created_at_fa || '')}</span></p>
                            ${h.note ? `<p class="text-[10px] text-stone-400 leading-5 mt-0.5">${escapeHtml(h.note)}</p>` : ''}
                        </div>
                    </div>`).join('')}
                </div>
            </div>`;
        }

        els.detailBody.innerHTML = html;
    }

    /* ================== ③ تخصیص دستی ================== */
    async function openAssign(order) {
        actionOrder = order;
        selectedCoffeenet = null;
        els.assignSubtitle.textContent = order.order_number;
        els.assignNote.value = '';
        els.assignSearch.value = '';
        hideError(els.assignError);
        els.assignList.innerHTML = '<p class="text-xs text-stone-400 text-center py-6">در حال دریافت کافی‌نت‌های فعال…</p>';

        openModal(els.assignModal);
        loadCoffeenets('');
    }

    async function loadCoffeenets(q) {
        try {
            const res = await App.ajax(`${BASE}/coffeenets${q ? `?q=${encodeURIComponent(q)}` : ''}`);
            if (!res.ok) throw new Error();
            const data = await res.json();

            if (!data.data.length) {
                els.assignList.innerHTML = '<p class="text-xs text-stone-400 text-center py-6">کافی‌نت فعالی یافت نشد.</p>';
                return;
            }

            els.assignList.innerHTML = data.data.map(c => `
                <button type="button" class="cn-option w-full flex items-center gap-3 rounded-2xl border border-stone-200 px-4 py-3 text-right transition-all duration-200 hover:border-amber-300 hover:bg-amber-50/60" data-id="${c.id}" data-name="${escapeHtml(c.name)}" role="radio" aria-checked="false">
                    <span class="grid place-items-center size-9 rounded-xl bg-stone-100 text-stone-500 text-xs font-bold shrink-0">${escapeHtml(String(c.name || 'ک').charAt(0))}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-bold text-stone-700 truncate">${escapeHtml(c.name)}</span>
                        <span class="block text-[10px] text-stone-400 truncate">${escapeHtml(c.city || '—')}${c.phone ? ` · ${escapeHtml(c.phone)}` : ''}</span>
                    </span>
                    <span class="cn-check grid place-items-center size-6 rounded-full border-2 border-stone-200 shrink-0 transition-all duration-200">
                        <svg class="size-3.5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                </button>`).join('');
        } catch {
            els.assignList.innerHTML = '<p class="text-xs text-rose-500 text-center py-6">خطا در دریافت کافی‌نت‌ها.</p>';
        }
    }

    els.assignList.addEventListener('click', (e) => {
        const btn = e.target.closest('.cn-option');
        if (!btn) return;

        selectedCoffeenet = { id: btn.dataset.id, name: btn.dataset.name };
        els.assignList.querySelectorAll('.cn-option').forEach(b => {
            const active = b === btn;
            b.setAttribute('aria-checked', active ? 'true' : 'false');
            b.style.borderColor = active ? '#fbbf24' : '';
            b.classList.toggle('bg-amber-50', active);

            const check = b.querySelector('.cn-check');
            check.classList.toggle('border-amber-500', active);
            check.classList.toggle('bg-amber-400', active);
            check.classList.toggle('text-white', active);
            check.classList.toggle('border-stone-200', !active);
            const icon = check.querySelector('svg');
            if (icon) icon.classList.toggle('hidden', !active);
        });
    });

    els.assignForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError(els.assignError);

        if (!actionOrder) return;
        if (!selectedCoffeenet) {
            showError(els.assignError, 'ابتدا یک کافی‌نت را انتخاب کنید.');
            return;
        }

        setLoading(els.assignSave, true, 'در حال تخصیص…');

        try {
            const res = await App.ajax(`${BASE}/${actionOrder.id}/assign`, {
                method: 'PATCH',
                body: {
                    coffeenet_id: parseInt(selectedCoffeenet.id, 10),
                    note: els.assignNote.value.trim() || null,
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'سفارش تخصیص یافت.', 'success');
                closeModal(els.assignModal);
                closeModal(els.detailModal);
                lastCountsSig = '';
                load(currentPage);
            } else {
                showError(els.assignError, extractError(data));
            }
        } catch {
            showError(els.assignError, 'ارتباط با سرور برقرار نشد.');
        } finally {
            setLoading(els.assignSave, false);
        }
    });

    /* ================== ری‌پخش (PanelUI.confirm) ================== */
    function openRebroadcast(order) {
        actionOrder = order;
        if (window.PanelUI) {
            window.PanelUI.confirm(
                {
                    title: 'ری‌پخش سفارش',
                    desc: `سفارش «${escapeHtml(order.order_number)}» مجدداً با مهلت تازه بین کافی‌نت‌های فعال پخش می‌شود.`,
                    okText: 'بله، ری‌پخش کن',
                    cancelText: 'انصراف',
                    icon: 'question',
                },
                doRebroadcast
            );
        } else {
            doRebroadcast();
        }
    }

    async function doRebroadcast() {
        if (!actionOrder) return;

        try {
            const res = await App.ajax(`${BASE}/${actionOrder.id}/rebroadcast`, { method: 'POST' });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'سفارش مجدداً پخش شد.', 'success');
                closeModal(els.detailModal);
                lastCountsSig = '';
                load(currentPage);
            } else {
                App.toast(extractError(data), 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        }
    }

    /* ================== لغو ================== */
    function openCancel(order) {
        actionOrder = order;
        els.cancelSubtitle.textContent = order.order_number;
        els.cancelReason.value = '';
        hideError(els.cancelError);
        openModal(els.cancelModal);
    }

    els.cancelForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError(els.cancelError);

        if (!actionOrder) return;

        const reason = els.cancelReason.value.trim();
        if (reason.length < 3) {
            showError(els.cancelError, 'دلیل لغو باید حداقل ۳ حرف باشد.');
            return;
        }

        setLoading(els.cancelSave, true, 'در حال لغو…');

        try {
            const res = await App.ajax(`${BASE}/${actionOrder.id}/cancel`, {
                method: 'PATCH',
                body: { reason },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'سفارش لغو شد.', 'success');
                closeModal(els.cancelModal);
                closeModal(els.detailModal);
                lastCountsSig = '';
                load(currentPage);
            } else {
                showError(els.cancelError, extractError(data));
            }
        } catch {
            showError(els.cancelError, 'ارتباط با سرور برقرار نشد.');
        } finally {
            setLoading(els.cancelSave, false);
        }
    });

    /* دکمه‌های مودال جزئیات */
    els.detailAssignBtn.addEventListener('click', () => {
        if (detailOrder) { closeModal(els.detailModal); setTimeout(() => openAssign(detailOrder), 180); }
    });
    els.detailRebroadcastBtn.addEventListener('click', () => {
        if (detailOrder) { closeModal(els.detailModal); openRebroadcast(detailOrder); }
    });
    els.detailCancelBtn.addEventListener('click', () => {
        if (detailOrder) { closeModal(els.detailModal); setTimeout(() => openCancel(detailOrder), 180); }
    });

    /* ================== عمومی ================== */
    function openModal(modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        modal.classList.add('hidden');
        if (!anyModalOpen()) document.body.style.overflow = '';
    }

    function anyModalOpen() {
        return [els.detailModal, els.assignModal, els.cancelModal]
            .some(m => !m.classList.contains('hidden'));
    }

    [els.detailModal, els.assignModal, els.cancelModal].forEach(modal => {
        modal.querySelectorAll('[data-close-modal]').forEach(el => {
            el.addEventListener('click', () => closeModal(modal));
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            [els.cancelModal, els.assignModal, els.detailModal].forEach(closeModal);
        }
    });

    function setLoading(btn, on, text) {
        if (on) {
            btn.dataset.original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="size-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span> ${text || 'لطفاً صبر کنید…'}`;
        } else {
            btn.disabled = false;
            if (btn.dataset.original) btn.innerHTML = btn.dataset.original;
        }
    }

    function extractError(data) {
        if (data?.errors) {
            const first = Object.values(data.errors)[0];
            if (Array.isArray(first) && first.length) return first[0];
        }
        return data?.message || 'عملیات انجام نشد.';
    }

    function showError(el, message) { el.textContent = message; el.classList.remove('hidden'); }
    function hideError(el) { el.textContent = ''; el.classList.add('hidden'); }

    /* ---------- رویدادها ---------- */
    function bindEvents() {
        let searchDebounce;
        els.search.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => load(1), 400);
        });
        els.status.addEventListener('change', () => {
            currentStatus = els.status.value;
            syncChips();
            load(1);
        });

        els.chips.forEach(chip => {
            chip.addEventListener('click', () => {
                currentStatus = chip.dataset.status || '';
                els.status.value = currentStatus;
                syncChips();
                load(1);
            });
        });

        function syncChips() {
            els.chips.forEach(chip => {
                chip.classList.toggle('is-active', (chip.dataset.status || '') === currentStatus);
            });
        }

        /* فیلتر اولیه از کوئری‌رینگ (لینک‌های داشبورد: ?status=queued و…) */
        const initialStatus = new URLSearchParams(window.location.search).get('status');
        if (initialStatus !== null && document.getElementById('orders-status')) {
            currentStatus = initialStatus;
            els.status.value = initialStatus;
            syncChips();
        }

        els.pagination.addEventListener('click', (e) => {
            const btn = e.target.closest('.pg-btn');
            if (btn && !btn.disabled) load(+btn.dataset.page);
        });

        /* عملیات ردیف‌ها */
        els.tbody.addEventListener('click', (e) => {
            const assignBtn = e.target.closest('.act-assign');
            if (assignBtn) {
                openAssign({ id: assignBtn.dataset.id, order_number: assignBtn.dataset.number });
                return;
            }
            const detailBtn = e.target.closest('.act-detail');
            if (detailBtn) openDetail(+detailBtn.dataset.id);
        });

        /* جستجوی کافی‌نت در مودال تخصیص */
        let cnDebounce;
        els.assignSearch.addEventListener('input', () => {
            clearTimeout(cnDebounce);
            cnDebounce = setTimeout(() => loadCoffeenets(els.assignSearch.value.trim()), 350);
        });

        /* polling آمار */
        if (countsTimer) clearInterval(countsTimer);
        countsTimer = setInterval(loadCounts, 8000);
    }

    /* ---------- helpers ---------- */
    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[c]);
    }
    function fa(n) { return String(n ?? 0).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

    /* ---------- boot ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        bindEvents();
        load(1);
        loadCounts();
    }

    window.addEventListener('app:ready', boot, { once: true });
    setTimeout(boot, 2500);
})();


/* v28 — حذف نرم/دائم این بخش (trash.js) */
document.addEventListener('DOMContentLoaded', function () {
    if (window.AdminTrash) {
        window.AdminTrash.mount({ section: 'orders' });
    }
});
