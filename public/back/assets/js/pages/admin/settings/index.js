/**
 * کافی‌نت آنلاین — اسکریپت صفحه «تنظیمات» (بازطراحی درخواست بازخوردی ۶-۵)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    /* ---------- ناوبری سکشن‌ها ---------- */
    const navItems = document.querySelectorAll('.st-nav-item[data-section]');
    const sections = document.querySelectorAll('.st-section');

    function activate(sectionId) {
        navItems.forEach(b => b.classList.toggle('is-active', b.dataset.section === sectionId));
        sections.forEach(s => s.classList.toggle('hidden', s.id !== 'sec-' + sectionId));
    }

    navItems.forEach(btn => {
        btn.addEventListener('click', () => activate(btn.dataset.section));
    });

    /* hash اولیه: #sms و مانند آن */
    if (location.hash) {
        const want = location.hash.replace('#', '');
        if (document.getElementById('sec-' + want)) activate(want);
    }

    /* ---------- هایلایت کارت پرووایدرِ انتخاب‌شده ---------- */
    const providerSelect = document.getElementById('s-provider');
    const kvnBox = document.getElementById('kvn-box');
    const fraaBox = document.getElementById('fraa-box');

    function syncProviderBoxes() {
        const p = providerSelect?.value || 'log';
        kvnBox?.classList.toggle('is-dimmed', p !== 'kavenegar');
        fraaBox?.classList.toggle('is-dimmed', p !== 'fraasms');
    }

    providerSelect?.addEventListener('change', syncProviderBoxes);
    syncProviderBoxes();

    /* ---------- درگاه پرداخت: هایلایت کارت درایور + جعبه‌های مرچنت ---------- */
    const payDriverInput = document.getElementById('p-driver');
    const zarinpalBox = document.getElementById('zarinpal-box');
    const zibalBox = document.getElementById('zibal-box');

    function syncPayBoxes() {
        const d = payDriverInput?.value || 'local';
        zarinpalBox?.classList.toggle('is-dimmed', d !== 'zarinpal');
        zibalBox?.classList.toggle('is-dimmed', d !== 'zibal');
    }

    document.querySelectorAll('input[name="pay-driver"]').forEach(radio => {
        radio.addEventListener('change', () => {
            if (radio.checked && payDriverInput) {
                payDriverInput.value = radio.value;
                syncPayBoxes();
            }
        });
    });
    syncPayBoxes();

    /* ---------- کارکنان: همگام‌سازی کارت سیاست تایید ---------- */
    const hiringInput = document.getElementById('s-hiring-mode');
    document.querySelectorAll('input[name="staff-hiring-mode"]').forEach(radio => {
        radio.addEventListener('change', () => {
            if (radio.checked && hiringInput) {
                hiringInput.value = radio.value;
            }
        });
    });
    if (hiringInput) { hiringInput.value = hiringInput.value || 'auto'; }

    /* ---------- ذخیره هر فرم (AJAX) ---------- */
    document.querySelectorAll('form[data-group]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const values = {};
            form.querySelectorAll('[data-key]').forEach(input => {
                if (input.type === 'checkbox') {
                    values[input.dataset.key] = input.checked ? '1' : '0';
                    return;
                }
                const v = input.value.trim();
                // فیلد رمز-like خالی → کلید ارسال نشود (موجود حفظ شود)
                if (input.hasAttribute('data-empty-skip') && v === '') return;
                values[input.dataset.key] = v;
            });

            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            const original = btn.innerHTML;
            btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

            try {
                const res = await App.ajax('/admin/settings', {
                    method: 'PUT',
                    body: { group: form.dataset.group, values },
                });
                const data = await res.json().catch(() => ({}));

                if (res.ok) App.toast(data.message || 'ذخیره شد.', 'success');
                else App.toast(data.message || 'خطا در ذخیره‌سازی.', 'error');
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    });

    /* ذخیره پاداش معرفی (اندپوینت اختصاصی) */
    const referralForm = document.getElementById('sec-referral');
    referralForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = referralForm.querySelector('button[type="submit"]');
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

        try {
            const isActive = document.getElementById('r-active').checked;
            const res = await App.ajax('/admin/settings/referral', {
                method: 'PUT',
                body: {
                    introduction_reward: parseFloat(document.getElementById('r-reward').value) || 0,
                    per_order_type: document.getElementById('r-per-type').value,
                    per_order_value: parseFloat(document.getElementById('r-per-value').value) || 0,
                    is_active: isActive,
                },
            });
            const data = await res.json().catch(() => ({}));
            App.toast(data.message || (res.ok ? 'ذخیره شد.' : 'خطا در ذخیره‌سازی.'), res.ok ? 'success' : 'error');

            /* به‌روزرسانی زندهٔ نشانگرها (بدون رفرش) */
            if (res.ok) {
                const dot = document.querySelector('.st-nav-item[data-section="referral"] .st-nav-dot');
                if (dot) {
                    dot.classList.toggle('st-nav-dot--on', isActive);
                    dot.title = isActive ? 'فعال' : 'غیرفعال';
                }
                const badge = document.getElementById('r-badge');
                if (badge) {
                    badge.textContent = isActive ? 'فعال' : 'غیرفعال';
                    badge.className = isActive
                        ? 'badge bg-emerald-50 text-emerald-700 border border-emerald-200'
                        : 'badge bg-stone-100 text-stone-500 border border-stone-200';
                }
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    /* ---------- تست اتصال پوشر (فاز ۱۳ — Realtime) ---------- */
    document.getElementById('btn-test-pusher')?.addEventListener('click', async () => {
        const btn = document.getElementById('btn-test-pusher');
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="size-4 border-2 border-stone-300 border-t-stone-600 rounded-full animate-spin inline-block align-middle me-1"></span> در حال اتصال...';

        try {
            const res = await App.ajax('/admin/settings/test-pusher', { method: 'POST' });
            const data = await res.json().catch(() => ({}));
            App.toast(data.message || (res.ok ? 'اتصال برقرار است.' : 'اتصال ناموفق بود.'), res.ok ? 'success' : 'error');
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    /* پیامک آزمایشی */
    const smsModal = document.getElementById('sms-modal');
    document.getElementById('btn-test-sms')?.addEventListener('click', () => {
        smsModal.classList.remove('hidden');
        smsModal.classList.add('flex');
    });
    smsModal.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', () => {
        smsModal.classList.add('hidden');
        smsModal.classList.remove('flex');
    }));

    document.getElementById('sms-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const errEl = smsModal.querySelector('.err[data-for="mobile"]');
        errEl.classList.add('hidden');

        const mobile = document.getElementById('t-mobile').value.trim();
        if (!/^09\d{9}$/.test(mobile)) {
            errEl.textContent = 'فرمت موبایل صحیح نیست.';
            errEl.classList.remove('hidden');
            return;
        }

        const btn = document.getElementById('sms-send');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ارسال...';

        try {
            const res = await App.ajax('/admin/settings/test-sms', { method: 'POST', body: { mobile } });
            const data = await res.json().catch(() => ({}));
            App.toast(data.message || (res.ok ? 'ارسال شد.' : 'خطا در ارسال.'), res.ok ? 'success' : 'error');
            if (res.ok) {
                smsModal.classList.add('hidden');
                smsModal.classList.remove('flex');
            }
        } finally {
            btn.disabled = false;
            btn.textContent = 'ارسال';
        }
    });
})();
