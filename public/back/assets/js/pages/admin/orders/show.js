/**
 * کافی‌نت آنلاین — اسکریپت صفحهٔ «جزئیات سفارش» پنل مدیریت کل (درخواست بازخوردی).
 *
 * صفحهٔ کامل جزئیات (ورودی از نوتیف/لیست) با همان payload مودال index:
 *  - رندر کامل: متا، مبالغ، فرم، مدارک، پخش، پرداخت‌ها، تاریخچه
 *  - عملیات: تخصیص به کافی‌نت (+اپراتور اختیاری)، واگذاری به اپراتور، ری‌پخش، لغو
 */
(function () {
    const PAGE = App.pageData();
    const BASE = PAGE.base;
    const ID = parseInt(PAGE.orderId, 10);

    let order = null;
    let selectedCoffeenet = null;
    let selectedOperator = null;

    const STATUS_COLORS = {
        amber: 'bg-amber-50 text-amber-700 border border-amber-200',
        sky: 'bg-sky-50 text-sky-700 border border-sky-200',
        blue: 'bg-teal-50 text-teal-700 border border-teal-200',
        orange: 'bg-amber-50 text-amber-700 border border-amber-200',
        teal: 'bg-teal-50 text-teal-700 border border-teal-200',
        emerald: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        rose: 'bg-rose-50 text-rose-600 border border-rose-200',
    };

    const els = {
        icon: document.getElementById('order-icon'),
        title: document.getElementById('order-title'),
        service: document.getElementById('order-service'),
        status: document.getElementById('order-status'),
        body: document.getElementById('order-body'),

        actChat: document.getElementById('act-chat'),
        actAssign: document.getElementById('act-assign'),
        actOperator: document.getElementById('act-operator'),
        actRebroadcast: document.getElementById('act-rebroadcast'),
        actCancel: document.getElementById('act-cancel'),

        assignModal: document.getElementById('assign-modal'),
        assignForm: document.getElementById('assign-form'),
        assignList: document.getElementById('assign-list'),
        assignSearch: document.getElementById('assign-search'),
        assignNote: document.getElementById('assign-note'),
        assignError: document.getElementById('assign-error'),
        assignSave: document.getElementById('assign-save'),
        assignSubtitle: document.getElementById('assign-subtitle'),
        assignOperatorsBox: document.getElementById('assign-operators-box'),
        assignOperator: document.getElementById('assign-operator'),

        operatorModal: document.getElementById('operator-modal'),
        operatorForm: document.getElementById('operator-form'),
        operatorList: document.getElementById('operator-list'),
        operatorNote: document.getElementById('operator-note'),
        operatorError: document.getElementById('operator-error'),
        operatorSave: document.getElementById('operator-save'),
        operatorSubtitle: document.getElementById('operator-subtitle'),

        cancelModal: document.getElementById('cancel-modal'),
        cancelForm: document.getElementById('cancel-form'),
        cancelReason: document.getElementById('cancel-reason'),
        cancelError: document.getElementById('cancel-error'),
        cancelSave: document.getElementById('cancel-save'),
        cancelSubtitle: document.getElementById('cancel-subtitle'),

        actStatus: document.getElementById('act-status'),
        statusModal: document.getElementById('status-modal'),
        statusForm: document.getElementById('status-form'),
        statusOptions: document.getElementById('status-options'),
        statusReason: document.getElementById('status-reason'),
        statusReasonReq: document.getElementById('status-reason-req'),
        statusError: document.getElementById('status-error'),
        statusSave: document.getElementById('status-save'),
        statusSubtitle: document.getElementById('status-subtitle'),
    };

    /* ================== ماشین وضعیت (آینهٔ PHP) ================== */
    const STATUS_META = {
        pending_payment: { label: 'در انتظار پذیرش', tone: 'amber' },
        paid: { label: 'پرداخت‌شده', tone: 'sky' },
        broadcasting: { label: 'در حال پخش', tone: 'sky' },
        accepted: { label: 'پذیرفته‌شده', tone: 'teal' },
        in_progress: { label: 'در حال انجام', tone: 'teal' },
        needs_info: { label: 'نیازمند اطلاعات', tone: 'amber' },
        delivered: { label: 'تحویل‌شده', tone: 'teal' },
        completed: { label: 'تکمیل‌شده', tone: 'emerald' },
        queued: { label: 'صف تعیین‌تکلیف', tone: 'sky' },
        cancelled: { label: 'لغوشده', tone: 'rose' },
        refunded: { label: 'بازگشت وجه', tone: 'rose' },
    };

    // گذارهای مجاز (از allowedTransitions سمت سرور)
    const TRANSITIONS = {
        pending_payment: ['broadcasting', 'queued', 'cancelled', 'paid'],
        paid: ['in_progress', 'cancelled', 'refunded'],
        broadcasting: ['accepted', 'queued', 'cancelled'],
        queued: ['accepted', 'broadcasting', 'cancelled'],
        accepted: ['paid', 'in_progress', 'needs_info', 'cancelled'],
        in_progress: ['needs_info', 'delivered', 'cancelled'],
        needs_info: ['in_progress', 'delivered', 'cancelled'],
        delivered: ['completed', 'refunded'],
        completed: [],
        cancelled: [],
        refunded: [],
    };

    // وضعیت‌هایی که مدیر نمی‌گذارد (پرداخت/پخش/صف دست خود مشتری/موتور است)
    const STAFF_SKIPPED = ['paid', 'broadcasting', 'queued'];
    const REASON_REQUIRED = ['cancelled', 'refunded'];

    let selectedStatus = null;

    /* ================== بارگذاری ================== */
    async function load() {
        try {
            const res = await App.ajax(`${BASE}/${ID}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            order = data.data;
            render(order);
        } catch {
            els.body.innerHTML = '<div class="card animate-fade-up"><p class="text-xs text-rose-500 text-center py-10">خطا در دریافت جزئیات سفارش.</p></div>';
        }
    }

    function render(o) {
        els.title.textContent = o.order_number;
        els.service.textContent = o.service?.name || '—';
        els.icon.textContent = o.service?.icon || '📄';
        els.status.textContent = o.status.label;
        els.status.className = `badge ${STATUS_COLORS[o.status.color] || STATUS_COLORS.amber} shrink-0`;

        // عملیات بر اساس وضعیت
        const canAssign = ['queued', 'broadcasting', 'paid'].includes(o.status.value);
        const canOperator = ['accepted', 'needs_info', 'in_progress', 'paid'].includes(o.status.value) && o.coffeenet;
        const canRebroadcast = o.status.value === 'queued';
        const canCancel = ['paid', 'broadcasting', 'queued', 'accepted'].includes(o.status.value);
        const canChat = ['accepted', 'paid', 'in_progress', 'needs_info', 'delivered', 'completed'].includes(o.status.value);

        els.actAssign.classList.toggle('hidden', !canAssign);
        els.actOperator.classList.toggle('hidden', !canOperator);
        els.actRebroadcast.classList.toggle('hidden', !canRebroadcast);
        els.actCancel.classList.toggle('hidden', !canCancel);
        els.actChat.classList.toggle('hidden', !canChat);

        const staffTransitions = (TRANSITIONS[o.status.value] || []).filter(s => !STAFF_SKIPPED.includes(s));
        els.actStatus.classList.toggle('hidden', staffTransitions.length === 0);

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
                       ${o.seconds_left > 0 ? `مهلت باقی‌مانده: <strong class="tabular-nums">${fa(o.seconds_left)} ثانیه</strong>` : 'مهلت در حال اتمام…'}
                       ${o.attempts > 1 ? ` (پخش ${fa(o.attempts)})` : ''}
                   </p>
               </div>` : '';

        const queuedNote = o.status.value === 'queued'
            ? `<div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 flex items-center gap-3">
                   <span class="grid place-items-center size-9 rounded-xl bg-amber-100 text-amber-600 shrink-0">
                       <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l2-3 4 6 2-3h6"/></svg>
                   </span>
                   <p class="text-[11px] leading-6 text-amber-800">
                       سفارش بعد از ${fa(o.attempts)} پخشِ بی‌پذیرش در صف تعیین‌تکلیف است (از ${escapeHtml(o.queued_at_fa || '—')}).<br>
                       <strong>تخصیص دستی، ری‌پخش یا لغو</strong> از دکمه‌های بالا.
                   </p>
               </div>` : '';

        const acceptedNote = (['accepted', 'needs_info', 'in_progress'].includes(o.status.value))
            ? `<div class="rounded-2xl border border-teal-200 bg-teal-50 px-4 py-3 flex items-center gap-3">
                   <span class="grid place-items-center size-9 rounded-xl bg-teal-100 text-teal-600 shrink-0">
                       <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                   </span>
                   <p class="text-[11px] leading-6 text-teal-800">
                       سفارش پذیرفته‌شدهٔ کافی‌نت «${escapeHtml(o.coffeenet?.name || '—')}» است${o.operator_name ? ' با اپراتور «'+escapeHtml(o.operator_name)+'»' : ' — <strong>بدون اپراتور</strong>'}.
                       ${o.operator_name ? '' : 'برای ارجاع به اپراتور از دکمهٔ «واگذاری به اپراتور» استفاده کنید.'}
                   </p>
               </div>` : '';

        html += countdownNote + queuedNote + acceptedNote;

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

        /* نظرسنجی مشتری */
        if (o.rating) {
            const stars = Array.from({ length: 5 }, (_, i) =>
                `<svg class="size-4 ${i < o.rating.rating ? 'text-amber-400' : 'text-stone-200'}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg>`).join('');
            html += `<div class="rounded-2xl border border-amber-100 bg-amber-50/60 p-4">
                <p class="text-[11px] font-extrabold text-stone-500 mb-2">نظرسنجی مشتری</p>
                <div class="flex items-center gap-2">
                    <span class="flex items-center gap-0.5" dir="ltr">${stars}</span>
                    <strong class="text-xs text-amber-700 tabular-nums">${fa(o.rating.rating)} از ۵</strong>
                    ${o.rating.rated_at_fa ? `<span class="text-[10px] text-stone-400">· ${escapeHtml(o.rating.rated_at_fa)}</span>` : ''}
                </div>
                ${o.rating.comment ? `<p class="text-xs text-stone-600 leading-6 mt-2">«${escapeHtml(o.rating.comment)}»</p>` : ''}
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

        // کلاس card برای هر سکشن
        html = html
            .replace(/<div class="rounded-2xl/g, '<div class="card !p-4 rounded-2xl')
            .replace(/<div class="card !p-4 rounded-2xl border/g, '<div class="card rounded-2xl border');

        els.body.innerHTML = `<div class="space-y-4">${html}</div>`;
    }

    /* ================== مودال‌ها ================== */
    function openModal(modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        modal.classList.add('hidden');
        if (![els.assignModal, els.operatorModal, els.cancelModal, els.statusModal].some(m => !m.classList.contains('hidden'))) {
            document.body.style.overflow = '';
        }
    }

    [els.assignModal, els.operatorModal, els.cancelModal, els.statusModal].forEach(modal => {
        modal.querySelectorAll('[data-close-modal]').forEach(el => {
            el.addEventListener('click', () => closeModal(modal));
        });
    });

    function showError(el, msg) { el.textContent = msg; el.classList.remove('hidden'); }
    function hideError(el) { el.classList.add('hidden'); }
    function extractError(data) { return data?.message || (data?.errors && Object.values(data.errors)[0]?.[0]) || 'خطا'; }

    function setLoading(btn, on, text) {
        btn.disabled = on;
        if (on) btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ' + (text || '…');
        else btn.innerHTML = btn.dataset.label || btn.innerHTML;
    }

    /* ================== تخصیص به کافی‌نت ================== */
    async function openAssign() {
        selectedCoffeenet = null;
        selectedOperator = null;
        els.assignSubtitle.textContent = order.order_number;
        els.assignNote.value = '';
        els.assignSearch.value = '';
        els.assignOperatorsBox.classList.add('hidden');
        els.assignOperator.innerHTML = '<option value="">بدون اپراتور — تعیین تکلیف توسط مدیر کافی‌نت</option>';
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

    els.assignList.addEventListener('click', async (e) => {
        const btn = e.target.closest('.cn-option');
        if (!btn) return;

        selectedCoffeenet = { id: btn.dataset.id, name: btn.dataset.name };
        els.assignList.querySelectorAll('.cn-option').forEach(b => {
            const active = b === btn;
            b.setAttribute('aria-checked', active ? 'true' : 'false');
            b.style.borderColor = active ? '#fbbf24' : '';
            b.classList.toggle('bg-amber-50', active);

            const check = b.querySelector('.cn-check');
            check?.classList.toggle('border-amber-500', active);
            check?.classList.toggle('bg-amber-400', active);
            check?.classList.toggle('text-white', active);
            check?.classList.toggle('border-stone-200', !active);
            check?.querySelector('svg')?.classList.toggle('hidden', !active);
        });

        // اپراتورهای این کافی‌نت
        await loadAssignOperators(selectedCoffeenet.id);
    });

    async function loadAssignOperators(coffeenetId) {
        try {
            const res = await App.ajax(`${BASE}/${ID}/operators?coffeenet_id=${coffeenetId}`);
            if (!res.ok) throw new Error();
            const data = await res.json();

            els.assignOperator.innerHTML = '<option value="">بدون اپراتور — تعیین تکلیف توسط مدیر کافی‌نت</option>'
                + (data.data || []).map(op => `<option value="${op.id}">${escapeHtml(op.name)} — ${escapeHtml(op.position_label)}</option>`).join('');

            els.assignOperatorsBox.classList.toggle('hidden', !(data.data || []).length);
        } catch {
            els.assignOperatorsBox.classList.add('hidden');
        }
    }

    els.assignSearch.addEventListener('input', (() => {
        let t;
        return () => { clearTimeout(t); t = setTimeout(() => loadCoffeenets(els.assignSearch.value.trim()), 300); };
    })());

    els.assignForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError(els.assignError);

        if (!selectedCoffeenet) {
            showError(els.assignError, 'ابتدا یک کافی‌نت را انتخاب کنید.');
            return;
        }

        const opId = els.assignOperator.value || null;
        els.assignSave.dataset.label = els.assignSave.innerHTML;
        setLoading(els.assignSave, true, 'در حال تخصیص…');

        try {
            const res = await App.ajax(`${BASE}/${ID}/assign`, {
                method: 'PATCH',
                body: {
                    coffeenet_id: parseInt(selectedCoffeenet.id, 10),
                    operator_id: opId ? parseInt(opId, 10) : null,
                    note: els.assignNote.value.trim() || null,
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'سفارش تخصیص یافت.', 'success');
                closeModal(els.assignModal);
                load();
            } else {
                showError(els.assignError, extractError(data));
            }
        } catch {
            showError(els.assignError, 'ارتباط با سرور برقرار نشد.');
        } finally {
            setLoading(els.assignSave, false);
        }
    });

    /* ================== واگذاری به اپراتور ================== */
    async function openOperator() {
        selectedOperator = null;
        els.operatorSubtitle.textContent = order.order_number;
        els.operatorNote.value = '';
        hideError(els.operatorError);
        els.operatorList.innerHTML = '<p class="text-xs text-stone-400 text-center py-6">در حال دریافت اپراتورها…</p>';

        openModal(els.operatorModal);
        await loadOperators();
    }

    async function loadOperators() {
        try {
            const res = await App.ajax(`${BASE}/${ID}/operators`);
            if (!res.ok) throw new Error();
            const data = await res.json();

            if (!data.data.length) {
                els.operatorList.innerHTML = '<p class="text-xs text-stone-400 text-center py-6">اپراتور فعالی برای کافی‌نت پذیرنده یافت نشد.</p>';
                return;
            }

            els.operatorList.innerHTML = data.data.map(op => `
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
            els.operatorList.innerHTML = '<p class="text-xs text-rose-500 text-center py-6">خطا در دریافت اپراتورها.</p>';
        }
    }

    els.operatorList.addEventListener('click', (e) => {
        const btn = e.target.closest('.op-option');
        if (!btn) return;

        selectedOperator = { id: btn.dataset.id, name: btn.dataset.name };
        els.operatorList.querySelectorAll('.op-option').forEach(b => {
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

    els.operatorForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError(els.operatorError);

        if (!selectedOperator) {
            showError(els.operatorError, 'ابتدا یک اپراتور را انتخاب کنید.');
            return;
        }

        els.operatorSave.dataset.label = els.operatorSave.innerHTML;
        setLoading(els.operatorSave, true, 'در حال واگذاری…');

        try {
            const res = await App.ajax(`${BASE}/${ID}/operator`, {
                method: 'PATCH',
                body: {
                    operator_id: parseInt(selectedOperator.id, 10),
                    note: els.operatorNote.value.trim() || null,
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'سفارش واگذار شد.', 'success');
                closeModal(els.operatorModal);
                load();
            } else {
                showError(els.operatorError, extractError(data));
            }
        } catch {
            showError(els.operatorError, 'ارتباط با سرور برقرار نشد.');
        } finally {
            setLoading(els.operatorSave, false);
        }
    });

    /* ================== ری‌پخش ================== */
    async function doRebroadcast() {
        try {
            const res = await App.ajax(`${BASE}/${ID}/rebroadcast`, { method: 'POST' });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'سفارش مجدداً پخش شد.', 'success');
                load();
            } else {
                App.toast(extractError(data), 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        }
    }

    els.actRebroadcast.addEventListener('click', () => {
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
    });

    /* ================== لغو ================== */
    els.actCancel.addEventListener('click', () => {
        els.cancelSubtitle.textContent = order.order_number;
        els.cancelReason.value = '';
        hideError(els.cancelError);
        openModal(els.cancelModal);
    });

    els.cancelForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError(els.cancelError);

        const reason = els.cancelReason.value.trim();
        if (reason.length < 3) {
            showError(els.cancelError, 'دلیل لغو باید حداقل ۳ حرف باشد.');
            return;
        }

        els.cancelSave.dataset.label = els.cancelSave.innerHTML;
        setLoading(els.cancelSave, true, 'در حال لغو…');

        try {
            const res = await App.ajax(`${BASE}/${ID}/cancel`, {
                method: 'PATCH',
                body: { reason },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'سفارش لغو شد.', 'success');
                closeModal(els.cancelModal);
                load();
            } else {
                showError(els.cancelError, extractError(data));
            }
        } catch {
            showError(els.cancelError, 'ارتباط با سرور برقرار نشد.');
        } finally {
            setLoading(els.cancelSave, false);
        }
    });

    /* ================== تغییر وضعیت (پنل مدیریت کل) ================== */
    els.actStatus.addEventListener('click', () => {
        selectedStatus = null;
        els.statusSubtitle.textContent = order.order_number;
        els.statusReason.value = '';
        els.statusReasonReq.classList.add('hidden');
        hideError(els.statusError);

        const toneClass = {
            amber: 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100',
            sky: 'bg-sky-50 text-sky-700 border-sky-200 hover:bg-sky-100',
            teal: 'bg-teal-50 text-teal-700 border-teal-200 hover:bg-teal-100',
            emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100',
            rose: 'bg-rose-50 text-rose-600 border-rose-200 hover:bg-rose-100',
        };

        const options = (TRANSITIONS[order.status.value] || [])
            .filter(s => !STAFF_SKIPPED.includes(s))
            .map(s => `<button type="button" class="status-opt rounded-xl border px-3.5 py-3 text-xs font-bold transition-colors text-start ${toneClass[STATUS_META[s]?.tone] || toneClass.amber}"
                       data-status="${s}" role="radio" aria-checked="false">
                       ${STATUS_META[s]?.label || s}
                       ${REASON_REQUIRED.includes(s) ? '<span class="block text-[9px] font-normal opacity-70 mt-0.5">نیاز به دلیل</span>' : ''}
                   </button>`).join('');

        els.statusOptions.innerHTML = options || '<p class="text-xs text-stone-400 text-center col-span-2 py-4">گذار مجازی برای وضعیت جاری وجود ندارد.</p>';

        els.statusOptions.querySelectorAll('.status-opt').forEach(btn => {
            btn.addEventListener('click', () => {
                els.statusOptions.querySelectorAll('.status-opt').forEach(b => {
                    b.classList.remove('ring-2', 'ring-amber-400', 'ring-offset-1');
                    b.setAttribute('aria-checked', 'false');
                });
                btn.setAttribute('aria-checked', 'true');
                btn.classList.add('ring-2', 'ring-amber-400', 'ring-offset-1');
                selectedStatus = btn.dataset.status;
                const needsReason = REASON_REQUIRED.includes(selectedStatus);
                els.statusReasonReq.classList.toggle('hidden', !needsReason);
                els.statusReason.placeholder = needsReason
                    ? 'دلیل برای مشتری و تاریخچه (اجباری)…'
                    : 'یادداشت تاریخچه (اختیاری)…';
            });
        });

        openModal(els.statusModal);
    });

    els.statusForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError(els.statusError);

        if (!selectedStatus) {
            showError(els.statusError, 'یک وضعیت جدید انتخاب کنید.');
            return;
        }

        const reason = els.statusReason.value.trim();
        if (REASON_REQUIRED.includes(selectedStatus) && reason.length < 3) {
            showError(els.statusError, 'برای این وضعیت، ذکر دلیل (حداقل ۳ حرف) الزامی است.');
            return;
        }

        els.statusSave.dataset.label = els.statusSave.innerHTML;
        setLoading(els.statusSave, true, 'در حال اعمال…');

        try {
            const res = await App.ajax(`${BASE}/${ID}/status`, {
                method: 'PATCH',
                body: { status: selectedStatus, reason: reason || null },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'وضعیت سفارش تغییر کرد.', 'success');
                closeModal(els.statusModal);
                load();
            } else {
                showError(els.statusError, extractError(data));
            }
        } catch {
            showError(els.statusError, 'ارتباط با سرور برقرار نشد.');
        } finally {
            setLoading(els.statusSave, false);
        }
    });

    /* ================== دکمه‌ها ================== */
    els.actAssign.addEventListener('click', openAssign);
    els.actOperator.addEventListener('click', openOperator);

    /* ================== ابزار ================== */
    function escapeHtml(str) {
        return String(str ?? '')
            .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function fa(n) { return String(n ?? 0).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

    /* شروع */
    load();
})();
