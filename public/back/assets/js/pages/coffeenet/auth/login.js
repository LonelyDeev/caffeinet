/**
 * کافی‌نت آنلاین — اسکریپت صفحه «back/assets/js/pages/coffeenet/auth/login.js»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    /* نمایش/مخفی رمز */
    const passInput = document.getElementById('password');
    const toggle = document.getElementById('toggle-pass');
    toggle?.addEventListener('click', () => {
        passInput.type = passInput.type === 'password' ? 'text' : 'password';
    });

    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formError = document.getElementById('form-error');
        formError.classList.add('hidden');
        document.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));

        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        let ok = true;
        if (!email) { showErr('email', 'ایمیل الزامی است.'); ok = false; }
        if (!password) { showErr('password', 'رمز عبور الزامی است.'); ok = false; }
        if (!ok) return;

        const btn = document.getElementById('login-btn');
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="size-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> در حال بررسی...';

        try {
            const res = await App.ajax('/coffeenet/login', {
                method: 'POST',
                body: { email, password },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                btn.innerHTML = '✓ ' + (data.message || 'خوش آمدید!');
                setTimeout(() => { window.location = data.redirect || '/coffeenet'; }, 600);
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => showErr(k, v[0]));
                formError.textContent = Object.values(data.errors)[0][0];
                formError.classList.remove('hidden');
            } else {
                formError.textContent = data.message || 'خطا در ورود.';
                formError.classList.remove('hidden');
            }
        } catch {
            formError.textContent = 'ارتباط با سرور برقرار نشد.';
            formError.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            setTimeout(() => { btn.innerHTML = original; }, 400);
        }

        function showErr(name, msg) {
            const el = document.querySelector(`.err[data-for="${name}"]`);
            if (el) { el.textContent = msg; el.classList.remove('hidden'); }
        }
    });
})();
