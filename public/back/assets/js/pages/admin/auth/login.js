/**
 * کافی‌نت آنلاین — اسکریپت صفحه «back/assets/js/pages/admin/auth/login.js»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    const form = document.getElementById('login-form');
    const btn = document.getElementById('login-btn');
    const formError = document.getElementById('form-error');

    document.getElementById('toggle-pass')?.addEventListener('click', function () {
        const pass = document.getElementById('password');
        pass.type = pass.type === 'password' ? 'text' : 'password';
    });

    function showFieldError(name, msg) {
        const el = document.querySelector(`.err[data-for="${name}"]`);
        if (el) { el.textContent = msg; el.classList.remove('hidden'); }
        const input = form.querySelector(`[name="${name}"]`);
        if (input) input.classList.add('!border-rose-400');
    }

    function clearErrors() {
        form.querySelectorAll('.err').forEach(e => e.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(e => e.classList.remove('!border-rose-400'));
        formError.classList.add('hidden');
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearErrors();

        const email = form.email.value.trim();
        const password = form.password.value;

        if (!email) return showFieldError('email', 'ایمیل را وارد کنید.');
        if (!password) return showFieldError('password', 'رمز عبور را وارد کنید.');

        btn.disabled = true;
        const spinner = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>';
        btn.innerHTML = spinner + ' در حال ورود...';

        try {
            const res = await App.ajax('/admin/login', {
                method: 'POST',
                body: { email, password, remember: 1 },
            });

            const data = await res.json().catch(() => ({}));

            if (res.ok && data.redirect) {
                App.toast('خوش آمدید!', 'success');
                setTimeout(() => { window.location = data.redirect; }, 450);
                return;
            }

            if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => showFieldError(k, v[0]));
            } else {
                formError.textContent = data.message || 'خطا در ورود؛ دوباره تلاش کنید.';
                formError.classList.remove('hidden');
                form.animate(
                    [{ transform: 'translateX(0)' }, { transform: 'translateX(-7px)' }, { transform: 'translateX(7px)' }, { transform: 'translateX(0)' }],
                    { duration: 260, iterations: 2 }
                );
            }
        } catch (err) {
            formError.textContent = 'ارتباط با سرور برقرار نشد.';
            formError.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg> ورود به پنل';
        }
    });
})();
