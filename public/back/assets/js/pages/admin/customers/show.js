/**
 * کافی‌نت آنلاین — اسکریپت صفحه «پروفایل کامل مشتری» (پنل مدیریت کل — درخواست بازخوردی)
 * نمودار روند سفارش + مودال ویرایش + مسدودسازی/رفع مسدودی + تنظیم دستی کیف پول
 */
const PAGE = App.pageData();

(function () {
    const customerId = Number(PAGE.id) || 0;

    /* ================== نمودار روند سفارش ================== */
    const canvas = document.getElementById('cs-trend-chart');
    let loading = false;

    const rangeLabel = document.getElementById('cs-range-label');
    const sumOrders = document.getElementById('cs-sum-orders');
    const sumDone = document.getElementById('cs-sum-done');
    const sumVolume = document.getElementById('cs-sum-volume');
    const customBox = document.getElementById('cs-custom-range');
    const fromInput = document.getElementById('cs-from');
    const toInput = document.getElementById('cs-to');

    function setBusy(on) {
        loading = on;
        canvas.closest('.cs-chart-wrap')?.classList.toggle('is-loading', on);
    }

    async function load(preset, from, to) {
        if (loading) return;
        setBusy(true);

        let url = `/admin/customers/${customerId}/trend?preset=${encodeURIComponent(preset)}`;
        if (from) url += `&from=${encodeURIComponent(from)}`;
        if (to) url += `&to=${encodeURIComponent(to)}`;

        try {
            const res = await App.ajax(url);
            const data = await res.json();
            render(data);
        } catch {
            App.toast('بارگذاری نمودار سفارش‌ها ناموفق بود.', 'error');
        } finally {
            setBusy(false);
        }
    }

    function render(data) {
        const labels = data.series.map(s => s.label);
        const orders = data.series.map(s => s.orders);
        const done = data.series.map(s => s.done);
        const volume = data.series.map(s => s.volume);

        if (rangeLabel) rangeLabel.textContent = data.label || '—';
        if (sumOrders) sumOrders.textContent = App.digits(orders.reduce((a, b) => a + b, 0));
        if (sumDone) sumDone.textContent = App.digits(done.reduce((a, b) => a + b, 0));
        if (sumVolume) sumVolume.textContent = App.money(volume.reduce((a, b) => a + b, 0));

        if (window.PanelCharts) {
            PanelCharts.line(canvas, {
                labels,
                fill: true,
                yMoney: false,
                datasets: [
                    { key: 'teal', label: 'سفارش ثبت‌شده', data: orders, money: false },
                    { key: 'amber', label: 'تحویل‌شده', data: done, money: false },
                ],
            });
        }
    }

    document.querySelectorAll('.cs-range-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.cs-range-btn').forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');

            const p = btn.dataset.range;
            customBox.classList.toggle('hidden', p !== 'custom');

            if (p === 'custom') {
                if (!fromInput.value) setPickerGregorian(fromInput, isoDaysAgo(30));
                if (!toInput.value) setPickerGregorian(toInput, isoDaysAgo(0));
                return; // منتظر «اعمال»
            }

            load(p);
        });
    });

    document.getElementById('cs-apply')?.addEventListener('click', () => {
        const from = document.getElementById('cs-from-g')?.value || fromInput.value;
        const to = document.getElementById('cs-to-g')?.value || document.getElementById('cs-from-g')?.value || from;

        if (!from) {
            App.toast('تاریخ شروع را انتخاب کنید.', 'error');
            return;
        }
        if (to < from) {
            App.toast('تاریخ پایان نباید قبل از شروع باشد.', 'error');
            return;
        }

        load('custom', from, to);
    });

    function setPickerGregorian(input, isoDate) {
        if (window.CNJdp) { window.CNJdp.setGregorian(input, isoDate); }
        else { input.value = isoDate; }
    }

    function isoDaysAgo(days) {
        const d = new Date();
        d.setDate(d.getDate() - days);
        return d.toISOString().slice(0, 10);
    }

    /* بار اول: روزانه */
    load('daily');

    /* ================== ناوبری ردیف سفارش‌ها (کلیک → صفحهٔ سفارش) ================== */
    document.querySelectorAll('tr.ops-row[data-href]').forEach(row => {
        row.addEventListener('click', e => {
            if (e.target.closest('a') || e.target.closest('button')) return;
            window.location.assign(row.dataset.href);
        });
    });

    /* ================== مودال ویرایش ================== */
    const editModal = document.getElementById('edit-modal');
    const editForm = document.getElementById('edit-form');
    const editSave = document.getElementById('edit-save');
    const editError = document.getElementById('edit-error');
    const editSubtitle = document.getElementById('edit-subtitle');
    let citiesCache = {};

    function toEnDigits(v) {
        return String(v || '').replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
            .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
    }

    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.style.overflow = '';
    }

    ['edit-modal', 'ban-modal', 'wallet-modal'].forEach(id => {
        document.getElementById(id)?.querySelectorAll('[data-close-modal]').forEach(el => {
            el.addEventListener('click', () => closeModal(id));
        });
    });

    async function loadCities(provinceId, selectedId) {
        const select = document.getElementById('f-city');
        select.innerHTML = '<option value="">در حال بارگذاری…</option>';

        if (!provinceId) {
            select.innerHTML = '<option value="">ابتدا استان را انتخاب کنید…</option>';
            return;
        }

        if (!citiesCache[provinceId]) {
            try {
                const res = await App.ajax((PAGE.geoCitiesUrl || '/admin/geo/cities') + '?province_id=' + provinceId);
                const data = await res.json();
                citiesCache[provinceId] = data.cities || [];
            } catch {
                citiesCache[provinceId] = [];
            }
        }

        select.innerHTML = '<option value="">انتخاب شهر…</option>' + citiesCache[provinceId].map(c =>
            `<option value="${c.id}">${c.name}</option>`).join('');
        if (selectedId) select.value = String(selectedId);
    }

    document.getElementById('btn-edit')?.addEventListener('click', () => {
        const c = PAGE.customer || {};

        editSubtitle.textContent = (PAGE.name || '') + ' — ' + (c.mobile || '');
        document.getElementById('f-name').value = c.name || '';
        document.getElementById('f-family').value = c.family || '';
        document.getElementById('f-mobile').value = c.mobile || '';
        document.getElementById('f-email').value = c.email || '';
        document.getElementById('f-gender').value = c.gender || '';
        document.getElementById('f-birthdate').value = c.birthdate_fa || '';
        document.getElementById('f-province').value = c.province_id ? String(c.province_id) : '';
        document.getElementById('f-password').value = '';
        document.getElementById('f-profile-completed').checked = !!c.profile_completed;
        document.getElementById('f-is-active').checked = !!c.is_active;

        loadCities(c.province_id, c.city_id);

        editForm.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        editError.classList.add('hidden');

        openModal('edit-modal');
    });

    document.getElementById('f-province')?.addEventListener('change', function () {
        loadCities(this.value ? parseInt(this.value, 10) : 0, null);
    });

    editForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        editForm.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        editError.classList.add('hidden');

        const payload = {
            name: document.getElementById('f-name').value.trim(),
            family: document.getElementById('f-family').value.trim() || null,
            mobile: document.getElementById('f-mobile').value.trim(),
            email: document.getElementById('f-email').value.trim() || null,
            gender: document.getElementById('f-gender').value || null,
            birthdate: toEnDigits(document.getElementById('f-birthdate').value.trim()),
            province_id: parseInt(document.getElementById('f-province').value, 10) || null,
            city_id: parseInt(document.getElementById('f-city').value, 10) || null,
            profile_completed: document.getElementById('f-profile-completed').checked,
            is_active: document.getElementById('f-is-active').checked,
            password: document.getElementById('f-password').value || null,
        };

        editSave.disabled = true;
        editSave.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

        try {
            const res = await App.ajax('/admin/customers/' + customerId, {
                method: 'PUT',
                body: payload,
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'ذخیره شد.', 'success');
                setTimeout(() => window.location.reload(), 700);
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => {
                    const el = editForm.querySelector(`.err[data-for="${k}"]`);
                    if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
                });
                App.toast(data.message || Object.values(data.errors)[0][0], 'error');
            } else {
                editError.textContent = data.message || 'خطا در ذخیره‌سازی.';
                editError.classList.remove('hidden');
            }
        } catch {
            editError.textContent = 'ارتباط با سرور برقرار نشد.';
            editError.classList.remove('hidden');
        } finally {
            editSave.disabled = false;
            editSave.innerHTML = 'ذخیرهٔ تغییرات';
        }
    });

    /* ================== مسدودسازی ================== */
    const banForm = document.getElementById('ban-form');
    const banSave = document.getElementById('ban-save');
    const banError = document.getElementById('ban-error');

    document.getElementById('btn-ban')?.addEventListener('click', () => {
        document.getElementById('ban-subtitle').textContent = (PAGE.name || '') + ' — ' + (PAGE.mobile || '');
        document.getElementById('ban-reason').value = '';
        banError.classList.add('hidden');
        openModal('ban-modal');
    });

    banForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const reason = document.getElementById('ban-reason').value.trim();
        if (reason.length < 3) {
            banError.classList.remove('hidden');
            return;
        }
        banError.classList.add('hidden');

        banSave.disabled = true;
        banSave.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> در حال مسدودسازی...';

        try {
            const res = await App.ajax('/admin/customers/' + customerId + '/ban', {
                method: 'PATCH',
                body: { reason },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'مسدود شد.', 'success');
                setTimeout(() => window.location.reload(), 700);
            } else {
                App.toast(data.message || 'خطا در مسدودسازی.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            banSave.disabled = false;
            banSave.innerHTML = 'مسدودسازی حساب';
        }
    });

    /* ================== رفع مسدودی ================== */
    document.getElementById('btn-unban')?.addEventListener('click', async () => {
        if (!confirm('رفع مسدودی این مشتری؟ دوباره می‌تواند وارد اپ شود.')) return;

        try {
            const res = await App.ajax('/admin/customers/' + customerId + '/unban', { method: 'PATCH' });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                App.toast(data.message || 'فعال شد.', 'success');
                setTimeout(() => window.location.reload(), 700);
            } else {
                App.toast(data.message || 'خطا در رفع مسدودی.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        }
    });

    /* ================== تنظیم دستی کیف پول ================== */
    const walletForm = document.getElementById('wallet-form');
    const walletSave = document.getElementById('wallet-save');
    const walletError = document.getElementById('wallet-error');

    document.getElementById('btn-wallet')?.addEventListener('click', () => {
        document.getElementById('w-amount').value = '';
        document.getElementById('w-desc').value = '';
        walletForm.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        walletError.classList.add('hidden');
        openModal('wallet-modal');
    });

    walletForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        walletForm.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        walletError.classList.add('hidden');

        const payload = {
            amount: parseFloat(document.getElementById('w-amount').value) || 0,
            description: document.getElementById('w-desc').value.trim(),
        };

        if (!payload.amount) {
            const el = walletForm.querySelector('.err[data-for="amount"]');
            if (el) { el.textContent = 'مبلغ غیرصفر لازم است.'; el.classList.remove('hidden'); }
            return;
        }
        if (payload.description.length < 3) {
            const el = walletForm.querySelector('.err[data-for="description"]');
            if (el) { el.textContent = 'حداقل ۳ حرف برای شرح لازم است.'; el.classList.remove('hidden'); }
            return;
        }

        walletSave.disabled = true;
        walletSave.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ثبت...';

        try {
            const res = await App.ajax('/admin/customers/' + customerId + '/wallet', {
                method: 'POST',
                body: payload,
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'تراکنش ثبت شد.', 'success');
                setTimeout(() => window.location.reload(), 700);
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => {
                    const el = walletForm.querySelector(`.err[data-for="${k}"]`);
                    if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
                });
                App.toast(data.message || Object.values(data.errors)[0][0], 'error');
            } else {
                walletError.textContent = data.message || 'خطا در ثبت تراکنش.';
                walletError.classList.remove('hidden');
            }
        } catch {
            walletError.textContent = 'ارتباط با سرور برقرار نشد.';
            walletError.classList.remove('hidden');
        } finally {
            walletSave.disabled = false;
            walletSave.innerHTML = 'ثبت تراکنش';
        }
    });
})();
