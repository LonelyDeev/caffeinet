/**
 * کافی‌نت آنلاین — اسکریپت صفحه «back/assets/js/pages/org/auth/choose.js»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    document.querySelectorAll('.org-card').forEach(card => {
        card.addEventListener('click', async () => {
            card.disabled = true;
            try {
                const res = await App.ajax('/organization/select', {
                    method: 'POST',
                    body: { organization_id: +card.dataset.org },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    window.location = data.redirect || '/organization';
                } else {
                    card.disabled = false;
                    App.toast(data.message || 'خطا در انتخاب سازمان.', 'error');
                }
            } catch {
                card.disabled = false;
            }
        });
    });

    document.getElementById('logout-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await App.ajax('/organization/logout', { method: 'POST' });
            const data = await res.json().catch(() => ({}));
            window.location = data.redirect || '/organization/login';
        } catch {
            window.location = '/organization/login';
        }
    });
})();
