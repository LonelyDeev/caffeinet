/**
 * کافی‌نت آنلاین — اسکریپت صفحه «کافی‌نت‌های من»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    let searchTimer = null, currentPage = 1, currentStatus = '';
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const modal = document.getElementById('modal');
    const form = document.getElementById('modal-form');

    const statusColors = {
        amber: 'bg-amber-50 text-amber-700 border border-amber-200',
        emerald: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        rose: 'bg-rose-50 text-rose-600 border border-rose-200',
        stone: 'bg-stone-100 text-stone-500 border border-stone-200',
    };

    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = skeletonRows(5);

        const q = document.getElementById('search-input').value.trim();
        const res = await App.ajax('/organization/coffeenets/data?page=' + page + '&q=' + encodeURIComponent(q) + '&status=' + currentStatus);
        const data = await res.json();
        render(data);
    }

    /* اسکلتون بارگذاری */
    function skeletonRows(cols) {
        let html = '';
        for (let i = 0; i < 4; i++) {
            html += `<tr><td colspan="${cols}" class="!py-4">
                <div class="space-y-2.5">
                    <div class="ui-skeleton h-3 w-full"></div>
                    <div class="ui-skeleton h-3 w-40"></div>
                    <div class="ui-skeleton h-3 w-32"></div>
                </div>
            </td></tr>`;
        }
        return html;
    }

    function render(data) {
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="5" class="!py-10"><div class="ui-empty">' +
                '<span class="ui-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h20"/><path d="M12 7v5"/><rect x="3" y="7" width="18" height="14" rx="1"/></svg></span>' +
                '<p class="text-sm font-semibold text-stone-500">کافی‌نتی یافت نشد.</p>' +
                '<p class="text-xs text-stone-400 mt-1">اولین کافی‌نت را با دکمه «معرفی کافی‌نت جدید» ثبت کنید.</p>' +
                '</div></td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(c => `
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <span class="grid place-items-center size-9 rounded-xl bg-gradient-to-br from-teal-400/70 to-teal-600/70 text-white font-bold text-xs shrink-0">${(c.name || '؟').slice(0, 1)}</span>
                        <div>
                            <p class="font-bold text-stone-800">${c.name}</p>
                            <p class="text-[11px] text-stone-400">${c.phone || '—'}</p>
                        </div>
                    </div>
                </td>
                <td class="text-stone-500">${c.city ? c.city + '، ' : ''}${c.province || '—'}</td>
                <td>${c.reward_paid
                    ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">🏅 واریز شد</span>'
                    : '<span class="text-[11px] text-stone-400">—</span>'}</td>
                <td><span class="badge ${statusColors[c.status.color]}">${c.status.label}</span></td>
                <td class="text-stone-500 text-xs">${c.created_at}</td>
            </tr>
        `).join('');

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${data.total} کافی‌نت</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${data.total} کافی‌نت — صفحه ${data.current_page.toLocaleString('fa-IR')} از ${data.last_page.toLocaleString('fa-IR')}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    /* ---------- مودال ---------- */
    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        form.reset();
        document.getElementById('f-city').disabled = true;
        document.getElementById('f-city').innerHTML = '<option value="">ابتدا استان…</option>';
        form.querySelectorAll('.err').forEach(e => e.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(e => e.classList.remove('field-error'));
    }

    document.getElementById('btn-new').addEventListener('click', openModal);
    form.querySelectorAll('.modal-close').forEach(el => el.addEventListener('click', closeModal));
    modal.querySelector('[data-close]').addEventListener('click', closeModal);

    /* ---------- سلکت آبشاری ---------- */
    async function loadCities(provinceId) {
        const citySelect = document.getElementById('f-city');
        citySelect.disabled = true;
        citySelect.innerHTML = '<option value="">در حال بارگذاری…</option>';
        try {
            const res = await App.ajax('/organization/geo/cities?province_id=' + provinceId);
            const data = await res.json();
            citySelect.innerHTML = '<option value="">— انتخاب شهرستان —</option>' + data.cities.map(c =>
                `<option value="${c.id}">${c.name}</option>`).join('');
            citySelect.disabled = false;
        } catch {
            citySelect.innerHTML = '<option value="">خطا در بارگذاری</option>';
        }
    }

    document.getElementById('f-province').addEventListener('change', function () {
        if (!this.value) {
            const citySelect = document.getElementById('f-city');
            citySelect.disabled = true;
            citySelect.innerHTML = '<option value="">ابتدا استان…</option>';
            return;
        }
        loadCities(this.value);
    });

    /* ---------- ارسال فرم ---------- */
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errEl = form.querySelector('.err[data-for="name"]');
        errEl.classList.add('hidden');
        document.getElementById('f-name').classList.remove('field-error');

        const payload = {
            name: document.getElementById('f-name').value.trim(),
            phone: document.getElementById('f-phone').value.trim() || null,
            province_id: document.getElementById('f-province').value || null,
            city_id: document.getElementById('f-city').value || null,
            address: document.getElementById('f-address').value.trim() || null,
        };

        if (!payload.name) {
            errEl.textContent = 'نام کافی‌نت الزامی است.';
            errEl.classList.remove('hidden');
            document.getElementById('f-name').classList.add('field-error');
            return;
        }

        const btn = document.getElementById('modal-save');
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> در حال ثبت...';

        try {
            const res = await App.ajax('/organization/coffeenets', { method: 'POST', body: payload });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                closeModal();
                App.toast(data.message || 'ثبت شد.', 'success');
                load(1);
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => {
                    const el = form.querySelector(`.err[data-for="${k}"]`);
                    if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
                    const fld = document.getElementById('f-' + k.replace('_id', ''));
                    if (fld) fld.classList.add('field-error');
                });
                App.toast(Object.values(data.errors)[0][0], 'error');
            } else {
                App.toast(data.message || 'خطا در ثبت.', 'error');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    /* ---------- فیلترها ---------- */
    document.getElementById('search-input').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(1), 350);
    });

    document.getElementById('status-filter').addEventListener('change', function () {
        currentStatus = this.value;
        load(1);
    });

    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        load(1);
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
