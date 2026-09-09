/**
 * کافی‌نت آنلاین — اسکریپت صفحه «جزئیات کافی‌نت» (پنل مدیریت کل)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 *
 * عملیات وضعیت (تأیید / رفع تعلیق / تعلیق / رد) از همان اندپوینت
 * لیست کافی‌نت‌ها استفاده می‌کند: PATCH /admin/coffeenets/{id}/status
 */
/* داده‌های سرور (از #page-data) */
const PAGE = App.pageData();
window.__referralReward = Number(PAGE.reward) || 0;

(function () {
    const id = Number(PAGE.id) || 0;

    /* ---------- تغییر وضعیت ---------- */
    const statusTexts = {
        approved: 'این کافی‌نت تأیید و فعال شود؟',
        suspended: 'این کافی‌نت به‌طور موقت تعلیق شود؟',
        rejected: 'این کافی‌نت رد شود؟',
    };

    function askStatus(status) {
        const doStatus = async () => {
            try {
                const res = await App.ajax(`/admin/coffeenets/${id}/status`, {
                    method: 'PATCH',
                    body: { status },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    App.toast(data.message || 'وضعیت کافی‌نت تغییر کرد.', 'success');
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    App.toast(data.message || 'خطا', 'error');
                }
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            }
        };

        let desc = statusTexts[status] || 'آیا مطمئن هستید؟';

        if (status === 'approved') {
            // نمایش پیش‌آگهی پاداش معرفی (اگر با تأیید پرداخت می‌شود)
            const reward = window.__referralReward || 0;
            if (reward > 0) {
                desc += ' — 🏅 با تأیید، پاداش معرفی ' + App.money(reward) + ' به کیف پول سازمان معرف واریز می‌شود.';
            }
        }

        if (window.PanelUI) {
            window.PanelUI.confirm(
                {
                    title: 'تغییر وضعیت کافی‌نت',
                    desc,
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

    /* دکمه‌ها فقط وقتی رندر شده‌اند (Blade بر اساس وضعیت فعلی تصمیم می‌گیرد) */
    function bindAction(btnId, status) {
        const btn = document.getElementById(btnId);
        if (btn) btn.addEventListener('click', () => askStatus(status));
    }

    bindAction('act-approve', 'approved');
    bindAction('act-suspend', 'suspended');
    bindAction('act-reject', 'rejected');
})();

/* ================================================================== */
/*  روند کاری کافی‌نت (درخواست بازخوردی ۶-۲) — چارت بازه‌ای           */
/* ================================================================== */
(function () {
    const canvas = document.getElementById('cs-trend-chart');
    if (!canvas || !window.PanelCharts) return;

    const id = Number(PAGE.id) || 0;
    let preset = 'daily';
    let chart = null;
    let loading = false;

    const rangeLabel = document.getElementById('cs-range-label');
    const sumOrders = document.getElementById('cs-sum-orders');
    const sumDone = document.getElementById('cs-sum-done');
    const sumVolume = document.getElementById('cs-sum-volume');
    const customBox = document.getElementById('cs-custom-range');
    const fromInput = document.getElementById('cs-from');
    const toInput = document.getElementById('cs-to');

    function setBusy(on) {
        loading = on;
        canvas.closest('.cs-chart-wrap')?.classList.toggle('is-loading', on);
    }

    async function load(p, from, to) {
        if (loading) return;
        setBusy(true);

        let url = `/admin/coffeenets/${id}/trend?preset=${encodeURIComponent(p)}`;
        if (from) url += `&from=${encodeURIComponent(from)}`;
        if (to) url += `&to=${encodeURIComponent(to)}`;

        try {
            const res = await App.ajax(url);
            const data = await res.json();
            render(data);
        } catch {
            App.toast('بارگذاری روند کاری ناموفق بود.', 'error');
        } finally {
            setBusy(false);
        }
    }

    function render(data) {
        preset = data.preset || preset;

        const labels = data.series.map(s => s.label);
        const orders = data.series.map(s => s.orders);
        const done = data.series.map(s => s.done);
        const volume = data.series.map(s => s.volume);

        if (rangeLabel) rangeLabel.textContent = data.label || '—';
        if (sumOrders) sumOrders.textContent = App.digits(orders.reduce((a, b) => a + b, 0));
        if (sumDone) sumDone.textContent = App.digits(done.reduce((a, b) => a + b, 0));
        if (sumVolume) sumVolume.textContent = App.money(volume.reduce((a, b) => a + b, 0));

        // رندر مجدد امن — build خودش نمودار قبلیِ همین بوم را destroy می‌کند
        PanelCharts.line(canvas, {
            labels,
            fill: true,
            yMoney: false,
            datasets: [
                { key: 'amber', label: 'سفارش ثبت‌شده', data: orders, money: false },
                { key: 'teal', label: 'تحویل‌شده', data: done, money: false },
            ],
        });
    }

    /* انتخاب بازه */
    document.querySelectorAll('.cs-range-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.cs-range-btn').forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');

            const p = btn.dataset.range;
            customBox.classList.toggle('hidden', p !== 'custom');

            if (p === 'custom') {
                if (!fromInput.value) setPickerGregorian(fromInput, isoDaysAgo(30));
                if (!toInput.value) setPickerGregorian(toInput, isoDaysAgo(0));
                return; // منتظر «اعمال»
            }

            load(p);
        });
    });

    /* اعمال بازهٔ دلخواه */
    document.getElementById('cs-apply')?.addEventListener('click', () => {
        const from = document.getElementById('cs-from-g')?.value || fromInput.value;
        const to = document.getElementById('cs-to-g')?.value || document.getElementById('cs-from-g')?.value || from;

        if (!from) {
            App.toast('تاریخ شروع را انتخاب کنید.', 'error');
            return;
        }
        if (to < from) {
            App.toast('تاریخ پایان نباید قبل از شروع باشد.', 'error');
            return;
        }

        load('custom', from, to);
    });

    /* مقداردهی اینپوت دیت‌پیکر شمسی با تاریخ میلادی ISO */
    function setPickerGregorian(input, isoDate) {
        if (window.CNJdp) { window.CNJdp.setGregorian(input, isoDate); }
        else { input.value = isoDate; }
    }

    function isoDaysAgo(days) {
        const d = new Date();
        d.setDate(d.getDate() - days);
        return d.toISOString().slice(0, 10);
    }

    /* بار اول: روزانه */
    load('daily');
})();
