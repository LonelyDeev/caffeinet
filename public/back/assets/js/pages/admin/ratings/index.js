/**
 * کافی‌net آنلاین — صفحه «نظرسنجی‌های مشتریان» (v33)
 *  ① آمار + توزیع امتیاز + پرتکرارترین دلایل (چیپ‌های کلیک‌پذیر = فیلتر)
 *  ② جدول نظرات با فیلترهای کامل (امتیاز، اپراتور، کافی‌net، دلیل، تاریخ، متن)
 *  ③ مدیریت گزینه‌های دلایل (فقط مدیر کل): افزودن/فعال-غیرفعال/حذف
 */
(function () {
    const PAGE = App.pageData();
    const BASE = PAGE.base;
    const IS_SUPER = !!PAGE.isSuperAdmin;

    let currentPage = 1;
    let searchTimer = null;

    const els = {
        tbody: document.getElementById('rt-tbody'),
        pagination: document.getElementById('rt-pagination'),
        summary: document.getElementById('rt-summary'),
        search: document.getElementById('rt-search'),
        rating: document.getElementById('rt-rating'),
        operatorRating: document.getElementById('rt-operator-rating'),
        coffeenet: document.getElementById('rt-coffeenet'),
        operator: document.getElementById('rt-operator'),
        option: document.getElementById('rt-option'),
        sort: document.getElementById('rt-sort'),
        from: document.getElementById('rt-from'),
        to: document.getElementById('rt-to'),
        clear: document.getElementById('rt-clear'),
    };

    /* ================== ① آمار و توزیع ================== */
    async function loadStats() {
        try {
            const res = await App.ajax(`${BASE}/stats`);
            if (!res.ok) return;
            const s = await res.json();

            document.getElementById('stat-total').textContent = fa(s.total || 0);
            document.getElementById('stat-avg').textContent = s.avg !== null && s.avg !== undefined ? fa(s.avg) : '—';
            document.getElementById('stat-avg-operator').textContent = s.avg_operator !== null && s.avg_operator !== undefined ? fa(s.avg_operator) : '—';
            document.getElementById('stat-low').textContent = fa(s.low || 0);

            const total = Math.max(1, s.total || 0);
            for (let i = 1; i <= 5; i++) {
                const count = (s.distribution && s.distribution[i]) || 0;
                const bar = document.getElementById('dist-' + i);
                const cnt = document.getElementById('distc-' + i);
                if (bar) bar.style.width = Math.round((count / total) * 100) + '%';
                if (cnt) cnt.textContent = fa(count);
            }

            const reasonsBox = document.getElementById('top-reasons');
            if (reasonBox && s.top_reasons) {
                const entries = Object.entries(s.top_reasons);
                reasonBox.innerHTML = entries.length
                    ? entries.map(([title, count]) => `
                        <button type="button" class="rt-reason-chip" data-reason="${escapeHtml(title)}" title="فیلتر نظرات با این دلیل">
                            <span class="rt-reason-title">${escapeHtml(title)}</span>
                            <span class="rt-reason-count">${fa(count)}</span>
                        </button>`).join('')
                    : '<p class="text-xs text-stone-400 text-center py-4">هنوز دلیلی ثبت نشده است</p>';
            }
        } catch { /* بی‌صدا */ }
    }
    const reasonBox = document.getElementById('top-reasons');

    /* ================== ② جدول نظرات ================== */
    async function load(page = 1) {
        currentPage = page;
        els.tbody.innerHTML = '<tr><td colspan="9" class="!py-10 text-center text-stone-400 text-xs">در حال بارگذاری…</td></tr>';

        const params = new URLSearchParams();
        params.set('page', String(page));
        if (els.search.value.trim()) params.set('q', els.search.value.trim());
        if (els.rating.value) params.set('rating', els.rating.value);
        if (els.operatorRating.value) params.set('operator_rating', els.operatorRating.value);
        if (els.coffeenet && els.coffeenet.value) params.set('coffeenet_id', els.coffeenet.value);
        if (els.operator.value) params.set('operator_id', els.operator.value);
        if (els.option.value) params.set('option_id', els.option.value);
        if (els.sort.value) params.set('sort', els.sort.value);
        if (els.from.value) params.set('from', els.from.value);
        if (els.to.value) params.set('to', els.to.value);

        try {
            const res = await App.ajax(`${BASE}/data?${params.toString()}`);
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'خطا');
            renderRows(data);
            renderPagination(data);
        } catch (e) {
            els.tbody.innerHTML = `<tr><td colspan="9" class="!py-10 text-center text-rose-400 text-xs">${escapeHtml(e.message || 'خطا در دریافت لیست.')}</td></tr>`;
        }
    }

    function starRow(value, withLabel) {
        if (value === null || value === undefined) {
            return '<span class="text-[11px] text-stone-300">—</span>';
        }
        const color = value >= 4 ? 'rt-stars--good' : (value <= 2 ? 'rt-stars--bad' : 'rt-stars--mid');
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            stars += `<svg class="${i <= value ? 'on' : ''}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg>`;
        }
        return `<div class="rt-stars ${color}" role="img" aria-label="${fa(value)} از ۵">${stars}<b class="rt-stars-num">${fa(value)}</b></div>`;
    }

    function renderRows(data) {
        if (!data.data.length) {
            els.tbody.innerHTML = `<tr><td colspan="9" class="!py-10">
                <div class="ui-empty">
                    <span class="ui-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg>
                    </span>
                    <p class="text-xs font-bold text-stone-500">نظری یافت نشد</p>
                    <p class="text-[11px] text-stone-400 mt-1">با تغییر فیلترها دوباره تلاش کنید.</p>
                </div>
            </td></tr>`;
            els.summary.textContent = '—';
            els.pagination.innerHTML = '';
            return;
        }

        els.tbody.innerHTML = data.data.map(row => {
            const reasons = (row.options || []).map(o => `
                <span class="rt-chip ${o.type === 'neg' ? 'rt-chip--neg' : ''}">${escapeHtml(o.title)}</span>`).join('');

            const comment = row.comment
                ? `<p class="rt-comment" title="${escapeHtml(row.comment)}">${escapeHtml(row.comment)}</p>`
                : '<span class="text-[11px] text-stone-300">—</span>';

            return `
            <tr class="group">
                <td>${starRow(row.rating)}</td>
                <td>${starRow(row.operator_rating)}</td>
                <td><div class="flex flex-wrap gap-1 max-w-56">${reasons || '<span class="text-[11px] text-stone-300">—</span>'}</div></td>
                <td class="max-w-52">${comment}</td>
                <td>
                    <p class="text-xs font-semibold text-stone-700">${escapeHtml(row.customer_name)}</p>
                    ${row.customer_mobile ? `<p class="text-[10px] text-stone-400 font-mono" dir="ltr">${escapeHtml(row.customer_mobile)}</p>` : ''}
                </td>
                ${IS_SUPER ? `<td class="text-xs text-stone-500">${escapeHtml(row.coffeenet_name || '—')}</td>` : ''}
                <td class="text-xs text-stone-600">${row.operator_name ? escapeHtml(row.operator_name) : '<span class="text-[11px] text-stone-300">—</span>'}</td>
                <td>
                    <a href="${row.view_url}" class="text-[11px] font-mono text-amber-700 hover:underline" dir="ltr" title="صفحهٔ کامل سفارش">${escapeHtml(row.order_number)}</a>
                </td>
                <td class="text-[11px] text-stone-400 whitespace-nowrap">${escapeHtml(row.rated_at_fa || '—')}</td>
            </tr>`;
        }).join('');

        els.summary.textContent = `نمایش ${fa(data.from || 0)} تا ${fa(data.to || 0)} از ${fa(data.total)} نظر`;
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

    /* ================== ③ مدیریت گزینه‌ها (مدیر کل) ================== */
    async function loadOptions() {
        if (!IS_SUPER) return;

        try {
            const res = await App.ajax(`${BASE}/options`);
            if (!res.ok) return;
            const data = await res.json();
            renderOptions(data.data || []);
        } catch { /* بی‌صدا */ }
    }

    function renderOptions(options) {
        const list = document.getElementById('rt-options-list');
        if (!list) return;

        const active = options.filter(o => o.is_active).length;
        const countEl = document.getElementById('rt-options-count');
        if (countEl) countEl.textContent = `${fa(active)} فعال از ${fa(options.length)} گزینه`;

        if (!options.length) {
            list.innerHTML = '<p class="text-xs text-stone-400 text-center py-6 col-span-2">هنوز گزینه‌ای ثبت نشده است.</p>';
            return;
        }

        list.innerHTML = options.map(o => `
            <div class="rt-option-row ${o.is_active ? '' : 'is-off'}" data-id="${o.id}">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <span class="rt-option-check" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <span class="text-xs font-bold text-stone-700 truncate">${escapeHtml(o.title)}</span>
                    <span class="badge ${o.type === 'neg' ? 'bg-rose-50 text-rose-600 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'} !text-[10px] !py-1 shrink-0">${o.type === 'neg' ? 'ضعف' : 'قوت'}</span>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button" class="rt-option-toggle" data-id="${o.id}" data-active="${o.is_active ? '1' : '0'}"
                            title="${o.is_active ? 'غیرفعال‌سازی (دیگر در اپ نمایش داده نمی‌شود)' : 'فعال‌سازی'}">
                        ${o.is_active ? 'غیرفعال' : 'فعال'}
                    </button>
                    <button type="button" class="rt-option-delete" data-id="${o.id}" title="حذف گزینه">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </div>`).join('');
    }

    async function bindOptions() {
        if (!IS_SUPER) return;

        const form = document.getElementById('rt-option-form');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const title = document.getElementById('rt-option-title').value.trim();
            const type = document.getElementById('rt-option-type').value;
            const errToast = (msg) => App.toast(msg, 'error');

            if (title.length < 2) { errToast('متن گزینه باید حداقل ۲ حرف باشد.'); return; }

            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;

            try {
                const res = await App.ajax(`${BASE}/options`, { method: 'POST', body: { title, type } });
                const data = await res.json();
                App.toast(data.message || 'گزینه اضافه شد.', res.ok ? 'success' : 'error');
                if (res.ok) {
                    document.getElementById('rt-option-title').value = '';
                    loadOptions();
                }
            } catch { App.toast('ارتباط با سرور برقرار نشد.', 'error'); }
            finally { btn.disabled = false; }
        });

        document.getElementById('rt-options-list')?.addEventListener('click', async (e) => {
            const toggle = e.target.closest('.rt-option-toggle');
            const del = e.target.closest('.rt-option-delete');

            if (toggle) {
                const id = toggle.dataset.id;
                const makeActive = toggle.dataset.active !== '1';
                try {
                    const res = await App.ajax(`${BASE}/options/${id}`, { method: 'PATCH', body: { is_active: makeActive } });
                    const data = await res.json();
                    App.toast(data.message || 'بروزرسانی شد.', res.ok ? 'success' : 'error');
                    if (res.ok) loadOptions();
                } catch { App.toast('ارتباط با سرور برقرار نشد.', 'error'); }
            }

            if (del) {
                const id = del.dataset.id;
                const row = del.closest('.rt-option-row');
                const title = row?.querySelector('.truncate')?.textContent || 'این گزینه';
                if (!window.confirm(`گزینهٔ «${title}» حذف شود؟ نظرات ثبت‌شدهٔ قبلی دست‌نخورده می‌مانند.`)) return;

                try {
                    const res = await App.ajax(`${BASE}/options/${id}`, { method: 'DELETE' });
                    const data = await res.json();
                    App.toast(data.message || 'حذف شد.', res.ok ? 'success' : 'error');
                    if (res.ok) loadOptions();
                } catch { App.toast('ارتباط با سرور برقرار نشد.', 'error'); }
            }
        });
    }

    /* ================== رویدادها ================== */
    function bindEvents() {
        els.search?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => load(1), 350);
        });

        [els.rating, els.operatorRating, els.coffeenet, els.operator, els.option, els.sort, els.from, els.to]
            .forEach(el => el?.addEventListener('change', () => load(1)));

        els.clear?.addEventListener('click', () => {
            els.search.value = '';
            els.rating.value = '';
            els.operatorRating.value = '';
            if (els.coffeenet) els.coffeenet.value = '';
            els.operator.value = '';
            els.option.value = '';
            els.sort.value = 'newest';
            els.from.value = '';
            els.to.value = '';
            load(1);
        });

        // چیپ‌ها و ردیف‌های توزیع = فیلتر سریع امتیاز
        document.querySelectorAll('[data-rating-filter]').forEach(el => {
            el.addEventListener('click', () => {
                const value = el.dataset.ratingFilter;
                els.rating.value = value;
                load(1);
                document.querySelector('.table-wrap')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        // پرتکرارترین دلایل = جستجوی متن دلیل (روی دیدگاه/متن جستجو می‌کنیم از طریق option id)
        reasonBox?.addEventListener('click', (e) => {
            const chip = e.target.closest('[data-reason]');
            if (!chip) return;
            const title = chip.dataset.reason;
            // گزینهٔ معادل در سلکت دلیل پیدا شود
            const opt = (PAGE.options || []).find(o => o.title === title);
            if (opt && els.option) {
                els.option.value = String(opt.id);
            } else if (els.search) {
                els.search.value = title;
            }
            load(1);
            document.querySelector('.table-wrap')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        // صفحه‌بندی (event delegation — محتوای داینامیک)
        document.addEventListener('click', (e) => {
            const pg = e.target.closest('#rt-pagination .pg-btn');
            if (pg && !pg.disabled) {
                load(parseInt(pg.dataset.page, 10) || 1);
            }
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
        load(1);
        loadStats();
        loadOptions();
        bindOptions();
    }

    window.addEventListener('app:ready', boot, { once: true });
    setTimeout(boot, 2500);
})();
