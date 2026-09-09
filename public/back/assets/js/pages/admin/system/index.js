/**
 * کافی‌نت آنلاین — اسکریپت صفحه «وضعیت سیستم» (ادمین)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    'use strict';

    const retentionForm = document.getElementById('sy-retention-form');
    const cleanupBtn = document.getElementById('sy-cleanup-btn');
    const encryptBtn = document.getElementById('sy-encrypt-btn');
    const reportBox = document.querySelector('[data-cleanup-report]');

    function digits(n) {
        return String(n === null || n === undefined ? 0 : n)
            .replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
    }

    function busy(btn, on, label) {
        if (!btn) return;
        if (on) {
            btn.dataset.label = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="sy-spin"></span>' + (label || 'در حال اجرا…');
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.label || btn.innerHTML;
        }
    }

    /* ---------- فرم نگهداشت ---------- */

    if (retentionForm) {
        retentionForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submit = retentionForm.querySelector('button[type="submit"]');
            busy(submit, true, 'ذخیره…');

            try {
                const res = await App.ajax(retentionForm.dataset.url, {
                    method: 'POST',
                    body: new FormData(retentionForm),
                });

                const data = await res.json();

                if (!res.ok) {
                    const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                    App.toast(first || data.message || 'خطا در ذخیره', 'error');
                    return;
                }

                App.toast(data.message || 'ذخیره شد', 'success');
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            } finally {
                busy(submit, false);
            }
        });
    }

    /* ---------- اجرای پاکسازی ---------- */

    function renderReport(report) {
        if (!reportBox || !report) return;

        const r = report.removed || {};
        const ret = report.retention || {};

        reportBox.innerHTML = `
            <div class="sy-report">
                <div class="sy-report-line"><span>کد OTP منقضی حذف‌شده</span><b>${digits(r.otp_codes || 0)}</b></div>
                <div class="sy-report-line"><span>اعلان خوانده‌شدهٔ قدیمی</span><b>${digits(r.notifications_read || 0)}</b></div>
                <div class="sy-report-line"><span>اعلان خوانده‌نشدهٔ قدیمی</span><b>${digits(r.notifications_unread || 0)}</b></div>
                <div class="sy-report-line"><span>لاگ پیامک قدیمی</span><b>${digits(r.sms_logs || 0)}</b></div>
                <div class="sy-report-line"><span>لاگ فعالیت قدیمی</span><b>${digits(r.audit_logs || 0)}</b></div>
                <div class="sy-report-line"><span>بایگانی لاگ لاراول</span><b>${(r.log_archived || 0) ? 'بایگانی شد' : 'لازم نشد'}</b></div>
                <div class="sy-report-meta">
                    آخرین اجرا: همین حالا —
                    نگهداشت: اعلان خوانده‌شدهٔ ${digits(ret.notifications_read || '-')} روز /
                    پیامک ${digits(ret.sms_logs || '-')} روز /
                    فعالیت ${digits(ret.audit_logs || '-')} روز
                </div>
            </div>`;
    }

    if (cleanupBtn) {
        cleanupBtn.addEventListener('click', () => {
            window.PanelUI.confirm({
                title: 'اجرای پاکسازی الان؟',
                desc: 'داده‌های موقت و لاگ‌های قدیمی‌تر از نگهداشت حذف می‌شوند. این عمل قابل بازگشت نیست.',
                okText: 'اجرا کن',
                icon: 'question',
            }, async () => {
                busy(cleanupBtn, true, 'در حال پاکسازی…');

                try {
                    const res = await App.ajax(cleanupBtn.dataset.url, { method: 'POST' });
                    const data = await res.json();

                    if (!res.ok) {
                        App.toast(data.message || 'خطا در اجرای پاکسازی', 'error');
                        return;
                    }

                    App.toast(data.message || 'پاکسازی اجرا شد', 'success');
                    renderReport(data.report);
                } catch {
                    App.toast('ارتباط با سرور برقرار نشد.', 'error');
                } finally {
                    busy(cleanupBtn, false);
                }
            });
        });
    }

    /* ---------- رمزنگاری فایل‌های باقی‌مانده ---------- */

    function updateEncStats(files) {
        if (!files) return;

        const fill = document.querySelector('[data-enc-fill]');
        const pct = document.querySelector('[data-enc-pct]');
        const state = document.querySelector('[data-enc-state]');
        const check = state ? state.closest('.sy-check') : null;

        const total = files.encrypted + files.plain;
        const value = total > 0 ? Math.round(files.encrypted * 100 / total) : 100;

        if (fill) fill.style.width = value + '%';
        if (pct) pct.textContent = digits(value) + '٪';

        if (state) {
            state.textContent = files.plain === 0
                ? 'همهٔ فایل‌ها رمزنگاری‌شده‌اند'
                : digits(files.plain) + ' فایل هنوز خام است';
        }
        if (check) {
            check.dataset.ok = files.plain === 0 ? '1' : '0';
        }
        if (encryptBtn && files.plain === 0) {
            encryptBtn.remove();
        }
    }

    if (encryptBtn) {
        encryptBtn.addEventListener('click', async () => {
            busy(encryptBtn, true, 'در حال رمزنگاری…');

            try {
                const res = await App.ajax(encryptBtn.dataset.url, { method: 'POST' });
                const data = await res.json();

                if (!res.ok) {
                    App.toast(data.message || 'خطا در رمزنگاری', 'error');
                    return;
                }

                App.toast(data.message || 'انجام شد', 'success');
                updateEncStats(data.files);
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            } finally {
                busy(encryptBtn, false);
            }
        });
    }
})();
