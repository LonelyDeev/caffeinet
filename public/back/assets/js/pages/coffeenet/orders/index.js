/**
 * کافی‌نت آنلاین — اسکریپت صفحه «سفارش‌ها» پنل کافی‌نت (فاز ۶)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 *
 * ① صندوق پخش زنده: polling هر ۴ ثانیه + شمارش معکوس محلی هر ۱ ثانیه + پذیرش اتمیک
 * ② سفارش‌های من: جدول AJAX با فیلتر/جستجو/صفحه‌بندی
 */
(function () {
    const PAGE = App.pageData();
    const BASE = PAGE.base;
    const TIMEOUT = PAGE.timeout || 60;

    const POLL_MS = 4000;
    const RING_C = 2 * Math.PI * 26; // محیط دایرهٔ شمارش معکوس (r=26, viewBox 64)

    let activeTab = 'broadcast';
    let pollTimer = null;
    let tickTimer = null;
    let acceptBusy = new Set();
    let currentPage = 1;
    let signature = ''; // امضای لیست پخش (برای جلوگیری از رندر مجدد بی‌مورد)

    const els = {
        tabBroadcast: document.getElementById('tab-broadcast'),
        tabMine: document.getElementById('tab-mine'),
        paneBroadcast: document.getElementById('pane-broadcast'),
        paneMine: document.getElementById('pane-mine'),
        broadcastList: document.getElementById('broadcast-list'),
        broadcastEmpty: document.getElementById('broadcast-empty'),
        broadcastCount: document.getElementById('broadcast-count'),
        mineCount: document.getElementById('mine-count'),
        tbody: document.getElementById('mine-tbody'),
        pagination: document.getElementById('mine-pagination'),
        summary: document.getElementById('mine-summary'),
        search: document.getElementById('mine-search'),
        status: document.getElementById('mine-status'),
    };

    /* ================== تب‌ها ================== */
    function switchTab(tab) {
        activeTab = tab;

        const isBroadcast = tab === 'broadcast';
        els.tabBroadcast.className = `tab-btn no-tab ${isBroadcast ? 'is-active' : ''}`;
        els.tabBroadcast.setAttribute('aria-selected', isBroadcast ? 'true' : 'false');
        els.tabMine.className = `tab-btn no-tab ${isBroadcast ? '' : 'is-active'}`;
        els.tabMine.setAttribute('aria-selected', isBroadcast ? 'false' : 'true');
        els.paneBroadcast.classList.toggle('hidden', !isBroadcast);
        els.paneMine.classList.toggle('hidden', isBroadcast);

        if (isBroadcast) {
            pollBroadcast(true);
        } else {
            stopPolling();
            loadMine(1);
        }
    }

    /* ================== ① صندوق پخش زنده ================== */
    async function pollBroadcast(immediate) {
        stopPolling();

        try {
            const res = await App.ajax(`${BASE}/broadcast/data`);
            if (!res.ok) throw new Error();
            const data = await res.json();

            renderBroadcast(data.orders || []);
            if (activeTab === 'broadcast') {
                pollTimer = setTimeout(() => pollBroadcast(), POLL_MS);
            }
        } catch {
            if (activeTab === 'broadcast') {
                pollTimer = setTimeout(() => pollBroadcast(), POLL_MS + 2000);
            }
        }
    }

    function stopPolling() {
        if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
        if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
    }

    function renderBroadcast(orders) {
        const ids = orders.map(o => o.id);
        const sig = ids.join(',');
        els.broadcastCount.textContent = fa(orders.length);

        els.broadcastEmpty.classList.toggle('hidden', orders.length > 0);

        if (sig !== signature) {
            signature = sig;
            els.broadcastList.innerHTML = orders.map(cardHtml).join('');

            if (orders.length && !tickTimer) {
                tickTimer = setInterval(tickAll, 1000);
            }
            if (!orders.length && tickTimer) {
                clearInterval(tickTimer);
                tickTimer = null;
            }
        }

        // هم‌گام‌سازی ثانیه‌ها با سرور
        orders.forEach(o => {
            const card = els.broadcastList.querySelector(`[data-order-id="${o.id}"]`);
            if (card) {
                card.dataset.seconds = o.seconds_left;
                card.dataset.total = o.seconds_left > 0 ? Math.max(TIMEOUT, o.seconds_left) : TIMEOUT;
                updateRing(card);
            }
        });
    }

    function cardHtml(o) {
        return `
        <article class="card ui-lift p-5 flex flex-col gap-4 relative overflow-hidden border-sky-200 animate-fade-up" data-order-id="${o.id}" data-seconds="${o.seconds_left}" data-total="${Math.max(TIMEOUT, o.seconds_left || TIMEOUT)}">
            <span class="ui-orb" data-tone="teal" data-pos="tr" aria-hidden="true"></span>

            <div class="flex items-start justify-between gap-3 relative">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="grid place-items-center size-11 rounded-2xl bg-sky-50 text-xl shrink-0">${escapeHtml(String(o.service_icon || '📄'))}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-stone-700 truncate">${escapeHtml(String(o.service_name || '—'))}</p>
                        <p class="text-[11px] text-stone-400 font-mono" dir="ltr">${escapeHtml(String(o.order_number))}</p>
                    </div>
                </div>
                ${o.attempts > 1 ? `<span class="badge bg-stone-50 text-stone-500 border border-stone-200 shrink-0">پخش ${fa(o.attempts)}</span>` : ''}
            </div>

            <div class="grid grid-cols-2 gap-2 text-[11px] relative">
                <div class="rounded-xl bg-stone-50 px-3 py-2">
                    <p class="text-stone-400 font-semibold mb-0.5">مشتری</p>
                    <p class="text-stone-700 font-bold truncate">${escapeHtml(String(o.customer_name))}</p>
                </div>
                <div class="rounded-xl bg-stone-50 px-3 py-2">
                    <p class="text-stone-400 font-semibold mb-0.5">مبلغ</p>
                    <p class="text-amber-700 font-extrabold">${App.money(o.total)}</p>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4 relative">
                <div class="text-[11px] text-stone-400 space-y-1">
                    <p>${o.estimated_time ? `⏱ حدود ${fa(o.estimated_time)} دقیقه` : '⏱ زمان متغیر'}</p>
                    ${o.has_files ? '<p>📎 دارای مدارک پیوست</p>' : ''}
                </div>

                <div class="relative grid place-items-center shrink-0" role="timer" aria-label="زمان باقی‌مانده پذیرش">
                    <svg class="size-16" style="transform:rotate(-90deg)" viewBox="0 0 64 64" aria-hidden="true">
                        <circle cx="32" cy="32" r="26" fill="none" stroke="#e7e5e4" stroke-width="5"></circle>
                        <circle class="ring-progress" cx="32" cy="32" r="26" fill="none" stroke="#f59e0b" stroke-width="5"
                                stroke-linecap="round" stroke-dasharray="${RING_C}" stroke-dashoffset="0"
                                style="transition: stroke-dashoffset .9s linear, stroke .3s"></circle>
                    </svg>
                    <strong class="absolute text-lg font-extrabold tabular-nums text-amber-700 leading-none ring-num">—</strong>
                </div>
            </div>

            <button type="button" class="act-accept btn-primary btn-shine ui-press w-full !py-3 relative" data-id="${o.id}">
                <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                پذیرش سفارش
            </button>
        </article>`;
    }

    /* شمارش معکوس محلی (هر ثانیه) */
    function tickAll() {
        els.broadcastList.querySelectorAll('[data-order-id]').forEach(card => {
            let s = parseInt(card.dataset.seconds || '0', 10);
            if (s > 0) {
                card.dataset.seconds = String(s - 1);
                updateRing(card);
            } else if (!card.dataset.expired) {
                card.dataset.expired = '1';
                markExpired(card);
            }
        });
    }

    function updateRing(card) {
        const s = Math.max(0, parseInt(card.dataset.seconds || '0', 10));
        const total = Math.max(15, parseInt(card.dataset.total || TIMEOUT, 10));
        const ring = card.querySelector('.ring-progress');
        const num = card.querySelector('.ring-num');
        if (!ring || !num) return;

        num.textContent = fa(s);
        const ratio = Math.min(1, s / total);
        ring.style.strokeDashoffset = String(RING_C * (1 - ratio));

        const danger = s <= 10;
        ring.style.stroke = danger ? '#e11d48' : '#f59e0b';
        num.style.color = danger ? '#e11d48' : '#92400e';
        card.classList.toggle('animate-pulse', danger && s > 0);
    }

    function markExpired(card) {
        const btn = card.querySelector('.act-accept');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-40', 'pointer-events-none');
            btn.innerHTML = 'مهلت تمام شد — در حال تعیین‌تکلیف…';
        }
        const num = card.querySelector('.ring-num');
        if (num) num.textContent = '۰';
    }

    /* پذیرش اتمیک */
    els.broadcastList.addEventListener('click', async (e) => {
        const btn = e.target.closest('.act-accept');
        if (!btn || btn.disabled) return;

        const id = btn.dataset.id;
        if (acceptBusy.has(id)) return;
        acceptBusy.add(id);

        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span> در حال ثبت پذیرش…';

        try {
            const res = await App.ajax(`${BASE}/${id}/accept`, { method: 'POST' });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'سفارش پذیرفته شد.', 'success');
                signature = '';
                pollBroadcast(true);
                loadMine(1, true);
            } else {
                const message = data.errors
                    ? (Object.values(data.errors)[0] || [])[0] || data.message
                    : data.message || 'پذیرش انجام نشد.';
                App.toast(message, 'error');
                btn.disabled = false;
                btn.innerHTML = original;
                pollBroadcast(true);
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
            btn.disabled = false;
            btn.innerHTML = original;
        } finally {
            acceptBusy.delete(id);
        }
    });

    /* ================== ② سفارش‌های من ================== */
    async function loadMine(page = 1, silent) {
        currentPage = page;
        if (!silent) {
            els.tbody.innerHTML = skeletonRows(7);
        }

        const params = new URLSearchParams();
        if (els.search.value.trim()) params.set('q', els.search.value.trim());
        if (els.status.value) params.set('status', els.status.value);
        params.set('page', page);

        try {
            const res = await App.ajax(`${BASE}/data?${params}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            renderMine(data);
        } catch {
            els.tbody.innerHTML = '<tr><td colspan="7" class="!py-10 text-center text-rose-400 text-xs">خطا در دریافت لیست.</td></tr>';
        }
    }

    /* اسکلتون بارگذاری */
    function skeletonRows(cols) {
        let rows = '';
        for (let i = 0; i < 4; i++) {
            rows += `<tr><td colspan="${cols}" class="!py-4">
                <div class="space-y-2.5">
                    <div class="ui-skeleton h-3 w-full"></div>
                    <div class="ui-skeleton h-3 w-40"></div>
                    <div class="ui-skeleton h-3 w-32"></div>
                </div>
            </td></tr>`;
        }
        return rows;
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

    function renderMine(data) {
        els.mineCount.textContent = fa(data.total || 0);

        if (!data.data.length) {
            els.tbody.innerHTML = `<tr><td colspan="8" class="!py-10">
                <div class="ui-empty">
                    <span class="ui-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 13v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M2 9h20"/><path d="m12 2 10 7-10 7-10-7 10-7Z"/></svg>
                    </span>
                    <p class="text-sm font-semibold text-stone-500">سفارشی یافت نشد</p>
                    <p class="text-xs text-stone-400 mt-1">سفارش‌های پذیرفته‌شدهٔ این کافی‌نت اینجا نمایش داده می‌شوند.</p>
                </div>
            </td></tr>`;
            els.summary.textContent = '—';
            els.pagination.innerHTML = '';
            return;
        }

        els.tbody.innerHTML = data.data.map(row => {
            const canRefer = ['accepted', 'needs_info', 'in_progress', 'paid'].includes(row.status.value);
            const canChat = ['accepted', 'paid', 'in_progress', 'needs_info', 'delivered', 'completed'].includes(row.status.value);

            return `
            <tr class="group">
                <td><span class="text-[11px] font-mono text-stone-500" dir="ltr">${escapeHtml(row.order_number)}</span></td>
                <td class="text-xs font-bold text-stone-700">${escapeHtml(row.service_name)}</td>
                <td class="text-xs text-stone-600">${escapeHtml(row.customer_name)}</td>
                <td class="text-xs text-stone-500">${escapeHtml(row.operator_name || 'تعیین نشده')}</td>
                <td class="text-xs font-extrabold text-amber-700 tabular-nums">${App.money(row.total)}</td>
                <td><span class="badge ${STATUS_COLORS[row.status.color] || STATUS_COLORS.amber}">${escapeHtml(row.status.label)}</span></td>
                <td class="text-[11px] text-stone-400">${escapeHtml(row.accepted_at_fa || '—')}</td>
                <td>
                    <div class="flex items-center justify-center gap-1.5">
                        ${canRefer ? `<button type="button" class="act-refer btn-primary !py-1.5 !px-3 !text-[11px]" data-id="${row.id}" data-number="${escapeHtml(row.order_number)}" title="ارجاع به اپراتور">${row.operator_name ? 'تغییر اپراتور' : 'ارجاع به اپراتور'}</button>` : ''}
                        ${canChat ? `<a href="${PAGE.base}/${row.id}/chat" class="btn-ghost !py-1.5 !px-3 !text-[11px]" title="گفتگوی سفارش">گفتگو</a>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');

        renderPagination(data);
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

    /* ================== ارجاع به اپراتور (درخواست بازخوردی) ================== */
    const opModal = document.getElementById('operator-modal');
    const opForm = document.getElementById('operator-form');
    const opList = document.getElementById('operator-list');
    const opNote = document.getElementById('operator-note');
    const opError = document.getElementById('operator-error');
    const opSave = document.getElementById('operator-save');
    const opSubtitle = document.getElementById('operator-subtitle');
    let referOrder = null;
    let selectedOperator = null;

    function openOpModal() {
        opModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeOpModal() {
        opModal.classList.add('hidden');
        document.body.style.overflow = '';
        referOrder = null;
        selectedOperator = null;
    }

    opModal?.querySelectorAll('[data-close-modal]').forEach(el => {
        el.addEventListener('click', closeOpModal);
    });

    async function openRefer(orderId, orderNumber) {
        referOrder = { id: orderId, order_number: orderNumber };
        selectedOperator = null;
        opSubtitle.textContent = orderNumber;
        opNote.value = '';
        opError.classList.add('hidden');
        opList.innerHTML = '<p class="text-xs text-stone-400 text-center py-6">در حال دریافت اپراتورها…</p>';

        openOpModal();
        await loadOperators();
    }

    async function loadOperators() {
        try {
            const res = await App.ajax(`${BASE}/operators`);
            if (!res.ok) throw new Error();
            const data = await res.json();

            if (!data.data.length) {
                opList.innerHTML = '<p class="text-xs text-stone-400 text-center py-6">اپراتور فعالی برای این کافی‌نت ثبت نشده — از بخش «کارکنان» اضافه کنید.</p>';
                return;
            }

            opList.innerHTML = data.data.map(op => `
                <button type="button" class="op-option w-full flex items-center gap-3 rounded-2xl border border-stone-200 px-4 py-3 text-right transition-all duration-200 hover:border-amber-300 hover:bg-amber-50/60" data-id="${op.id}" data-name="${escapeHtml(op.name)}" role="radio" aria-checked="false">
                    <span class="grid place-items-center size-9 rounded-xl bg-stone-100 text-stone-500 text-xs font-bold shrink-0">${escapeHtml(String(op.name || 'ا').charAt(0))}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-bold text-stone-700 truncate">${escapeHtml(op.name)}</span>
                        <span class="block text-[10px] text-stone-400 truncate">${escapeHtml(op.position_label)}${op.mobile ? ` · <span dir="ltr">${escapeHtml(op.mobile)}</span>` : ''}</span>
                    </span>
                    <span class="op-check grid place-items-center size-6 rounded-full border-2 border-stone-200 shrink-0 transition-all duration-200">
                        <svg class="size-3.5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                </button>`).join('');
        } catch {
            opList.innerHTML = '<p class="text-xs text-rose-500 text-center py-6">خطا در دریافت اپراتورها.</p>';
        }
    }

    opList?.addEventListener('click', (e) => {
        const btn = e.target.closest('.op-option');
        if (!btn) return;

        selectedOperator = { id: btn.dataset.id, name: btn.dataset.name };
        opList.querySelectorAll('.op-option').forEach(b => {
            const active = b === btn;
            b.setAttribute('aria-checked', active ? 'true' : 'false');
            b.style.borderColor = active ? '#fbbf24' : '';
            b.classList.toggle('bg-amber-50', active);

            const check = b.querySelector('.op-check');
            check?.classList.toggle('border-amber-500', active);
            check?.classList.toggle('bg-amber-400', active);
            check?.classList.toggle('text-white', active);
            check?.classList.toggle('border-stone-200', !active);
            check?.querySelector('svg')?.classList.toggle('hidden', !active);
        });
    });

    opForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        opError.classList.add('hidden');

        if (!referOrder) return;
        if (!selectedOperator) {
            opError.textContent = 'ابتدا یک اپراتور را انتخاب کنید.';
            opError.classList.remove('hidden');
            return;
        }

        opSave.disabled = true;
        opSave.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> در حال ارجاع…';

        try {
            const res = await App.ajax(`${BASE}/${referOrder.id}/operator`, {
                method: 'PATCH',
                body: {
                    operator_id: parseInt(selectedOperator.id, 10),
                    note: opNote.value.trim() || null,
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'سفارش واگذار شد.', 'success');
                closeOpModal();
                loadMine(currentPage);
            } else {
                opError.textContent = data.message || (data.errors && Object.values(data.errors)[0]?.[0]) || 'خطا در ارجاع.';
                opError.classList.remove('hidden');
            }
        } catch {
            opError.textContent = 'ارتباط با سرور برقرار نشد.';
            opError.classList.remove('hidden');
        } finally {
            opSave.disabled = false;
            opSave.innerHTML = 'ارجاع به اپراتور';
        }
    });

    /* ================== رویدادها ================== */
    function bindEvents() {
        els.tabBroadcast.addEventListener('click', () => switchTab('broadcast'));
        els.tabMine.addEventListener('click', () => switchTab('mine'));

        // دکمهٔ ارجاع به اپراتور در ردیف‌ها
        els.tbody.addEventListener('click', (e) => {
            const btn = e.target.closest('.act-refer');
            if (btn) {
                openRefer(parseInt(btn.dataset.id, 10), btn.dataset.number);
            }
        });

        let searchDebounce;
        els.search.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => loadMine(1), 400);
        });
        els.status.addEventListener('change', () => loadMine(1));

        els.pagination.addEventListener('click', (e) => {
            const btn = e.target.closest('.pg-btn');
            if (btn && !btn.disabled) loadMine(+btn.dataset.page);
        });

        // هنگام بازگشت به تب پخش، polling از نو شروع شود
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && activeTab === 'broadcast') pollBroadcast(true);
        });
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
        switchTab('broadcast');
        loadMine(1, true);
    }

    window.addEventListener('app:ready', boot, { once: true });
    setTimeout(boot, 2500);
})();
