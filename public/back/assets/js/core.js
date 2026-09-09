/**
 * کافی‌نت آنلاین — اسکریپت پایه پنل‌های مدیریت (back)
 * فایل مستقل — بدون Node / بدون بیلد (لینک مستقیم در Blade)
 *
 * App.url      : ساخت URL با حفظ پارامتر گیت‌وی پیش‌نمایش
 * App.ajax     : درخواست fetch با CSRF و هدرهای JSON
 * App.toast    : نمایش اعلان موفقیت/خطا
 * App.money    : قالب‌بندی مبلغ تومانی
 * App.pageData : داده‌های سرور صفحه (از data-payload عنصر #page-data)
 */

window.App = {
    /**
     * پارامتر گیت‌وی پیش‌نمایش (در پروداکشن null است)
     */
    gatewayPort: new URLSearchParams(window.location.search).get('XTransformPort'),

    /**
     * ساخت URL کامل با حفظ پارامتر گیت‌وی
     */
    url(path) {
        let url = String(path);
        if (this.gatewayPort && !url.includes('XTransformPort=')) {
            url += (url.includes('?') ? '&' : '?') + 'XTransformPort=' + encodeURIComponent(this.gatewayPort);
        }
        return url;
    },

    /**
     * درخواست AJAX (fetch) با CSRF و JSON
     *
     * @param {string} path مسیر نسبی مثل '/api/orders'
     * @param {object} options گزینه‌های fetch (method, body, ...)
     */
    async ajax(path, options = {}) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        const defaults = {
            method: options.method || 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
        };

        if (options.body && !(options.body instanceof FormData) && typeof options.body === 'object') {
            defaults.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        const response = await fetch(this.url(path), { ...defaults, ...options });

        if (response.redirected && response.url) {
            window.location = response.url;
        }

        if (!response.ok && response.status === 419) {
            this.toast('نشست شما منقضی شده است، صفحه نوسازی می‌شود…', 'error');
            setTimeout(() => window.location.reload(), 900);
        }

        return response;
    },

    /**
     * قالب‌بندی مبلغ به تومان با ارقام فارسی
     *
     * @param {number|string} value مبلغ (تومان)
     * @param {boolean} withUnit درج واحد «تومان»
     */
    money(value, withUnit = true) {
        const n = Number(value) || 0;
        const formatted = n.toLocaleString('fa-IR', { maximumFractionDigits: 0 });
        return withUnit ? formatted + ' تومان' : formatted;
    },

    /**
     * قالب‌بندی عدد (نه مبلغ) با ارقام فارسی — برای شمارنده‌ها و درصدها
     *
     * @param {number|string} value عدد
     */
    digits(value) {
        return (Number(value) || 0).toLocaleString('fa-IR', { maximumFractionDigits: 2 });
    },

    /**
     * داده‌های سروری صفحه — از ویژگی data-payload عنصر #page-data
     * (در Blade: <div id="page-data" hidden data-payload="{{ json_encode([...]) }}"></div>)
     *
     * @param {string|null} key کلید خاص؛ بدون کلید کل آبجکت
     * @returns {*} مقدار داده یا آبجکت کامل
     */
    pageData(key = null) {
        let data = {};
        try {
            const el = document.getElementById('page-data');
            data = JSON.parse(el ? (el.dataset.payload || '{}') : '{}') || {};
        } catch (e) {
            data = {};
        }
        return key === null ? data : (data[key] !== undefined ? data[key] : null);
    },

    /**
     * نمایش اعلان شناور — اگر PanelUI موجود باشد از توست مدرن آن استفاده می‌کند
     *
     * @param {string} message متن پیام
     * @param {'success'|'error'|'info'|'warn'} type نوع پیام
     */
    toast(message, type = 'success') {
        if (window.PanelUI && typeof window.PanelUI.toast === 'function') {
            window.PanelUI.toast(message, type);
            return;
        }

        const colors = {
            success: ['#2f9e63', '#e7f6ee'],
            error: ['#d24545', '#fdeaea'],
            info: ['#a8652e', '#fbf2e9'],
            warn: ['#d9910b', '#fdf5e3'],
        };
        const [fg, bg] = colors[type] ?? colors.info;

        const el = document.createElement('div');
        el.className = 'app-toast';
        el.dir = 'rtl';
        el.style.cssText = `
            position: fixed; top: 1.25rem; left: 1.25rem; z-index: 9999;
            display: flex; align-items: center; gap: .6rem;
            background: ${bg}; color: ${fg}; border-right: 4px solid ${fg};
            padding: .8rem 1.1rem; border-radius: .8rem; max-width: 22rem;
            font-size: .9rem; font-weight: 500; line-height: 1.6;
            box-shadow: 0 12px 34px -12px rgba(49, 25, 14, .45);
            animation: fade-up .4s cubic-bezier(.21,1.02,.73,1) both;
        `;
        el.innerHTML = `<span>${message}</span>`;
        document.body.appendChild(el);

        setTimeout(() => {
            el.style.transition = 'opacity .35s, transform .35s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            setTimeout(() => el.remove(), 400);
        }, 4200);
    },
};

window.addEventListener('DOMContentLoaded', () => {
    // افزودن پارامتر گیت‌وی به لینک‌های خام data-gateway
    document.querySelectorAll('a[data-gateway]').forEach((a) => {
        a.setAttribute('href', window.App.url(a.getAttribute('href')));
    });
});

// اعلان آماده‌سازی (این فایل به‌صورت classic script پیش از اسکریپت‌های صفحه اجرا می‌شود)
window.dispatchEvent(new Event('app:ready'));
