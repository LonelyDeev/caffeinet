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

                if (res.ok) {
                    App.toast(data.message || 'ذخیره شد.', 'success');

                    // v26 — کلید VAPID وب‌پوش ممکن است تازه ساخته شده باشد
                    if (data.webpush_public) {
                        const vapidInput = document.getElementById('ns-vapid-public');
                        if (vapidInput) { vapidInput.value = data.webpush_public; }
                    }
                } else {
                    App.toast(data.message || 'خطا در ذخیره‌سازی.', 'error');
                }
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

    /* ---------- v25: تب اعلان‌ها — صدا + پوش دستگاه ---------- */

    /* ۱) تیک «صدای پیش‌فرض» ↔ آپلودر صدای سفارشی */
    const nsDefault = document.getElementById('ns-default');
    const nsCustomZone = document.getElementById('ns-custom-zone');
    const nsDefaultLabel = document.getElementById('ns-default-label');

    function syncSoundDefault() {
        if (!nsDefault || !nsCustomZone) { return; }

        const useDefault = nsDefault.checked;

        nsCustomZone.classList.toggle('hidden', useDefault);

        if (nsDefaultLabel) {
            nsDefaultLabel.textContent = useDefault
                ? 'صدای پیش‌فرض فعال است (ding کوتاه)'
                : 'صدای سفارشی';
        }
    }

    nsDefault?.addEventListener('change', syncSoundDefault);
    syncSoundDefault();

    /* آدرس صدای «فعلی» برای پخش تست */
    function currentSoundUrl() {
        const chip = document.getElementById('ns-current-chip');
        const useDefault = !nsDefault || nsDefault.checked;
        const customUrl = chip && chip.dataset.url ? chip.dataset.url : null;

        if (!useDefault && customUrl) { return customUrl; }

        return App.url('/assets/sounds/notify.mp3');
    }

    /* ۲) پخش تست صدا */
    let nsTestAudio = null;

    document.getElementById('ns-play-btn')?.addEventListener('click', () => {
        try {
            if (!nsTestAudio || nsTestAudio.src !== currentSoundUrl()) {
                nsTestAudio = new Audio(currentSoundUrl());
            }
            nsTestAudio.currentTime = 0;
            const p = nsTestAudio.play();
            if (p && typeof p.catch === 'function') { p.catch(() => {}); }
            App.toast('در حال پخش صدا… برای قطع، صفحه‌ای با صدا باز نکنید 🙂', 'info');
        } catch {
            App.toast('پخش صدا در این مرورگر ممکن نشد.', 'error');
        }
    });

    /* ۳) آپلود صدای سفارشی */
    const nsFile = document.getElementById('ns-file');
    const nsUploadBtn = document.getElementById('ns-upload-btn');

    nsUploadBtn?.addEventListener('click', () => nsFile?.click());

    nsFile?.addEventListener('change', async () => {
        if (!nsFile.files || !nsFile.files.length) { return; }

        const fd = new FormData();
        fd.append('sound', nsFile.files[0]);

        nsUploadBtn.disabled = true;

        try {
            const res = await App.ajax('/admin/settings/notification/sound', { method: 'POST', body: fd });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'صدا ذخیره شد.', 'success');

                const chip = document.getElementById('ns-current-chip');
                if (chip) {
                    chip.textContent = 'فایل فعلی: ' + (data.name || 'صدا');
                    chip.dataset.url = data.url || '';
                    chip.className = 'badge bg-emerald-50 text-emerald-700 border border-emerald-200';
                }

                const delBtn = document.getElementById('ns-delete-btn');
                if (delBtn) { delBtn.classList.remove('hidden'); }

                // آپلودر باز می‌ماند تا در صورت نیاز جایگزین شود
            } else {
                App.toast(data.message || 'آپلود صدا ناموفق بود.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            nsUploadBtn.disabled = false;
            nsFile.value = '';
        }
    });

    /* ۴) حذف صدای سفارشی */
    document.getElementById('ns-delete-btn')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;

        try {
            const res = await App.ajax('/admin/settings/notification/sound', { method: 'DELETE' });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'حذف شد.', 'success');

                const chip = document.getElementById('ns-current-chip');
                if (chip) {
                    chip.textContent = 'فایل سفارشی ندارید';
                    chip.dataset.url = '';
                    chip.className = 'badge bg-stone-100 text-stone-500 border border-stone-200';
                }
                btn.classList.add('hidden');
                if (nsDefault) { nsDefault.checked = true; }
                syncSoundDefault();
            } else {
                App.toast(data.message || 'حذف ناموفق بود.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            btn.disabled = false;
        }
    });

    /* ۵) پرووایدر پوش (خاموش / پیش‌فرض / پوشر / فایربیس) — v26 */
    const nsProviderInput = document.getElementById('ns-provider');
    const fbZone = document.getElementById('ns-firebase-zone');
    const webpushZone = document.getElementById('ns-webpush-zone');
    const pusherZone = document.getElementById('ns-pusher-zone');
    const credZone = document.getElementById('ns-credentials-zone');
    const offlineRow = document.getElementById('ns-offline-row');
    const offlineSecondsRow = document.getElementById('ns-offline-seconds-row');
    const offlineSwitch = document.getElementById('ns-offline-enabled');

    /* v29 — آستانهٔ آفلاین: سوییچ روشن → ورودی ثانیه نمایش داده شود */
    function syncOfflineRows(providerVal) {
        const providerOn = ['default', 'pusher', 'firebase'].includes(providerVal);
        const thresholdOn = !!(offlineSwitch && offlineSwitch.checked);

        if (offlineRow) { offlineRow.classList.toggle('hidden', !providerOn); }
        if (offlineSecondsRow) { offlineSecondsRow.classList.toggle('hidden', !providerOn || !thresholdOn); }
    }

    function syncPushProvider(value) {
        const v = value || 'off';
        const providers = ['off', 'default', 'pusher', 'firebase'];

        if (nsProviderInput) { nsProviderInput.value = v; }

        document.querySelectorAll('input[name="ns-push-provider"]').forEach(radio => {
            const card = radio.closest('label');
            if (!card) { return; }

            if (radio.value === v) {
                card.className = 'flex items-center gap-2.5 cursor-pointer select-none px-4 py-3 rounded-xl border '
                    + (v === 'off' ? 'border-stone-400 bg-stone-50' : 'border-amber-500 bg-amber-50');
            } else {
                card.className = 'flex items-center gap-2.5 cursor-pointer select-none px-4 py-3 rounded-xl border border-stone-200';
            }
        });

        if (webpushZone) { webpushZone.classList.toggle('hidden', v !== 'default'); }
        if (pusherZone) { pusherZone.classList.toggle('hidden', v !== 'pusher'); }
        if (fbZone) { fbZone.classList.toggle('hidden', v !== 'firebase'); }
        if (credZone) { credZone.classList.toggle('hidden', v !== 'firebase'); }
        syncOfflineRows(v);
    }

    document.querySelectorAll('input[name="ns-push-provider"]').forEach(radio => {
        radio.addEventListener('change', () => {
            if (radio.checked) { syncPushProvider(radio.value); }
        });
    });
    syncPushProvider(nsProviderInput?.value);

    /* ۵-الف) v29 — توضیح زندهٔ آستانهٔ آفلاین (سوییچ + ثانیه) */
    const nsOfflineDesc = document.getElementById('ns-offline-desc');
    const nsOfflineSec = document.getElementById('fb-offline-sec');

    function syncOfflineDesc() {
        if (!nsOfflineDesc) { return; }

        const on = !!(offlineSwitch && offlineSwitch.checked);
        const sec = Math.max(1, parseInt(nsOfflineSec && nsOfflineSec.value, 10) || 180);

        nsOfflineDesc.innerHTML = on
            ? 'کاربرِ بدونِ درخواستِ بیشتر از <b>' + sec.toLocaleString('fa-IR') + '</b> ثانیه «آفلاین» است؛ پوش دستگاه و پیامک آفلاین برای او ارسال می‌شود.'
            : '<b>لحظه‌ای:</b> بلافاصله پس از آخرین درخواست، کاربر آفلاین فرض می‌شود — پوش/پیامک رویدادی حتی با باز بودن پنل ارسال می‌شود.';
    }

    offlineSwitch?.addEventListener('change', () => {
        syncOfflineRows(nsProviderInput ? nsProviderInput.value : 'off');
        syncOfflineDesc();
    });
    nsOfflineSec?.addEventListener('input', syncOfflineDesc);

    /* ۵-الف) کپی کلید عمومی VAPID */
    document.getElementById('ns-copy-vapid')?.addEventListener('click', async (e) => {
        const input = document.getElementById('ns-vapid-public');
        if (!input || !input.value.trim()) {
            App.toast('کلیدی برای کپی وجود ندارد؛ ابتدا «پیش‌فرض» را ذخیره کنید.', 'info');
            return;
        }

        try {
            await navigator.clipboard.writeText(input.value.trim());
            App.toast('کلید عمومی VAPID کپی شد.', 'success');
        } catch {
            input.select();
            document.execCommand('copy');
            App.toast('کلید عمومی VAPID کپی شد.', 'success');
        }
    });

    /* ۵-ب) بازتولید کلیدهای VAPID وب‌پوش داخلی */
    document.getElementById('ns-regen-vapid')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;

        if (!confirm('کلیدهای وب‌پوش از نو ساخته شوند؟ دستگاه‌هایی که قبلاً نوتیف دستگاه را فعال کرده بودند باید دوباره فعالش کنند.')) {
            return;
        }

        btn.disabled = true;

        try {
            const res = await App.ajax('/admin/settings/notification/webpush-keys', { method: 'POST' });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'کلیدهای جدید ساخته شد.', 'success');
                const input = document.getElementById('ns-vapid-public');
                if (input && data.public_key) { input.value = data.public_key; }
            } else {
                App.toast(data.message || 'بازتولید ناموفق بود.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            btn.disabled = false;
        }
    });

    /* ۶) آپلود Service Account فایربیس */
    const fbCredFile = document.getElementById('fb-cred-file');
    const fbCredUploadBtn = document.getElementById('fb-cred-upload-btn');

    fbCredUploadBtn?.addEventListener('click', () => fbCredFile?.click());

    fbCredFile?.addEventListener('change', async () => {
        if (!fbCredFile.files || !fbCredFile.files.length) { return; }

        const fd = new FormData();
        fd.append('credentials', fbCredFile.files[0]);

        fbCredUploadBtn.disabled = true;

        try {
            const res = await App.ajax('/admin/settings/notification/push-credentials', { method: 'POST', body: fd });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'اعتبارنامه ذخیره شد.', 'success');

                const chip = document.getElementById('fb-cred-chip');
                if (chip) {
                    chip.textContent = 'ذخیره‌شده ✓';
                    chip.className = 'badge bg-emerald-50 text-emerald-700 border border-emerald-200';
                }
                document.getElementById('fb-cred-delete-btn')?.classList.remove('hidden');

                const projectInput = document.getElementById('fb-project');
                if (projectInput && !projectInput.value.trim() && data.project_id) {
                    projectInput.value = data.project_id;
                }
            } else {
                App.toast(data.message || 'آپلود ناموفق بود.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            fbCredUploadBtn.disabled = false;
            fbCredFile.value = '';
        }
    });

    /* ۷) حذف اعتبارنامه */
    document.getElementById('fb-cred-delete-btn')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;

        try {
            const res = await App.ajax('/admin/settings/notification/push-credentials', { method: 'DELETE' });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'حذف شد.', 'success');
                const chip = document.getElementById('fb-cred-chip');
                if (chip) {
                    chip.textContent = 'بارگذاری‌نشده';
                    chip.className = 'badge bg-stone-100 text-stone-500 border border-stone-200';
                }
                btn.classList.add('hidden');
            } else {
                App.toast(data.message || 'حذف ناموفق بود.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            btn.disabled = false;
        }
    });

    /* ۸) تست پوش فایربیس (ارسال واقعی از گوگل) */
    document.getElementById('btn-test-push')?.addEventListener('click', async () => {
        const btn = document.getElementById('btn-test-push');
        btn.disabled = true;

        try {
            const res = await App.ajax('/admin/settings/test-push', { method: 'POST' });
            const data = await res.json().catch(() => ({}));
            App.toast(data.message || (res.ok ? 'ارسال شد.' : 'ارسال ناموفق بود.'), res.ok ? 'success' : 'error');
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            btn.disabled = false;
        }
    });

    /* ۹) پیش‌نمایش محلی نوتیف دستگاه (بدون گوگل — از طریق SW) */
    document.getElementById('btn-preview-push')?.addEventListener('click', async () => {
        if (typeof Notification === 'undefined') {
            App.toast('این مرورگر نوتیف سیستم‌عامل را پشتیبانی نمی‌کند.', 'error');
            return;
        }

        if (Notification.permission === 'default') {
            try { await Notification.requestPermission(); } catch { /* noop */ }
        }

        if (!window.CNPush) {
            App.toast('پیش‌نمایش در دسترس نیست.', 'error');
            return;
        }

        try {
            const shown = await CNPush.simulate({
                notification: { title: 'پیش‌نمایش نوتیف دستگاه — کافی‌نت آنلاین', body: 'نوتیف‌های سیستم‌عامل به همین شکل روی گوشی/ویندوز شما نمایش داده می‌شوند.' },
                data: { url: '/admin/settings#notifications', tag: 'cn-preview' },
            });

            App.toast(shown
                ? 'نوتیف پیش‌نمایش ارسال شد — نوار اعلان سیستم‌عامل خود را ببینید. 🔔'
                : 'Service Worker هنوز آماده نیست؛ صفحه را یک‌بار تازه کنید و دوباره بزنید.', 'info');
        } catch {
            App.toast('اجرای پیش‌نمایش ممکن نشد.', 'error');
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
