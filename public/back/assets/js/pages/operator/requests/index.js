/**
 * کافی‌نت آنلاین — اسکریپت صفحه «درخواست‌های مشتری» پنل اپراتور (فاز ۱۱)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 *
 * ① لیست زندهٔ درخواست‌های پخش‌شده: polling هر ۴ ثانیه + شمارش معکوس محلی
 * ② مشاهدهٔ جزئیات فرم مشتری (باز/بسته شونده)
 * ③ پذیرش اتمیک درخواست → سپرده‌شدن به اپراتور + هدایت به گفتگو
 */
(function () {
    const PAGE = App.pageData();
    const BASE = PAGE.base;
    const TIMEOUT = PAGE.timeout || 60;

    const POLL_MS = 4000;
    const RING_C = 2 * Math.PI * 26; // محیط دایرهٔ شمارش معکوس (r=26, viewBox 64)

    let pollTimer = null;
    let tickTimer = null;
    let acceptBusy = new Set();
    let signature = '';
    let expanded = new Set(); // کارت‌هایی که جزئیات فرمشان باز است

    const els = {
        list: document.getElementById('requests-list'),
        empty: document.getElementById('requests-empty'),
        count: document.getElementById('requests-count'),
    };

    /* ================== ① لیست زنده ================== */
    async function poll(immediate) {
        stopPolling();

        try {
            const res = await App.ajax(`${BASE}/data`);
            if (!res.ok) throw new Error();
            const data = await res.json();

            render(data.requests || []);
            pollTimer = setTimeout(() => poll(), POLL_MS);
        } catch {
            pollTimer = setTimeout(() => poll(), POLL_MS + 2000);
        }
    }

    function stopPolling() {
        if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
    }

    function render(requests) {
        const ids = requests.map(r => r.id);
        const sig = ids.join(',');
        els.count.textContent = fa(requests.length);

        // هم‌گام‌سازی بج سایدبار
        window.dispatchEvent(new CustomEvent('requests:count', { detail: requests.length }));

        els.empty.classList.toggle('hidden', requests.length > 0);

        if (sig !== signature) {
            signature = sig;
            els.list.innerHTML = requests.map(cardHtml).join('');

            // بازیابی وضعیت باز بودن جزئیات
            expanded.forEach(id => {
                const details = els.list.querySelector(`[data-order-id="${id}"] .req-details`);
                if (details) { details.classList.remove('hidden'); }
            });

            if (requests.length && !tickTimer) {
                tickTimer = setInterval(tickAll, 1000);
            }
            if (!requests.length && tickTimer) {
                clearInterval(tickTimer);
                tickTimer = null;
            }
        }

        // هم‌گام‌سازی ثانیه‌ها با سرور
        requests.forEach(r => {
            const card = els.list.querySelector(`[data-order-id="${r.id}"]`);
            if (card && !card.dataset.accepted) {
                card.dataset.seconds = r.seconds_left;
                card.dataset.total = r.seconds_left > 0 ? Math.max(TIMEOUT, r.seconds_left) : TIMEOUT;
                updateRing(card);
            }
        });
    }

    function cardHtml(r) {
        const fieldsHtml = (r.form_data_display || []).map(f => `
            <div class="flex items-baseline justify-between gap-3 py-1.5 border-b border-dashed border-stone-100 last:border-0">
                <span class="text-[10.5px] text-stone-400 font-semibold shrink-0">${escapeHtml(String(f.label))}</span>
                <span class="text-[11px] text-stone-700 font-bold text-left truncate">${escapeHtml(String(f.value))}</span>
            </div>`).join('');

        return `
        <article class="card ui-lift p-5 flex flex-col gap-4 relative overflow-hidden border-amber-200 animate-fade-up" data-order-id="${r.id}" data-seconds="${r.seconds_left}" data-total="${Math.max(TIMEOUT, r.seconds_left || TIMEOUT)}">
            <span class="ui-orb" data-tone="amber" data-pos="tr" aria-hidden="true"></span>

            <div class="flex items-start justify-between gap-3 relative">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="grid place-items-center size-11 rounded-2xl bg-amber-50 text-xl shrink-0">${escapeHtml(String(r.service_icon || '📄'))}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-stone-700 truncate">${escapeHtml(String(r.service_name || '—'))}</p>
                        <p class="text-[11px] text-stone-400 font-mono" dir="ltr">${escapeHtml(String(r.order_number))}</p>
                    </div>
                </div>
                ${r.attempts > 1 ? `<span class="badge bg-stone-50 text-stone-500 border border-stone-200 shrink-0">پخش ${fa(r.attempts)}</span>` : ''}
            </div>

            <div class="grid grid-cols-2 gap-2 text-[11px] relative">
                <div class="rounded-xl bg-stone-50 px-3 py-2">
                    <p class="text-stone-400 font-semibold mb-0.5">مشتری</p>
                    <p class="text-stone-700 font-bold truncate">${escapeHtml(String(r.customer_name))}</p>
                </div>
                <div class="rounded-xl bg-stone-50 px-3 py-2">
                    <p class="text-stone-400 font-semibold mb-0.5">هزینهٔ درخواست</p>
                    <p class="text-amber-700 font-extrabold">${App.money(r.total)}</p>
                </div>
            </div>

            ${fieldsHtml ? `
            <div class="relative">
                <button type="button" class="req-toggle text-[11px] font-bold text-teal-700 hover:text-teal-800 transition-colors inline-flex items-center gap-1" data-id="${r.id}">
                    <svg class="size-3.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    جزئیات فرم درخواست
                </button>
                <div class="req-details hidden mt-2 rounded-xl bg-stone-50/80 px-3.5 py-2 ${expanded.has(r.id) ? '' : 'hidden'}">
                    ${fieldsHtml}
                </div>
            </div>` : ''}

            <div class="flex items-center justify-between gap-4 relative">
                <div class="text-[11px] text-stone-400 space-y-1">
                    <p>${r.estimated_time ? `⏱ حدود ${fa(r.estimated_time)} دقیقه` : '⏱ زمان متغیر'}</p>
                    ${r.has_files ? '<p>📎 دارای مدارک پیوست</p>' : ''}
                    <p>💳 پرداخت پس از اتصال</p>
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

            <button type="button" class="act-accept btn-primary btn-shine ui-press w-full !py-3 relative" data-id="${r.id}">
                <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                پذیرش درخواست
            </button>
        </article>`;
    }

    /* شمارش معکوس محلی (هر ثانیه) */
    function tickAll() {
        els.list.querySelectorAll('[data-order-id]').forEach(card => {
            if (card.dataset.accepted) return;
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

    /* ================== ② جزئیات فرم (باز/بسته) ================== */
    els.list.addEventListener('click', (e) => {
        const toggle = e.target.closest('.req-toggle');
        if (!toggle) return;

        const id = toggle.dataset.id;
        const details = toggle.closest('.card').querySelector('.req-details');
        if (!details) return;

        const isOpen = !details.classList.contains('hidden');
        details.classList.toggle('hidden');
        if (isOpen) { expanded.delete(Number(id)); } else { expanded.add(Number(id)); }
        const chev = toggle.querySelector('svg');
        if (chev) { chev.style.transform = isOpen ? '' : 'rotate(180deg)'; }
    });

    /* ================== ③ پذیرش اتمیک ================== */
    els.list.addEventListener('click', async (e) => {
        const btn = e.target.closest('.act-accept');
        if (!btn || btn.disabled) return;

        const card = btn.closest('[data-order-id]');
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
                card.dataset.accepted = '1';
                btn.innerHTML = '<svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg> پذیرفته شد';
                btn.classList.remove('btn-shine');
                btn.classList.add('!bg-emerald-600');

                const chatUrl = (data.data && data.data.chat_url) || `${PAGE.chatBase}/${id}/chat`;
                const actions = card.querySelector('.req-details');
                const success = document.createElement('a');
                success.href = chatUrl;
                success.className = 'btn-outline !bg-emerald-50 !border-emerald-200 !text-emerald-700 w-full !py-2.5 text-center text-xs font-bold rounded-xl inline-flex items-center justify-center gap-2';
                success.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg> رفتن به گفتگو با مشتری';
                btn.after(success);

                App.toast(data.message || 'درخواست پذیرفته شد.', 'success', 6000);

                // صفحه بج‌ها را تازه کند و بعد از چند ثانیه کارت را جمع کند
                window.dispatchEvent(new CustomEvent('requests:count', { detail: 0 }));
                window.setTimeout(() => {
                    signature = '';
                    poll();
                }, 3500);
            } else {
                const message = data.errors
                    ? (Object.values(data.errors)[0] || [])[0] || data.message
                    : data.message || 'پذیرش انجام نشد.';
                App.toast(message, 'error', 6000);
                btn.disabled = false;
                btn.innerHTML = original;
                poll();
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
            btn.disabled = false;
            btn.innerHTML = original;
        } finally {
            acceptBusy.delete(id);
        }
    });

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
        poll();
    }

    window.addEventListener('app:ready', boot, { once: true });
    setTimeout(boot, 2500);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });
})();
