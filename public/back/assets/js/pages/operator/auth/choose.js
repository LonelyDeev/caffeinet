/**
 * کافی‌نت آنلاین — اسکریپت صفحه «back/assets/js/pages/operator/auth/choose.js»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    document.querySelectorAll('.net-card').forEach(card => {
        card.addEventListener('click', async () => {
            card.disabled = true;
            try {
                const res = await App.ajax('/operator/select', {
                    method: 'POST',
                    body: { coffeenet_id: +card.dataset.net },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    window.location = data.redirect || '/operator';
                } else {
                    card.disabled = false;
                    App.toast(data.message || 'خطا در انتخاب کافی‌نت.', 'error');
                }
            } catch {
                card.disabled = false;
            }
        });
    });

    document.getElementById('logout-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await App.ajax('/operator/logout', { method: 'POST' });
            const data = await res.json().catch(() => ({}));
            window.location = data.redirect || '/operator/login';
        } catch {
            window.location = '/operator/login';
        }
    });
})();
