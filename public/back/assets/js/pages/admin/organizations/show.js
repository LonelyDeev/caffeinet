/**
 * کافی‌نت آنلاین — اسکریپت صفحه «جزئیات سازمان» (ادمین)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    const PAGE = App.pageData();
    const ORG_ID = Number(PAGE.id) || 0;

    /* ---------- تغییر وضعیت (تأیید / تعلیق / رد) — PATCH admin/organizations/{id}/status ---------- */
    const statusTexts = {
        approved: 'این سازمان تأیید شود؟ مدیر سازمان نیز فعال می‌شود.',
        suspended: 'این سازمان تعلیق شود؟ دسترسی مدیر سازمان هم مسدود می‌شود.',
        rejected: 'این سازمان رد شود؟',
    };

    function askStatus(status) {
        const doStatus = async () => {
            try {
                const res = await App.ajax('/admin/organizations/' + ORG_ID + '/status', {
                    method: 'PATCH',
                    body: { status },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    App.toast(data.message || 'وضعیت سازمان تغییر کرد.', 'success');
                    setTimeout(() => window.location.reload(), 950);
                } else {
                    App.toast(data.message || 'خطا در تغییر وضعیت.', 'error');
                }
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            }
        };

        if (window.PanelUI) {
            window.PanelUI.confirm(
                {
                    title: 'تغییر وضعیت سازمان',
                    desc: statusTexts[status] || 'آیا مطمئن هستید؟',
                    okText: 'بله، انجام بده',
                    danger: status === 'rejected',
                    icon: 'question',
                },
                doStatus
            );
        } else {
            doStatus();
        }
    }

    /* ---------- بارگذاری اولیه ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;

        document.querySelectorAll('.dt-action[data-status]').forEach((btn) => {
            btn.addEventListener('click', () => askStatus(btn.dataset.status));
        });
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
