/**
 * کافی‌نت آنلاین — اسکریپت صفحه «حقوق و دستمزد»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    const PAGE = App.pageData();
    const STAFF = PAGE.staff;
    const BASE = PAGE.base;
    const TYPE_COLORS = {
        monthly: 'bg-stone-50 text-stone-600 border border-stone-200',
        overtime: 'bg-amber-50 text-amber-700 border border-amber-200',
        bonus: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        manual: 'bg-stone-50 text-stone-500 border border-stone-200',
    };

    let currentPage = 1;

    const els = {
        tbody: document.getElementById('logs-tbody'),
        pagination: document.getElementById('logs-pagination'),
        fUser: document.getElementById('f-user'),
        fType: document.getElementById('f-type'),
        fPeriod: document.getElementById('f-period'),
        form: document.getElementById('salary-form'),
        formError: document.getElementById('salary-error'),
        user: document.getElementById('s-user'),
        period: document.getElementById('s-period'),
        type: document.getElementById('s-type'),
        amount: document.getElementById('s-amount'),
        desc: document.getElementById('s-desc'),
        pay: document.getElementById('btn-pay'),
    };

    /* پر کردن سلکت کارمندان (فرم + فیلتر) */
    function fillStaffSelects() {
        const options = STAFF.map(s => {
            const salaryHint = s.salary ? ` — ${s.salary.type_label}` : '';
            return `<option value="${s.user_id}">${escapeHtml(s.full_name)}${escapeHtml(salaryHint)}</option>`;
        }).join('');

        els.user.innerHTML = '<option value="">— انتخاب کارمند —</option>' + options;
        els.fUser.innerHTML = '<option value="">همه کارمندان</option>' + options;
    }

    /* نمایش مدل حقوق زیر انتخاب کارمند */
    function showSalaryHint() {
        const id = +els.user.value;
        const box = document.getElementById('salary-hint');
        if (box) box.remove();
        if (!id) return;
        const member = STAFF.find(s => s.user_id === id);
        if (!member || !member.salary) return;
        const rate = member.salary.type === 'percent'
            ? App.money(member.salary.rate, false) + '٪'
            : App.money(member.salary.rate);
        const p = document.createElement('p');
        p.id = 'salary-hint';
        p.className = 'text-[10px] text-stone-400 mt-1.5 leading-5';
        p.textContent = `قرارداد فعلی: ${member.salary.type_label} — ${rate}`;
        els.user.closest('div').appendChild(p);
    }

    /* ---------- لیست لاگ‌ها ---------- */
    async function load(page = 1) {
        currentPage = page;
        els.tbody.innerHTML = skeletonRows(7);

        const params = new URLSearchParams();
        if (els.fUser.value) params.set('user_id', els.fUser.value);
        if (els.fType.value) params.set('type', els.fType.value);
        if (els.fPeriod.value) params.set('period', els.fPeriod.value);
        params.set('page', page);

        try {
            const res = await App.ajax(`${BASE}/data?${params}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            currentPage = data.current_page;
            renderRows(data);
            renderPagination(data);
        } catch {
            els.tbody.innerHTML = '<tr><td colspan="7" class="!py-10 text-center text-rose-400 text-xs">خطا در دریافت تاریخچه.</td></tr>';
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

    function renderRows(data) {
        if (!data.data.length) {
            els.tbody.innerHTML = `<tr><td colspan="7" class="!py-10">
                <div class="ui-empty">
                    <span class="ui-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
                    </span>
                    <p class="text-sm font-semibold text-stone-500">پرداختی ثبت نشده است</p>
                    <p class="text-xs text-stone-400 mt-1">اولین پرداخت را از فرم «ثبت پرداخت جدید» ثبت کنید.</p>
                </div>
            </td></tr>`;
            return;
        }

        els.tbody.innerHTML = data.data.map(log => `
            <tr>
                <td class="text-xs font-bold text-stone-700">${escapeHtml(log.user.full_name)}</td>
                <td class="text-[11px] text-stone-500 whitespace-nowrap">${escapeHtml(log.period_label)}</td>
                <td><span class="badge ${TYPE_COLORS[log.type] || TYPE_COLORS.manual}">${escapeHtml(log.type_label)}</span></td>
                <td class="text-xs font-extrabold tabular-nums text-stone-700 whitespace-nowrap">${App.money(log.amount)}</td>
                <td class="text-[11px] text-stone-500 max-w-40 truncate" title="${escapeHtml(log.description || '')}">${escapeHtml(log.description || '—')}</td>
                <td class="text-[11px] text-stone-500">${escapeHtml(log.logged_by)}</td>
                <td class="text-[11px] text-stone-400 whitespace-nowrap">${escapeHtml(log.created_at)}</td>
            </tr>`).join('');
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            els.pagination.innerHTML = `<span class="text-[11px] text-stone-400">${fa(data.total)} رکورد</span>`;
            return;
        }
        els.pagination.innerHTML = `
            <span class="text-[11px] text-stone-400">${fa(data.total)} رکورد — صفحه ${fa(data.current_page)} از ${fa(data.last_page)}</span>
            <div class="flex items-center gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
    }

    /* ---------- ثبت پرداخت ---------- */
    async function submitPay(e) {
        e.preventDefault();
        els.formError.classList.add('hidden');
        document.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));

        const errors = {};
        if (!els.user.value) errors.user_id = ['انتخاب کارمند الزامی است.'];
        if (!els.period.value) errors.period = ['انتخاب دوره الزامی است.'];
        if (!els.amount.value || +els.amount.value <= 0) errors.amount = ['مبلغ باید بیش از صفر باشد.'];
        if (Object.keys(errors).length) {
            Object.entries(errors).forEach(([k, v]) => {
                const el = document.querySelector(`.err[data-for="${k}"]`);
                if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
            });
            els.formError.textContent = Object.values(errors)[0][0];
            els.formError.classList.remove('hidden');
            return;
        }

        els.pay.disabled = true;
        const original = els.pay.innerHTML;
        els.pay.innerHTML = '<span class="size-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span> در حال ثبت…';

        try {
            const res = await App.ajax(BASE, {
                method: 'POST',
                body: {
                    user_id: +els.user.value,
                    period: els.period.value,
                    type: els.type.value,
                    amount: els.amount.value,
                    description: els.desc.value.trim() || null,
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'پرداخت ثبت شد.', 'success');
                els.amount.value = '';
                els.desc.value = '';
                load(1);
                setTimeout(() => window.location.reload(), 1200);
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => {
                    const el = document.querySelector(`.err[data-for="${k}"]`);
                    if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
                });
                els.formError.textContent = data.message || Object.values(data.errors)[0][0];
                els.formError.classList.remove('hidden');
            } else {
                els.formError.textContent = data.message || 'خطا در ثبت پرداخت.';
                els.formError.classList.remove('hidden');
            }
        } catch {
            els.formError.textContent = 'ارتباط با سرور برقرار نشد.';
            els.formError.classList.remove('hidden');
        } finally {
            els.pay.disabled = false;
            els.pay.innerHTML = original;
        }
    }

    /* ---------- رویدادها ---------- */
    function bindEvents() {
        fillStaffSelects();
        els.user.addEventListener('change', showSalaryHint);
        els.form.addEventListener('submit', submitPay);

        els.fUser.addEventListener('change', () => load(1));
        els.fType.addEventListener('change', () => load(1));
        els.fPeriod.addEventListener('change', () => load(1));

        els.pagination.addEventListener('click', (e) => {
            const btn = e.target.closest('.pg-btn');
            if (btn && !btn.disabled) load(+btn.dataset.page);
        });
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[c]);
    }
    function fa(n) { return String(n ?? 0).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        bindEvents();
        load(1);
    }

    window.addEventListener('app:ready', boot, { once: true });
    setTimeout(boot, 2500);
})();
