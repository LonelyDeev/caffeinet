/**
 * کافی‌نت آنلاین — ماژول حذف نرم/دائم مشترک بخش‌های پنل مدیریت کل (v28)
 * ---------------------------------------------------------------------------
 * هر صفحه فهرست بخش (مشتریان/کافی‌net‌ها/کارکنان/مدیران/تیکت‌ها/سفارش‌ها/سازمان‌ها)
 * این ماژول را با نام بخش خودش mount می‌کند:
 *
 *   AdminTrash.mount({ section: 'customers' });
 *
 * وظایف:
 *  ۱) دکمهٔ «حذف‌شده‌ها» در هدر صفحه (با شمار رکوردهای حذف‌شده)
 *  ۲) کلیک روی [data-trash="{id}"] در ردیف‌ها → دیالوگ تأیید با هشدارهای
 *     وابستگی (از GET /admin/{section}/{id}/delete-info) → DELETE
 *  ۳) مودال حذف‌شده‌ها: جستجو + بازگردانی + حذف دائم
 *
 * استایل: public/assets/css/pages/trash.css
 */
(function () {
    'use strict';

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    const TRASH_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>';
    const RESTORE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>';

    function AdminTrash() { }

    AdminTrash.prototype.mount = function (opts) {
        const cfg = opts || {};
        const section = String(cfg.section || '');
        if (!section) { return; }
        const base = '/admin/' + section;

        if (document.body.hasAttribute('data-trash-mounted-' + section)) { return; }
        document.body.setAttribute('data-trash-mounted-' + section, '1');

        /* ---------- ۱) دکمهٔ «حذف‌شده‌ها» در هدر ---------- */
        this.toolbarBtn = null;
        this.injectToolbarButton(base);

        /* ---------- ۲) کلیک روی سطل ردیف‌ها ---------- */
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-trash]');
            if (!btn) { return; }
            e.preventDefault();
            const id = parseInt(btn.getAttribute('data-trash'), 10);
            if (!id) { return; }
            this.confirmAndDelete(base, id, btn.getAttribute('data-trash-label') || '');
        });

        /* شمار حذف‌شده‌ها برای بج */
        this.refreshBadge(base);
    };

    /* دکمهٔ «حذف‌شده‌ها» — داخل اولین هدر کارت صفحه تزریق می‌شود */
    AdminTrash.prototype.injectToolbarButton = function (base) {
        const head = document.querySelector('.adm-card-head') || document.querySelector('.page-toolbar');
        if (!head) { return; }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'trash-toolbar-btn';
        btn.innerHTML = TRASH_ICON +
            '<span>حذف‌شده‌ها</span>' +
            '<b class="trash-toolbar-count" hidden>۰</b>';
        btn.setAttribute('aria-label', 'فهرست رکوردهای حذف‌شده');
        btn.addEventListener('click', () => this.openTrashedModal(base));

        const target = head.querySelector('.flex-1') || head.firstElementChild || head;
        target.insertAdjacentElement('afterend', btn);

        this.toolbarBtn = btn;
    };

    AdminTrash.prototype.refreshBadge = async function (base) {
        try {
            const res = await App.ajax(base + '/trashed');
            if (!res.ok) { return; }
            const data = await res.json().catch(() => ({}));
            const count = (data.data || []).length;
            const badge = this.toolbarBtn?.querySelector('.trash-toolbar-count');
            if (!badge) { return; }
            badge.hidden = count === 0;
            badge.textContent = String(count).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[+d]);
        } catch { /* بی‌صدا */ }
    };

    /* ---------- دیالوگ تأیید حذف (هشدارهای وابستگی) ---------- */
    AdminTrash.prototype.confirmAndDelete = async function (base, id, fallbackLabel) {
        App.toast('در حال بررسی وابستگی‌ها…', 'info');

        let info = null;
        try {
            const res = await App.ajax(base + '/' + id + '/delete-info');
            const data = await res.json().catch(() => ({}));
            if (res.ok) { info = data.data; }
        } catch { /* ادامه با حالت ساده */ }

        const label = info?.label || fallbackLabel || 'این رکورد';
        const warnings = Array.isArray(info?.warnings) ? info.warnings : [];

        if (info && info.deletable === false) {
            window.PanelUI?.alert({
                title: 'حذف مجاز نیست',
                desc: info.reason || 'این رکورد قابل حذف نیست.',
                type: 'warning',
            });
            return;
        }

        const html =
            '<div class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="trash-confirm-title">' +
            '  <div class="ui-modal trash-modal" data-tone="warning">' +
            '    <span class="ui-modal-icon trash-icon">' + TRASH_ICON + '</span>' +
            '    <h3 class="ui-modal-title" id="trash-confirm-title">حذف «' + esc(label) + '»</h3>' +
            '    <p class="ui-modal-desc">این رکورد به <b>لیست حذف‌شده‌ها</b> منتقل می‌شود و تا پیش از «حذف دائم» قابل بازگردانی است.' +
            '      هشدارهای وابستگی را بخوانید:</p>' +
            '    <ul class="trash-warnings" role="list">' +
            warnings.map((w) => '<li>' + esc(w) + '</li>').join('') +
            '    </ul>' +
            '    <div class="ui-modal-actions">' +
            '      <button type="button" class="ui-btn-danger ui-press" data-trash-ok>بله، حذف کن</button>' +
            '      <button type="button" class="btn btn-ghost ui-press" data-trash-cancel>انصراف</button>' +
            '    </div>' +
            '  </div>' +
            '</div>';

        this.runModal(html, {
            '[data-trash-cancel]': () => {},
            '[data-trash-ok]': async (modal) => {
                const okBtn = modal.querySelector('[data-trash-ok]');
                okBtn.disabled = true;
                okBtn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> در حال حذف…';

                try {
                    const res = await App.ajax(base + '/' + id, { method: 'DELETE' });
                    const data = await res.json().catch(() => ({}));

                    if (res.ok) {
                        App.toast(data.message || 'حذف شد و به حذف‌شده‌ها منتقل شد.', 'success');
                        modal.remove();
                        this.refreshBadge(base);
                        /* صفحه‌های فهرست، تابع سراسری reload خودشان را دارند */
                        if (typeof window.location.reload === 'function' && window.__trashReload !== false) {
                            setTimeout(() => window.location.reload(), 700);
                        }
                    } else {
                        App.toast(data.message || 'حذف ناموفق بود.', 'error');
                        okBtn.disabled = false;
                        okBtn.textContent = 'بله، حذف کن';
                    }
                } catch {
                    App.toast('ارتباط با سرور برقرار نشد.', 'error');
                    okBtn.disabled = false;
                    okBtn.textContent = 'بله، حذف کن';
                }
            },
        });
    };

    /* ---------- مودال حذف‌شده‌ها ---------- */
    AdminTrash.prototype.openTrashedModal = async function (base) {
        const html =
            '<div class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="trash-list-title">' +
            '  <div class="ui-modal trash-modal trash-list-modal" data-tone="info">' +
            '    <div class="trash-list-head">' +
            '      <span class="ui-modal-icon trash-icon">' + TRASH_ICON + '</span>' +
            '      <h3 class="ui-modal-title" id="trash-list-title">حذف‌شده‌ها</h3>' +
            '      <input type="search" class="field trash-list-search" placeholder="جستجو در حذف‌شده‌ها…" aria-label="جستجو">' +
            '    </div>' +
            '    <p class="ui-modal-desc">رکوردهای حذف‌شده (نرم). «بازگردانی» آن‌ها را برمی‌گرداند؛ «حذف دائم» رکورد، فایل‌ها و گفتگوهای وابسته را برای همیشه پاک می‌کند.</p>' +
            '    <div class="trash-list" role="list" aria-live="polite">' +
            '      <p class="trash-empty"><span class="nb-spin"></span> در حال بارگذاری…</p>' +
            '    </div>' +
            '    <div class="ui-modal-actions">' +
            '      <button type="button" class="btn btn-ghost ui-press" data-trash-close>بستن</button>' +
            '    </div>' +
            '  </div>' +
            '</div>';

        this.runModal(html, {
            '[data-trash-close]': (modal) => modal.remove(),
        });

        const modal = document.querySelector('.trash-list-modal')?.closest('.ui-modal-backdrop');
        if (!modal) { return; }

        const load = async () => {
            const q = modal.querySelector('.trash-list-search')?.value.trim() || '';
            const listEl = modal.querySelector('.trash-list');
            listEl.innerHTML = '<p class="trash-empty"><span class="nb-spin"></span> در حال بارگذاری…</p>';

            try {
                const res = await App.ajax(base + '/trashed' + (q ? '?q=' + encodeURIComponent(q) : ''));
                const data = await res.json().catch(() => ({}));
                const rows = data.data || [];

                if (!res.ok) {
                    listEl.innerHTML = '<p class="trash-empty">خطا در دریافت حذف‌شده‌ها.</p>';
                    return;
                }

                if (!rows.length) {
                    listEl.innerHTML = '<p class="trash-empty">مورد حذف‌شده‌ای' + (q ? ' با این جستجو ' : ' ') + 'وجود ندارد.</p>';
                    return;
                }

                listEl.innerHTML = rows.map((r) => `
                    <div class="trash-row" role="listitem">
                        <div class="trash-row-body">
                            <strong>${esc(r.label)}</strong>
                            <small>${esc(r.sub || '')} · حذف: ${esc(r.deleted_at_fa)}</small>
                        </div>
                        <div class="trash-row-actions">
                            <button type="button" class="btn btn-ghost ui-press trash-restore" data-id="${r.id}" title="بازگردانی به حالت فعال">
                                ${RESTORE_ICON} بازگردانی
                            </button>
                            <button type="button" class="ui-btn-danger ui-press trash-purge" data-id="${r.id}" data-label="${esc(r.label)}">
                                ${TRASH_ICON} حذف دائم
                            </button>
                        </div>
                    </div>`).join('');
            } catch {
                listEl.innerHTML = '<p class="trash-empty">ارتباط با سرور برقرار نشد.</p>';
            }
        };

        modal.querySelector('.trash-list-search')?.addEventListener('input', (() => {
            let t = null;
            return () => { clearTimeout(t); t = setTimeout(load, 350); };
        })());

        modal.querySelector('.trash-list').addEventListener('click', (e) => {
            const restoreBtn = e.target.closest('.trash-restore');
            const purgeBtn = e.target.closest('.trash-purge');

            if (restoreBtn) {
                this.doAction(base, parseInt(restoreBtn.getAttribute('data-id'), 10), 'restore', 'POST', restoreBtn, load);
            } else if (purgeBtn) {
                const pid = parseInt(purgeBtn.getAttribute('data-id'), 10);
                const plabel = purgeBtn.getAttribute('data-label') || 'این رکورد';

                window.PanelUI?.confirm({
                    title: 'حذف دائم «' + plabel + '»',
                    desc: 'این عمل غیرقابل بازگشت است؛ رکورد، فایل‌ها/تصاویر و گفتگوهای وابسته برای همیشه پاک می‌شوند. مطمئن هستید؟',
                    okText: 'بله، حذف دائم کن',
                    cancelText: 'انصراف',
                    danger: true,
                }, () => {
                    this.doAction(base, pid, 'purge', 'DELETE', purgeBtn, load);
                });
            }
        });

        load();
    };

    /* اجرای restore/purge */
    AdminTrash.prototype.doAction = async function (base, id, action, method, btn, onDone) {
        if (!id) { return; }
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="size-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>';

        try {
            const res = await App.ajax(base + '/' + id + '/' + action, { method });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'انجام شد.', 'success');
                this.refreshBadge(base);
                if (onDone) { onDone(); }
            } else {
                App.toast(data.message || 'عملیات ناموفق بود.', 'error');
                btn.disabled = false;
                btn.innerHTML = original;
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
            btn.disabled = false;
            btn.innerHTML = original;
        }
    };

    /* ابزار: ساخت مودال + اتصال انتخابگرها */
    AdminTrash.prototype.runModal = function (html, handlers) {
        const wrap = document.createElement('div');
        wrap.innerHTML = html;
        const modal = wrap.firstElementChild;
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';

        const close = () => {
            modal.classList.add('ui-closing');
            setTimeout(() => { modal.remove(); }, 200);
            if (!document.querySelector('.ui-modal-backdrop:not(.ui-closing)')) {
                document.body.style.overflow = '';
            }
        };

        /* Escape + کلیک پس‌زمینه = بستن (مگر در حال پردازش) */
        const onKey = (e) => {
            if (e.key === 'Escape' && !modal.querySelector('[data-trash-ok][disabled]')) { close(); }
        };
        document.addEventListener('keydown', onKey);
        modal.addEventListener('click', (e) => {
            if (e.target === modal && !modal.querySelector('[data-trash-ok][disabled]')) { close(); }
        });

        Object.entries(handlers || {}).forEach(([sel, fn]) => {
            modal.querySelectorAll(sel).forEach((el) => {
                el.addEventListener('click', (e) => {
                    e.stopPropagation();
                    fn(modal, close);
                });
            });
        });
    };

    window.AdminTrash = new AdminTrash();
})();
