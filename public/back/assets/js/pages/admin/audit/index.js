/**
 * کافی‌نت آنلاین — اسکریپت صفحه «لاگ فعالیت»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    let currentPage = 1, searchTimer = null;
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const detailModal = document.getElementById('detail-modal');

    const actionTone = (action) => {
        if (action.startsWith('auth.login')) return ['bg-emerald-50 text-emerald-700 border-emerald-200', 'ورود'];
        if (action.startsWith('auth.logout')) return ['bg-stone-100 text-stone-500 border-stone-200', 'خروج'];
        if (action.startsWith('admin')) return ['bg-amber-50 text-amber-700 border-amber-200', 'مدیر'];
        if (action.startsWith('settings')) return ['bg-sky-50 text-sky-700 border-sky-200', 'تنظیمات'];
        if (action.startsWith('sms')) return ['bg-violet-50 text-violet-600 border-violet-200', 'پیامک'];
        return ['bg-stone-100 text-stone-500 border-stone-200', 'سیستم'];
    };

    async function load(page) {
        page = page || currentPage;
        const params = new URLSearchParams({ page });
        const q = document.getElementById('f-q').value.trim();
        const from = document.getElementById('f-from-g')?.value || '';
        const to = document.getElementById('f-to-g')?.value || '';
        if (q) params.set('q', q);
        if (from) params.set('from', from);
        if (to) params.set('to', to);

        rows.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        const res = await App.ajax('/admin/audit-logs/data?' + params.toString());
        const data = await res.json();
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-stone-400 text-xs">موردی یافت نشد.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(r => {
            const [toneCls, label] = actionTone(r.action);
            return `
            <tr>
                <td class="font-semibold text-stone-700">${r.user}</td>
                <td>
                    <span class="badge ${toneCls}">${label}</span>
                    <span class="font-mono text-[10px] text-stone-400 ms-1" dir="ltr">${r.action}</span>
                </td>
                <td class="max-w-64 truncate text-stone-500">${r.description}</td>
                <td class="text-stone-400">${r.auditable || '—'}</td>
                <td class="font-mono text-[11px] text-stone-400" dir="ltr">${r.ip}</td>
                <td class="text-[11px] text-stone-500" dir="ltr">${r.created_at}</td>
                <td class="text-center">
                    ${r.old_values || r.new_values
                        ? `<button class="act-detail ui-row-btn" data-i="${data.data.indexOf(r)}" title="جزئیات" aria-label="مشاهده جزئیات">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 11h.01"/></svg>
                        </button>`
                        : '<span class="text-stone-300">—</span>'}
                </td>
            </tr>`;
        }).join('');

        rows.querySelectorAll('.act-detail').forEach(b => b.addEventListener('click', () => {
            const r = data.data[+b.dataset.i];
            document.getElementById('d-title').textContent = 'جزئیات: ' + r.action;
            document.getElementById('d-old').textContent = r.old_values ? JSON.stringify(r.old_values, null, 2) : '—';
            document.getElementById('d-new').textContent = r.new_values ? JSON.stringify(r.new_values, null, 2) : '—';
            detailModal.classList.remove('hidden');
            detailModal.classList.add('flex');
        }));

        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${data.total} رکورد</span>`;
        } else {
            pagination.innerHTML = `
                <span>${data.total} رکورد — صفحه ${data.current_page} از ${data.last_page}</span>
                <div class="flex gap-2">
                    <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                    <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
                </div>`;
            pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
        }
    }

    detailModal.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', () => {
        detailModal.classList.add('hidden');
        detailModal.classList.remove('flex');
    }));

    document.getElementById('btn-filter').addEventListener('click', () => load(1));
    document.getElementById('f-q').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(1), 350);
    });

    /* ---------- v29 — حذف دوره‌ای (نگهداشت + پاکسازی قدیمی‌ها) ---------- */
    const lgRetCard = document.querySelector('.lg-ret[data-scope="audit_logs"]');

    if (lgRetCard) {
        const daysInput = document.getElementById('lg-ret-days');
        const daysBadge = document.querySelector('[data-lg-ret-days]');
        const oldBadge = document.querySelector('[data-lg-ret-old]');
        const saveBtn = document.getElementById('lg-ret-save');
        const cleanBtn = document.getElementById('lg-ret-clean');
        const faNum = n => (Number(n) || 0).toLocaleString('fa-IR', { maximumFractionDigits: 0 });

        saveBtn?.addEventListener('click', async () => {
            const days = Math.max(7, Math.min(3650, parseInt(daysInput.value, 10) || 0));
            saveBtn.disabled = true;

            try {
                const res = await App.ajax('/admin/system/retention', { method: 'POST', body: { audit_logs: days } });
                const data = await res.json().catch(() => ({}));
                App.toast(data.message || (res.ok ? 'ذخیره شد.' : 'خطا در ذخیره‌سازی.'), res.ok ? 'success' : 'error');

                if (res.ok && daysBadge) { daysBadge.textContent = faNum(days); }
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            } finally {
                saveBtn.disabled = false;
            }
        });

        cleanBtn?.addEventListener('click', () => {
            if (cleanBtn.disabled) { return; }

            window.PanelUI.confirm({
                title: 'پاکسازی لاگ فعالیت‌های قدیمی؟',
                desc: 'همهٔ ردیف‌های قدیمی‌تر از نگهداشت فعلی، از قدیمی‌ترین حذف می‌شوند. این عمل قابل بازگشت نیست.',
                okText: 'حذف قدیمی‌ها',
                danger: true,
                icon: 'question',
            }, async () => {
                cleanBtn.disabled = true;

                try {
                    const res = await App.ajax('/admin/system/cleanup', { method: 'POST', body: { scope: 'audit_logs' } });
                    const data = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        App.toast(data.message || 'خطا در پاکسازی.', 'error');
                        return;
                    }

                    App.toast(data.message || 'پاکسازی اجرا شد.', 'success');

                    if (oldBadge) { oldBadge.textContent = faNum(0) + ' ردیف قدیمی‌تر از نگهداشت'; }
                    load(1);
                } catch {
                    App.toast('ارتباط با سرور برقرار نشد.', 'error');
                } finally {
                    cleanBtn.disabled = false;
                }
            });
        });
    }

    /* ---------- بارگذاری اولیه (با صبر برای app.js) ---------- */
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
