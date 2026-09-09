/**
 * کافی‌نت آنلاین — اسکریپت صفحه «تنظیمات»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    const PAGE = App.pageData();
    const BASE = PAGE.base;
    const CITY_ID = PAGE.city_id;

    const els = {
        infoForm: document.getElementById('info-form'),
        infoError: document.getElementById('info-error'),
        btnInfo: document.getElementById('btn-info'),
        province: document.getElementById('i-province'),
        city: document.getElementById('i-city'),
        passForm: document.getElementById('pass-form'),
        passError: document.getElementById('pass-error'),
        btnPass: document.getElementById('btn-pass'),
    };

    /* ---------- سلکت آبشاری استان → شهرستان ---------- */
    async function loadCities(provinceId, selectedId) {
        els.city.disabled = true;
        els.city.innerHTML = '<option value="">در حال بارگذاری…</option>';
        try {
            const res = await App.ajax(`/coffeenet/geo/cities?province_id=${provinceId}`);
            const data = await res.json();
            els.city.innerHTML = '<option value="">— انتخاب شهرستان —</option>'
                + data.cities.map(c => `<option value="${c.id}" ${+c.id === +selectedId ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('');
            els.city.disabled = false;
        } catch {
            els.city.innerHTML = '<option value="">خطا در بارگذاری شهرها</option>';
        }
    }

    /* ---------- ذخیره اطلاعات ---------- */
    async function saveInfo(e) {
        e.preventDefault();
        els.infoError.classList.add('hidden');
        document.querySelectorAll('#info-form .err').forEach(el => el.classList.add('hidden'));

        const name = document.getElementById('i-name').value.trim();
        if (!name) {
            showErr('name', 'نام کافی‌نت الزامی است.');
            return;
        }

        els.btnInfo.disabled = true;
        const original = els.btnInfo.innerHTML;
        els.btnInfo.innerHTML = '<span class="size-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span> در حال ذخیره…';

        try {
            const res = await App.ajax(BASE, {
                method: 'PUT',
                body: {
                    name,
                    phone: document.getElementById('i-phone').value.trim() || null,
                    province_id: els.province.value ? +els.province.value : null,
                    city_id: els.city.value ? +els.city.value : null,
                    address: document.getElementById('i-address').value.trim() || null,
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'اطلاعات ذخیره شد.', 'success');
                setTimeout(() => window.location.reload(), 900);
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => showErr(k, v[0]));
                els.infoError.textContent = data.message || Object.values(data.errors)[0][0];
                els.infoError.classList.remove('hidden');
            } else {
                els.infoError.textContent = data.message || 'خطا در ذخیره‌سازی.';
                els.infoError.classList.remove('hidden');
            }
        } catch {
            els.infoError.textContent = 'ارتباط با سرور برقرار نشد.';
            els.infoError.classList.remove('hidden');
        } finally {
            els.btnInfo.disabled = false;
            els.btnInfo.innerHTML = original;
        }

        function showErr(k, msg) {
            const el = document.querySelector(`#info-form .err[data-for="${k}"]`);
            if (el) { el.textContent = msg; el.classList.remove('hidden'); }
        }
    }

    /* ---------- تغییر رمز ---------- */
    async function changePassword(e) {
        e.preventDefault();
        els.passError.classList.add('hidden');
        document.querySelectorAll('#pass-form .err').forEach(el => el.classList.add('hidden'));

        const current = document.getElementById('p-current').value;
        const pass = document.getElementById('p-new').value;
        const confirm = document.getElementById('p-confirm').value;

        const errors = {};
        if (!current) errors.current_password = ['رمز فعلی الزامی است.'];
        if (!pass || pass.length < 8) errors.password = ['رمز جدید حداقل ۸ کاراکتر باشد.'];
        if (pass !== confirm) errors.password_confirmation = ['تکرار رمز مطابقت ندارد.'];
        if (Object.keys(errors).length) {
            Object.entries(errors).forEach(([k, v]) => {
                const el = document.querySelector(`#pass-form .err[data-for="${k}"]`);
                if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
            });
            els.passError.textContent = Object.values(errors)[0][0];
            els.passError.classList.remove('hidden');
            return;
        }

        els.btnPass.disabled = true;
        const original = els.btnPass.innerHTML;
        els.btnPass.innerHTML = '<span class="size-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span> در حال تغییر…';

        try {
            const res = await App.ajax(`${BASE}/password`, {
                method: 'PUT',
                body: {
                    current_password: current,
                    password: pass,
                    password_confirmation: confirm,
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'رمز عبور تغییر کرد.', 'success');
                els.passForm.reset();
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => {
                    const el = document.querySelector(`#pass-form .err[data-for="${k}"]`);
                    if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
                });
                els.passError.textContent = data.message || Object.values(data.errors)[0][0];
                els.passError.classList.remove('hidden');
            } else {
                els.passError.textContent = data.message || 'خطا در تغییر رمز.';
                els.passError.classList.remove('hidden');
            }
        } catch {
            els.passError.textContent = 'ارتباط با سرور برقرار نشد.';
            els.passError.classList.remove('hidden');
        } finally {
            els.btnPass.disabled = false;
            els.btnPass.innerHTML = original;
        }
    }

    /* ---------- boot ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;

        if (els.province.value) loadCities(els.province.value, CITY_ID);
        els.province.addEventListener('change', () => {
            els.province.value ? loadCities(els.province.value, null) : (els.city.innerHTML = '<option value="">ابتدا استان…</option>', els.city.disabled = true);
        });

        els.infoForm.addEventListener('submit', saveInfo);
        els.passForm.addEventListener('submit', changePassword);
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[c]);
    }

    window.addEventListener('app:ready', boot, { once: true });
    setTimeout(boot, 2500);
})();
