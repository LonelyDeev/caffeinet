/**
 * کافی‌نت آنلاین — اسکریپت صفحه «لاگ پیامک‌ها» (v16)
 * فیلتر (جستجو/وضعیت/پرووایدر/بازه تاریخ شمسی) + جدول + صفحه‌بندی + مودال جزئیات
 */
/* global App, CNJdp */
(function () {
    'use strict';

    let currentPage = 1;
    let searchTimer = null;
    let lastRows = [];

    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const detailModal = document.getElementById('detail-modal');

    const esc = v => String(v ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    const faNum = n => (Number(n) || 0).toLocaleString('fa-IR', { maximumFractionDigits: 0 });

    const PROVIDER_LABELS = {
        log: 'لاگ توسعه', kavenegar: 'کاوه‌نگار', fraasms: 'فراز اس‌ام‌اس',
        ippanel: 'آی‌پی‌پنل', melipayamak: 'ملی‌پیامک', idehpardazan: 'ایده‌پردازان',
    };

    /* ---------- بارگذاری ---------- */
    async function load(page) {
        page = page || currentPage;

        const params = new URLSearchParams({ page });
        const q = document.getElementById('f-q').value.trim();
        const status = document.getElementById('f-status').value;
        const provider = document.getElementById('f-provider').value;
        const from = document.getElementById('f-from').value.trim();
        const to = document.getElementById('f-to').value.trim();

        if (q) params.set('q', q);
        if (status) params.set('status', status);
        if (provider) params.set('provider', provider);
        if (from) params.set('from', from);
        if (to) params.set('to', to);

        rows.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        try {
            const res = await App.ajax('/admin/sms-logs/data?' + params.toString());
            const data = await res.json();
            currentPage = data.current_page || 1;
            lastRows = data.data || [];

            // آمار
            if (data.stats) {
                document.getElementById('stat-total').textContent = faNum(data.stats.total);
                document.getElementById('stat-sent').textContent = faNum(data.stats.sent);
                document.getElementById('stat-failed').textContent = faNum(data.stats.failed);
                document.getElementById('stat-today').textContent = faNum(data.stats.today);
            }

            if (!lastRows.length) {
                rows.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-stone-400 text-xs">موردی یافت نشد.</td></tr>';
                pagination.innerHTML = '';
                return;
            }

            rows.innerHTML = lastRows.map(r => `
            <tr>
                <td class="font-mono text-xs text-stone-600" dir="ltr">${esc(r.mobile)}</td>
                <td>
                    ${r.template_key
                        ? `<span class="badge bg-amber-50 text-amber-700 border border-amber-200">${esc(r.template_title || r.template_key)}</span>`
                        : '<span class="text-stone-300 text-xs">—</span>'}
                </td>
                <td class="max-w-56 truncate text-xs text-stone-500" title="${esc(r.message)}">${esc(r.message)}</td>
                <td><span class="badge bg-stone-100 text-stone-500 border border-stone-200">${esc(PROVIDER_LABELS[r.provider] || r.provider)}${r.mode === 'pattern' ? ' · پترنی' : ''}</span></td>
                <td>
                    ${r.status === 'sent'
                        ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">ارسال موفق</span>'
                        : '<span class="badge bg-rose-50 text-rose-700 border border-rose-200">ناموفق</span>'}
                </td>
                <td class="text-[11px] text-stone-500" dir="ltr">${esc(r.created_at)}</td>
                <td class="text-center">
                    <button class="act-detail ui-row-btn" data-i="${lastRows.indexOf(r)}" title="جزئیات" aria-label="مشاهده جزئیات پیامک">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 11h.01"/></svg>
                    </button>
                </td>
            </tr>`).join('');

            rows.querySelectorAll('.act-detail').forEach(b => {
                b.addEventListener('click', () => showDetail(lastRows[+b.dataset.i]));
            });

            if (data.last_page <= 1) {
                pagination.innerHTML = `<span>${faNum(data.total)} رکورد</span>`;
            } else {
                pagination.innerHTML = `
                    <span>${faNum(data.total)} رکورد — صفحه ${faNum(data.current_page)} از ${faNum(data.last_page)}</span>
                    <div class="flex gap-2">
                        <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                        <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
                    </div>`;
                pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
            }
        } catch {
            rows.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-rose-500 text-xs">بارگذاری لاگ ناموفق بود — دوباره تلاش کنید.</td></tr>';
        }
    }

    /* ---------- مودال جزئیات ---------- */
    function showDetail(r) {
        if (!r) return;

        document.getElementById('sl-d-title').textContent = 'جزئیات پیامک #' + faNum(r.id);
        document.getElementById('sl-d-mobile').textContent = r.mobile || '—';

        const prov = PROVIDER_LABELS[r.provider] || r.provider;
        document.getElementById('sl-d-provider').textContent =
            prov + (r.mode === 'pattern' ? ' · ارسال پترنی' : ' · ارسال متنی');

        document.getElementById('sl-d-template').textContent =
            r.template_key ? (r.template_title ? `${r.template_title} (${r.template_key})` : r.template_key) : 'ارسال خارج از قالب';

        document.getElementById('sl-d-status').textContent =
            (r.status === 'sent' ? 'ارسال موفق' : 'ناموفق') + ' · ' + (r.created_at || '—');

        document.getElementById('sl-d-message').textContent = r.message || '—';

        document.getElementById('sl-d-response').textContent =
            r.response && Object.keys(r.response).length
                ? JSON.stringify(r.response, null, 2)
                : '—';

        detailModal.classList.remove('hidden');
        detailModal.classList.add('flex');
    }

    detailModal.querySelectorAll('[data-close]').forEach(el => {
        el.addEventListener('click', () => {
            detailModal.classList.add('hidden');
            detailModal.classList.remove('flex');
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !detailModal.classList.contains('hidden')) {
            detailModal.classList.add('hidden');
            detailModal.classList.remove('flex');
        }
    });

    /* ---------- فیلترها ---------- */
    document.getElementById('btn-filter').addEventListener('click', () => load(1));

    document.getElementById('f-q').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(1), 350);
    });

    document.getElementById('f-status').addEventListener('change', () => load(1));
    document.getElementById('f-provider').addEventListener('change', () => load(1));

    document.getElementById('btn-reset').addEventListener('click', () => {
        document.getElementById('f-q').value = '';
        document.getElementById('f-status').value = '';
        document.getElementById('f-provider').value = '';
        document.getElementById('f-from').value = '';
        document.getElementById('f-to').value = '';
        load(1);
    });

    /* ---------- v29 — حذف دوره‌ای (نگهداشت + پاکسازی قدیمی‌ها) ---------- */
    const lgRetCard = document.querySelector('.lg-ret[data-scope="sms_logs"]');

    if (lgRetCard) {
        const daysInput = document.getElementById('lg-ret-days');
        const daysBadge = document.querySelector('[data-lg-ret-days]');
        const oldBadge = document.querySelector('[data-lg-ret-old]');
        const saveBtn = document.getElementById('lg-ret-save');
        const cleanBtn = document.getElementById('lg-ret-clean');
        const faNum2 = n => (Number(n) || 0).toLocaleString('fa-IR', { maximumFractionDigits: 0 });

        saveBtn?.addEventListener('click', async () => {
            const days = Math.max(1, Math.min(3650, parseInt(daysInput.value, 10) || 0));
            saveBtn.disabled = true;

            try {
                const res = await App.ajax('/admin/system/retention', { method: 'POST', body: { sms_logs: days } });
                const data = await res.json().catch(() => ({}));
                App.toast(data.message || (res.ok ? 'ذخیره شد.' : 'خطا در ذخیره‌سازی.'), res.ok ? 'success' : 'error');

                if (res.ok && daysBadge) { daysBadge.textContent = faNum2(days); }
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            } finally {
                saveBtn.disabled = false;
            }
        });

        cleanBtn?.addEventListener('click', () => {
            if (cleanBtn.disabled) { return; }

            window.PanelUI.confirm({
                title: 'پاکسازی لاگ پیامک‌های قدیمی؟',
                desc: 'همهٔ ردیف‌های قدیمی‌تر از نگهداشت فعلی، از قدیمی‌ترین حذف می‌شوند. این عمل قابل بازگشت نیست.',
                okText: 'حذف قدیمی‌ها',
                danger: true,
                icon: 'question',
            }, async () => {
                cleanBtn.disabled = true;

                try {
                    const res = await App.ajax('/admin/system/cleanup', { method: 'POST', body: { scope: 'sms_logs' } });
                    const data = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        App.toast(data.message || 'خطا در پاکسازی.', 'error');
                        return;
                    }

                    App.toast(data.message || 'پاکسازی اجرا شد.', 'success');

                    if (oldBadge) { oldBadge.textContent = faNum2(0) + ' ردیف قدیمی‌تر از نگهداشت'; }
                    load(1);
                } catch {
                    App.toast('ارتباط با سرور برقرار نشد.', 'error');
                } finally {
                    cleanBtn.disabled = false;
                }
            });
        });
    }

    /* ---------- بارگذاری اولیه (با صبر برای core.js) ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        load(1);
    }

    if (typeof window.App !== 'undefined') {
        boot();
    } else {
        window.addEventListener('app:ready', boot, { once: true });
        setTimeout(boot, 2500);
    }
})();
