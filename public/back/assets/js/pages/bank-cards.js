/**
 * کافی‌نت آنلاین — کارت‌های بانکی (v39/v40)
 * UI مشترک سه پنل: اپراتور / مدیر کافی‌نت / مدیر سازمان.
 * endpointها از data-base المان ریشه خوانده می‌شوند.
 * v40: اگر data-finnotech=1 باشد، کد ملی صاحب کارت در مودال گرفته می‌شود
 * و کارت‌های تأییدشده نشان ✓ فینوتک می‌گیرند.
 */
(function () {
    'use strict';

    const root = document.getElementById('bcRoot');
    if (!root) { return; }

    const BASE = root.dataset.base.replace(/\/+$/, '');
    const FINNOTECH = root.dataset.finnotech === '1'; // v40
    let cards = [];
    try { cards = JSON.parse(root.dataset.cards || '[]'); } catch { cards = []; }

    /* ---------- ابزار ---------- */

    const $ = (sel, scope) => (scope || document).querySelector(sel);
    const $$ = (sel, scope) => Array.from((scope || document).querySelectorAll(sel));

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function fa(n) { return App.digits ? App.digits(n) : String(n); }

    /** نرمال‌سازی ارقام فارسی + حذف فاصله/خط تیره */
    function normNum(v) {
        const fa = '۰۱۲۳۴۵۶۷۸۹';
        return String(v || '').replace(/[۰-۹]/g, d => String(fa.indexOf(d)))
            .replace(/[\s-]/g, '').trim().toUpperCase();
    }

    function maskCard(c) {
        const n = normNum(c);
        if (!/^\d{16}$/.test(n)) { return esc(c) || '—'; }
        return esc(n.slice(0, 4) + '-' + n.slice(4, 6) + '**-****-' + n.slice(-4));
    }

    async function api(path, options) {
        const res = await App.ajax(BASE + path, options);
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, data };
    }

    /* ---------- رندر لیست ---------- */

    function cardRow(c) {
        const rows = [];

        /* v40 — نشان تأیید فینوتک */
        const verifiedBadge = c.verified
            ? `<span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-teal-50 border border-teal-200 text-teal-700 px-2.5 py-1 text-[10px] font-extrabold" title="مالکیت این کارت با کد ملی صاحبش از طریق فینوتک تأیید شده${c.verified_at_fa ? ' — ' + c.verified_at_fa : ''}">
                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                تأیید فینوتک
               </span>`
            : '';

        if (c.card_number) {
            rows.push(`<div class="flex items-center justify-between gap-3 text-xs border-b border-dashed border-stone-100 pb-2">
                <span class="text-stone-400 font-semibold shrink-0">شماره کارت</span>
                <span class="font-mono font-bold text-stone-700 tracking-wider" dir="ltr">${fa(c.card_number)}</span>
            </div>`);
        }
        if (c.sheba_number) {
            rows.push(`<div class="flex items-center justify-between gap-3 text-xs border-b border-dashed border-stone-100 pb-2">
                <span class="text-stone-400 font-semibold shrink-0">شماره شبا</span>
                <span class="font-mono font-bold text-stone-700 text-[11px]" dir="ltr">${fa(c.sheba_number)}</span>
            </div>`);
        }
        if (c.account_number) {
            rows.push(`<div class="flex items-center justify-between gap-3 text-xs border-b border-dashed border-stone-100 pb-2">
                <span class="text-stone-400 font-semibold shrink-0">شماره حساب</span>
                <span class="font-mono font-bold text-stone-700" dir="ltr">${fa(c.account_number)}</span>
            </div>`);
        }
        if (c.holder_name) {
            rows.push(`<div class="flex items-center justify-between gap-3 text-xs">
                <span class="text-stone-400 font-semibold shrink-0">صاحب حساب</span>
                <span class="font-bold text-stone-700">${esc(c.holder_name)}</span>
            </div>`);
        }

        return `<div class="card ui-lift p-4 relative overflow-hidden ${c.is_default ? '!border-amber-300 bg-gradient-to-l from-amber-50/60 to-white' : ''}">
            <div class="flex items-center justify-between gap-2 mb-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="grid place-items-center size-9 rounded-xl ${c.is_default ? 'bg-amber-100 text-amber-600' : 'bg-stone-100 text-stone-500'} shrink-0">
                        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-extrabold text-stone-700">${maskCard(c.card_number)}</p>
                        ${c.holder_name ? `<p class="text-[10px] text-stone-400 truncate">${esc(c.holder_name)}</p>` : ''}
                    </div>
                </div>
                ${c.is_default
                    ? `<span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-amber-100 border border-amber-200 text-amber-700 px-2.5 py-1 text-[10px] font-extrabold">
                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        پیش‌فرض تسویه
                       </span>`
                    : ''}
                ${verifiedBadge}
            </div>
            <div class="space-y-2">${rows.join('')}</div>
            <div class="flex items-center gap-2 mt-4 pt-3 border-t border-stone-100">
                <button type="button" class="btn-ghost ui-press !py-1.5 !px-3 !text-[11px]" data-bc-edit="${c.id}">ویرایش</button>
                ${c.is_default ? '' : `<button type="button" class="btn-ghost ui-press !py-1.5 !px-3 !text-[11px] !text-amber-700" data-bc-default="${c.id}">پیش‌فرض کن</button>`}
                <span class="grow"></span>
                <button type="button" class="btn-ghost ui-press !py-1.5 !px-3 !text-[11px] !text-rose-500 hover:!bg-rose-50" data-bc-delete="${c.id}">حذف</button>
            </div>
        </div>`;
    }

    function render() {
        const list = $('#bcList');
        const empty = $('#bcEmpty');

        if (!cards.length) {
            list.innerHTML = '';
            empty.classList.remove('hidden');
            return;
        }

        empty.classList.add('hidden');
        list.innerHTML = cards.map(cardRow).join('');
    }

    /* ---------- مودال ---------- */

    const modal = $('#bcModal');
    const form = $('#bcForm');
    let editingId = null;

    function openModal(card) {
        editingId = card ? card.id : null;

        $('#bcModalTitle').textContent = card ? 'ویرایش کارت بانکی' : 'افزودن کارت بانکی';
        $('#bcCard').value = card?.card_number || '';
        $('#bcSheba').value = card?.sheba_number || '';
        $('#bcAccount').value = card?.account_number || '';
        $('#bcHolder').value = card?.holder_name || '';
        $('#bcOwnerNid').value = ''; // v40 — هر بار برای امنیت از نو گرفته می‌شود
        $('#bcDefault').checked = card ? !!card.is_default : cards.length === 0;

        /* v40 — راهنمای مودال وقتی استعلام فینوتک فعال است */
        const nidWrap = $('#bcNidWrap');
        if (nidWrap) { nidWrap.classList.toggle('hidden', !FINNOTECH); }

        $$('.field-error', form).forEach(el => { el.textContent = ''; el.classList.remove('show'); });
        $$('.field', form).forEach(el => el.classList.remove('invalid'));

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => $('#bcCard').focus(), 60);
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        editingId = null;
    }

    function fieldError(id, message) {
        const err = $('#' + id + 'Error');
        if (err) { err.textContent = message || ''; err.classList.add('show'); }
        const input = $('#' + id);
        if (input) { input.classList.add('invalid'); }
    }

    $('#bcAddBtn').addEventListener('click', () => openModal(null));
    $$('[data-bc-close]').forEach(el => el.addEventListener('click', closeModal));

    /* ---------- ذخیره ---------- */

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const payload = {
            card_number: normNum($('#bcCard').value) || '',
            sheba_number: normNum($('#bcSheba').value) || '',
            account_number: normNum($('#bcAccount').value) || '',
            holder_name: $('#bcHolder').value.trim() || '',
            is_default: $('#bcDefault').checked,
            owner_nid: normNum($('#bcOwnerNid').value) || '', // v40
        };

        /* اعتبارسنجی سمت کلاینت (سرور هم چک سخت دارد) */
        let ok = true;
        if (payload.card_number && !/^\d{16}$/.test(payload.card_number)) {
            fieldError('bcCard', 'شماره کارت باید دقیقاً ۱۶ رقم باشد.');
            ok = false;
        }
        if (payload.sheba_number) {
            const s = payload.sheba_number.startsWith('IR') ? payload.sheba_number : 'IR' + payload.sheba_number;
            if (!/^IR\d{24}$/.test(s)) {
                fieldError('bcSheba', 'شماره شبا معتبر نیست — IR به‌همراه ۲۴ رقم.');
                ok = false;
            }
        }
        if (payload.account_number && !/^[A-Za-z0-9]{4,30}$/.test(payload.account_number)) {
            fieldError('bcAccount', 'شماره حساب باید ۴ تا ۳۰ رقم/حرف انگلیسی باشد.');
            ok = false;
        }
        if (!payload.card_number && !payload.sheba_number && !payload.account_number) {
            fieldError('bcCard', 'حداقل یکی از شماره کارت، شبا یا شماره حساب را وارد کنید.');
            ok = false;
        }
        /* v40 — استعلام فینوتک فعال: کد ملی صاحب کارت الزامی است */
        if (FINNOTECH && payload.card_number && !/^\d{10}$/.test(payload.owner_nid)) {
            fieldError('bcOwnerNid', 'کد ملی ۱۰ رقمی صاحب کارت را وارد کنید (برای احراز مالکیت).');
            ok = false;
        }
        if (!ok) { return; }

        if (payload.sheba_number && !payload.sheba_number.startsWith('IR')) {
            payload.sheba_number = 'IR' + payload.sheba_number;
        }

        const btn = $('#bcSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

        try {
            const res = editingId
                ? await api('/' + editingId, { method: 'PUT', body: payload })
                : await api('', { method: 'POST', body: payload });

            if (res.ok) {
                cards = res.data.data || cards;
                render();
                closeModal();
                App.toast(res.data.message || 'ذخیره شد.', 'success');
            } else {
                const errors = res.data.errors || {};
                const map = { card_number: 'bcCard', sheba_number: 'bcSheba', account_number: 'bcAccount', owner_nid: 'bcOwnerNid' };
                Object.keys(errors).forEach(key => {
                    if (map[key] && errors[key]?.length) { fieldError(map[key], errors[key][0]); }
                });
                App.toast(res.data.message || 'خطا در ذخیره‌سازی.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'ذخیره کارت';
        }
    });

    /* ---------- عملیات ردیف ---------- */

    $('#bcList').addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-bc-edit]');
        const defaultBtn = e.target.closest('[data-bc-default]');
        const deleteBtn = e.target.closest('[data-bc-delete]');

        if (editBtn) {
            const card = cards.find(c => +c.id === +editBtn.dataset.bcEdit);
            if (card) { openModal(card); }
            return;
        }

        if (defaultBtn) {
            defaultBtn.disabled = true;
            try {
                const res = await api('/' + defaultBtn.dataset.bcDefault + '/default', { method: 'PATCH' });
                if (res.ok) {
                    cards = res.data.data || cards;
                    render();
                    App.toast(res.data.message || 'پیش‌فرض شد.', 'success');
                } else {
                    App.toast(res.data.message || 'خطا.', 'error');
                }
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            }
            return;
        }

        if (deleteBtn) {
            const card = cards.find(c => +c.id === +deleteBtn.dataset.bcDelete);
            const label = card ? (card.card_masked || card.sheba_number || 'شماره حساب') : 'این کارت';
            if (!confirm('کارت «' + label + '» حذف شود؟')) { return; }

            deleteBtn.disabled = true;
            try {
                const res = await api('/' + deleteBtn.dataset.bcDelete, { method: 'DELETE' });
                if (res.ok) {
                    cards = res.data.data || cards.filter(c => +c.id !== +deleteBtn.dataset.bcDelete);
                    render();
                    App.toast(res.data.message || 'حذف شد.', 'success');
                } else {
                    App.toast(res.data.message || 'خطا در حذف.', 'error');
                }
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            }
        }
    });

    render();
})();
