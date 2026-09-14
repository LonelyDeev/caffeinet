/**
 * کافی‌نت آنلاین — اسکریپت صفحه «لاگ سیستمی لاراول» (v38)
 *
 * فایل مستقل (Blade + vanilla JS) — بدون Node / بدون بیلد.
 *  • بارگذاری AJAX ورودی‌ها با فیلتر سطح + جستجو + صفحه‌بندی
 *  • چیپ‌های سطح با شمارندهٔ زنده (سمت سرور)
 *  • مودال جزئیات (متن کامل + stack trace)
 *  • خالی‌کردن / حذف کامل فایل با confirm خطرناک
 */
(function () {
    const rowsEl = document.getElementById('slg-rows');
    if (!rowsEl) { return; } // صفحهٔ حالت خالی

    const qEl = document.getElementById('slg-q');
    const perPageEl = document.getElementById('slg-perpage');
    const paginationEl = document.getElementById('slg-pagination');
    const detailModal = document.getElementById('slg-detail-modal');
    const mainCard = document.getElementById('slg-main');

    const state = {
        page: 1,
        level: '',
        q: '',
        perPage: 25,
        file: mainCard ? (mainCard.dataset.file || '') : '',
    };

    let searchTimer = null;
    let lastRows = [];

    /* ---------- آیکون‌های سطح ---------- */
    const LEVEL_ICONS = {
        emergency: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z"/></svg>',
        alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6"/><path d="M12 12v4"/><path d="M12 2h0"/></svg>',
        critical: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
        notice: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
        debug: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>',
    };

    const esc = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    /* ---------- بارگذاری ---------- */
    async function load(page) {
        state.page = page || state.page || 1;

        const params = new URLSearchParams({
            page: state.page,
            per_page: state.perPage,
        });
        if (state.file) { params.set('file', state.file); }
        if (state.level) { params.set('level', state.level); }
        if (state.q) { params.set('q', state.q); }

        rowsEl.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        try {
            const res = await App.ajax('/admin/settings/logs/data?' + params.toString());
            const data = await res.json();

            if (!res.ok) {
                rowsEl.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-stone-400 text-xs">خطا در بارگذاری لاگ‌ها.</td></tr>';
                App.toast(data.message || 'خطا در بارگذاری لاگ‌ها.', 'error');
                return;
            }

            render(data);
        } catch {
            rowsEl.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-stone-400 text-xs">ارتباط با سرور برقرار نشد.</td></tr>';
        }
    }

    function render(data) {
        state.page = data.current_page || 1;
        lastRows = data.data || [];

        if (!lastRows.length) {
            rowsEl.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-stone-400 text-xs">موردی یافت نشد.' +
                (data.truncated ? ' (فقط آخرین ۸ مگابایت فایل جستجو شده)' : '') + '</td></tr>';
            paginationEl.innerHTML = '';
            return;
        }

        rowsEl.innerHTML = lastRows.map((r) => `
            <tr class="cursor-pointer slg-row" data-i="${lastRows.indexOf(r)}">
                <td>
                    <span class="slg-lvl-badge slg-tone-${r.tone}">${LEVEL_ICONS[r.level] || LEVEL_ICONS.debug}${esc(r.level_fa)}</span>
                </td>
                <td><span class="slg-env">${esc(r.env)}</span></td>
                <td class="slg-time">
                    ${esc(r.date_fa)} — ${esc(r.time_fa)}
                    <small title="${esc(r.ts)}">${esc(r.ts)}</small>
                </td>
                <td>
                    <div class="slg-msg">${esc(r.message)}</div>
                    ${r.trace ? '<span class="slg-has-trace"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>دارای stack trace</span>' : ''}
                </td>
                <td class="text-center">
                    <button class="act-detail ui-row-btn" data-i="${lastRows.indexOf(r)}" title="جزئیات" aria-label="مشاهدهٔ جزئیات">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 11h.01"/></svg>
                    </button>
                </td>
            </tr>`).join('');

        rowsEl.querySelectorAll('.slg-row').forEach((tr) => {
            tr.addEventListener('click', (e) => {
                if (e.target.closest('button, a')) { return; }
                openDetail(+tr.dataset.i);
            });
        });
        rowsEl.querySelectorAll('.act-detail').forEach((b) => {
            b.addEventListener('click', () => openDetail(+b.dataset.i));
        });

        const total = data.total || 0;
        const faNum = (n) => (Number(n) || 0).toLocaleString('fa-IR');

        if ((data.last_page || 1) <= 1) {
            paginationEl.innerHTML = `<span>${faNum(total)} ورودی</span>`;
        } else {
            paginationEl.innerHTML = `
                <span>${faNum(total)} ورودی — صفحه ${faNum(data.current_page)} از ${faNum(data.last_page)}</span>
                <div class="flex gap-2">
                    <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                    <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
                </div>`;
            paginationEl.querySelectorAll('.pg-btn').forEach((b) => {
                b.addEventListener('click', () => {
                    if (!b.disabled) { load(+b.dataset.page); }
                });
            });
        }
    }

    /* ---------- مودال جزئیات ---------- */
    function openDetail(i) {
        const r = lastRows[i];
        if (!r) { return; }

        document.getElementById('slg-d-meta').innerHTML = `
            <span class="slg-lvl-badge slg-tone-${r.tone}">${LEVEL_ICONS[r.level] || LEVEL_ICONS.debug}${esc(r.level_fa)}</span>
            <span class="badge bg-stone-100 text-stone-500 border border-stone-200" dir="ltr">${esc(r.env)}</span>
            <span class="badge bg-stone-100 text-stone-500 border border-stone-200">${esc(r.date_fa)} — ${esc(r.time_fa)}</span>
            <span class="badge bg-stone-100 text-stone-500 border border-stone-200" dir="ltr" title="${esc(r.ts)}">${esc(r.ts)}</span>`;

        document.getElementById('slg-d-msg').textContent = r.message || '—';

        const traceZone = document.getElementById('slg-d-trace-zone');
        const traceEl = document.getElementById('slg-d-trace');
        if (r.trace) {
            traceZone.classList.remove('hidden');
            traceEl.textContent = r.trace;
        } else {
            traceZone.classList.add('hidden');
        }

        detailModal.classList.remove('hidden');
        detailModal.classList.add('flex');
    }

    detailModal?.querySelectorAll('[data-close]').forEach((el) => {
        el.addEventListener('click', () => {
            detailModal.classList.add('hidden');
            detailModal.classList.remove('flex');
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && detailModal && !detailModal.classList.contains('hidden')) {
            detailModal.classList.add('hidden');
            detailModal.classList.remove('flex');
        }
    });

    /* ---------- چیپ‌های سطح ---------- */
    document.querySelectorAll('.slg-level').forEach((chip) => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.slg-level').forEach((c) => c.classList.remove('is-on'));
            chip.classList.add('is-on');
            state.level = chip.dataset.level || '';
            load(1);
        });
    });

    /* ---------- جستجو + تعداد در صفحه ---------- */
    qEl?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.q = qEl.value.trim();
            load(1);
        }, 350);
    });

    perPageEl?.addEventListener('change', () => {
        state.perPage = parseInt(perPageEl.value, 10) || 25;
        load(1);
    });

    /* ---------- خالی‌کردن / حذف فایل ---------- */
    const clearBtn = document.getElementById('slg-clear-btn');
    const deleteBtn = document.getElementById('slg-delete-btn');
    const activeFile = mainCard ? (mainCard.dataset.file || '') : '';

    clearBtn?.addEventListener('click', () => {
        window.PanelUI.confirm({
            title: 'خالی‌کردن فایل لاگ؟',
            desc: 'همهٔ ورودی‌های فایل «' + activeFile + '» پاک می‌شود ولی خود فایل باقی می‌ماند و لاگ‌های جدید در همان ادامه می‌یابند. این عمل قابل بازگشت نیست.',
            okText: 'بله، خالی کن',
            danger: true,
            icon: 'warning',
        }, async () => {
            clearBtn.disabled = true;

            try {
                const res = await App.ajax('/admin/settings/logs/clear', { method: 'POST', body: { file: activeFile } });
                const data = await res.json().catch(() => ({}));

                if (res.ok) {
                    App.toast(data.message || 'فایل خالی شد.', 'success');
                    setTimeout(() => window.location.reload(), 700);
                } else {
                    App.toast(data.message || 'خطا در خالی‌کردن فایل.', 'error');
                    clearBtn.disabled = false;
                }
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
                clearBtn.disabled = false;
            }
        });
    });

    deleteBtn?.addEventListener('click', () => {
        window.PanelUI.confirm({
            title: 'حذف کامل فایل لاگ؟',
            desc: 'فایل «' + activeFile + '» به‌طور کامل از دیسک حذف می‌شود (بازگشتی وجود ندارد). لاراول در صورت نیاز فایل را دوباره می‌سازد.',
            okText: 'بله، حذف کن',
            danger: true,
            icon: 'warning',
        }, async () => {
            deleteBtn.disabled = true;

            try {
                const res = await App.ajax('/admin/settings/logs/file', { method: 'DELETE', body: { file: activeFile } });
                const data = await res.json().catch(() => ({}));

                if (res.ok) {
                    App.toast(data.message || 'فایل حذف شد.', 'success');
                    setTimeout(() => window.location.reload(), 700);
                } else {
                    App.toast(data.message || 'خطا در حذف فایل.', 'error');
                    deleteBtn.disabled = false;
                }
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
                deleteBtn.disabled = false;
            }
        });
    });

    /* ---------- بارگذاری اولیه ---------- */
    let booted = false;
    function boot() {
        if (booted) { return; }
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
