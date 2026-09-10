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

    /* ---------- پیامک: کارت‌های پرووایدر + کارت تنظیمات فعال (v13) ----------
     * هر پرووایدری که انتخاب شود، فقط کارت تنظیمات همان پرووایدر زیرش باز می‌شود. */
    const SMS_LABELS = {
        log: 'لاگ (محیط توسعه)',
        kavenegar: 'کاوه‌نگار',
        fraasms: 'فراز اس‌ام‌اس',
        ippanel: 'آی‌پی‌پنل',
        melipayamak: 'ملی‌پیامک',
        idehpardazan: 'ایده‌پردازان',
    };

    const smsProviderInput = document.getElementById('s-provider');
    const smsCards = document.querySelectorAll('#sec-sms .st-prov-card[data-prov]');
    const smsPanels = document.querySelectorAll('#sec-sms .st-gw[data-gw]');
    const smsBadge = document.getElementById('sms-provider-badge');
    const smsNavHint = document.querySelector('.st-nav-item[data-section="sms"] .st-nav-hint');

    function syncSmsProvider(value) {
        const v = value || 'log';

        if (smsProviderInput) smsProviderInput.value = v;
        smsCards.forEach(c => c.classList.toggle('is-selected', c.dataset.prov === v));
        smsPanels.forEach(p => p.classList.toggle('is-open', p.dataset.gw === v));

        const label = SMS_LABELS[v] ?? v;
        if (smsBadge) {
            smsBadge.textContent = label;
            smsBadge.className = 'badge ' + (v === 'log'
                ? 'bg-stone-100 text-stone-500 border border-stone-200'
                : 'bg-emerald-50 text-emerald-700 border border-emerald-200');
        }
        if (smsNavHint) smsNavHint.textContent = label;
    }

    smsCards.forEach(card => {
        const radio = card.querySelector('input[type="radio"]');
        radio?.addEventListener('change', () => {
            if (radio.checked) syncSmsProvider(radio.value);
        });
    });
    syncSmsProvider(smsProviderInput?.value);

    /* ---------- درگاه پرداخت: کارت‌های درایور + کارت پذیرندگی فعال (v13) ----------
     * هر درگاهی که فعال شود، فقط تنظیمات همان درگاه زیرش باز می‌شود. */
    const PAY_LABELS = {
        local: 'درگاه تست (local)',
        zarinpal: 'زرین‌پال',
        zibal: 'زیبال',
        behpardakht: 'بانک ملت',
        sep: 'بانک ملی',
        sepehr: 'درگاه سپهر',
    };

    const payDriverInput = document.getElementById('p-driver');
    const payCards = document.querySelectorAll('#sec-payment .st-prov-card[data-driver]');
    const payPanels = document.querySelectorAll('#sec-payment .st-gw[data-gw]');
    const payBadge = document.getElementById('pay-driver-badge');
    const payNavHint = document.querySelector('.st-nav-item[data-section="payment"] .st-nav-hint');

    function syncPayDriver(value) {
        const v = value || 'local';

        if (payDriverInput) payDriverInput.value = v;
        payCards.forEach(c => c.classList.toggle('is-selected', c.dataset.driver === v));
        payPanels.forEach(p => p.classList.toggle('is-open', p.dataset.gw === v));

        const label = PAY_LABELS[v] ?? v;
        if (payBadge) {
            payBadge.textContent = label;
            payBadge.className = 'badge ' + (v === 'local'
                ? 'bg-stone-100 text-stone-500 border border-stone-200'
                : 'bg-emerald-50 text-emerald-700 border border-emerald-200');
        }
        if (payNavHint) payNavHint.textContent = label;
    }

    payCards.forEach(card => {
        const radio = card.querySelector('input[type="radio"]');
        radio?.addEventListener('change', () => {
            if (radio.checked) syncPayDriver(radio.value);
        });
    });
    syncPayDriver(payDriverInput?.value);

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

    /* ---------- ساعت کاری (فاز ۱۵) — چیپ‌های روز هفته ----------
     * انتخاب چیپ‌ها به hidden input با data-key="workhours.days" sync می‌شود
     * تا همان سازوکار عمومی ذخیرهٔ فرم، مقدار را ارسال کند. */
    const whDaysInput = document.getElementById('wh-days');
    const whChips = document.querySelectorAll('.wh-day-chip');

    function syncWhDays() {
        if (!whDaysInput) return;
        const days = [...document.querySelectorAll('.wh-day-chip.is-on')]
            .map(c => c.dataset.day);
        whDaysInput.value = days.join(',');
    }

    whChips.forEach(chip => {
        chip.addEventListener('click', () => {
            // جلوگیری از خالی شدن کامل لیست روزها
            if (chip.classList.contains('is-on') && document.querySelectorAll('.wh-day-chip.is-on').length === 1) {
                App.toast('حداقل یک روز کاری باید انتخاب باشد.', 'warn');
                return;
            }
            chip.classList.toggle('is-on');
            chip.setAttribute('aria-pressed', chip.classList.contains('is-on') ? 'true' : 'false');
            syncWhDays();
        });
    });
    syncWhDays();

    /* همگام‌سازی نشانگر ناوبری پس از ذخیره */
    const whForm = document.getElementById('sec-workhours');
    whForm?.addEventListener('submit', () => {
        setTimeout(() => {
            const hint = document.querySelector('[data-wh-hint]');
            if (hint) hint.textContent = document.getElementById('wh-enabled')?.checked ? 'فعال' : 'خاموش';
        }, 900);
    }, { once: false });
