/* اپ مشتری — صفحه خدمت + فرم داینامیک سفارش */
/* فاز ۱۲ — آپلودر زیبای مدارک (دراپ‌زون + چیپ فایل) */
/* global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireAuth()) { return; }

    var serviceId = Number(window.location.pathname.split('/').pop()) || 0;
    var detail = null;

    /* ---------- آپلودر زیبا (فاز ۱۲) ---------- */

    var FUP_ICONS = {
        image: { tile: 't-image', icon: '<rect width="18" height="18" x="3" y="3" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a1.5 1.5 0 0 0-2.12 0L5 20"/>' },
        video: { tile: 't-video', icon: '<path d="m16 13 5.2-3.1a.6.6 0 0 1 .8.5v3.2a.6.6 0 0 1-.8.5L16 11"/><rect width="14" height="10" x="2" y="7" rx="2"/><path d="m6 11 2 2 4-4"/>' },
        audio: { tile: 't-audio', icon: '<path d="M12 2v11"/><path d="M8 6.5a6 6 0 0 0 0 11"/><path d="M16 6.5a6 6 0 0 1 0 11"/>' },
        file: { tile: 't-file', icon: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>' }
    };

    function fupDetectType(file) {
        if (file.type && file.type.indexOf('image/') === 0) { return 'image'; }
        if (file.type && file.type.indexOf('video/') === 0) { return 'video'; }
        if (file.type && file.type.indexOf('audio/') === 0) { return 'audio'; }
        return 'file';
    }

    function fupFa(n) { return CN.toFaDigits(String(n || 0)); }

    function fupExt(name) {
        var m = /\.([a-z0-9]+)$/i.exec(String(name || ''));
        return m ? m[1].toUpperCase() : '';
    }

    /**
     * ساخت آپلودر زیبا روی یک input[type=file] پنهان.
     * opts: { inputId, listId, zoneId(optional), multiple, maxKb, onInvalid }
     * خروجی: { files: () => File[], clear: () => void }
     */
    function createUploader(opts) {
        var input = document.getElementById(opts.inputId);
        var list = document.getElementById(opts.listId);
        var zone = document.getElementById(opts.zoneId || '');
        var root = input ? input.closest('.fup') : null;
        var files = [];
        var thumbEls = [];   // element بندانگشتی — هم‌اندازه با files
        var thumbUrls = [];  // URLهای تصویر برای revoke — هم‌اندازه با files

        if (!input || !list) { return { files: function () { return []; }, clear: function () {} }; }

        if (!zone && root) { zone = root.querySelector('.fup-zone'); }

        function addFiles(fileList) {
            var added = 0;
            Array.prototype.forEach.call(fileList || [], function (file) {
                if (!opts.multiple && files.length >= 1) {
                    CN.toast('برای این فیلد فقط یک فایل قابل انتخاب است.', 'error');
                    return;
                }
                if (opts.maxKb && file.size > opts.maxKb * 1024) {
                    CN.toast('حجم «' + (file.name || 'فایل') + '» بیش از حد مجاز است (حداکثر ' + fupFa(opts.maxKb / 1024) + ' مگابایت).', 'error');
                    return;
                }
                if (dup(file)) {
                    CN.toast('این فایل قبلاً اضافه شده است.', 'info');
                    return;
                }
                files.push(file);
                var t = makeThumb(file);
                thumbEls.push(t.el);
                thumbUrls.push(t.url || null);
                added++;
            });
            if (added) { render(); }
        }

        function dup(file) {
            return files.some(function (f) { return f.name === file.name && f.size === file.size; });
        }

        function makeThumb(file) {
            var type = fupDetectType(file);
            var url = null;
            var el = document.createElement('span');
            el.className = 'fc-thumb ' + FUP_ICONS[type].tile;
            if (type === 'image') {
                try {
                    var img = document.createElement('img');
                    img.alt = '';
                    url = URL.createObjectURL(file);
                    img.src = url;
                    el.appendChild(img);
                } catch (e) { /* noop */ }
            }
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke', 'currentColor');
            svg.setAttribute('stroke-width', '2');
            svg.setAttribute('stroke-linecap', 'round');
            svg.setAttribute('stroke-linejoin', 'round');
            svg.setAttribute('aria-hidden', 'true');
            if (type === 'image') { svg.style.display = 'none'; }
            svg.innerHTML = FUP_ICONS[type].icon;
            el.appendChild(svg);

            var ext = fupExt(file.name);
            if (ext && type !== 'image') {
                var b = document.createElement('span');
                b.className = 'fc-ext';
                b.textContent = ext;
                el.appendChild(b);
            }
            return { el: el, url: url };
        }

        function render() {
            list.innerHTML = '';
            files.forEach(function (file, i) {
                var chip = document.createElement('div');
                chip.className = 'fup-chip';

                chip.appendChild(thumbEls[i]);

                var info = document.createElement('span');
                info.className = 'fc-info';
                var nm = document.createElement('span');
                nm.textContent = file.name || 'فایل';
                var mt = document.createElement('small');
                mt.textContent = fupFa(Math.max(1, Math.round(file.size / 1024))) + ' کیلوبایت';
                info.appendChild(nm);
                info.appendChild(mt);
                chip.appendChild(info);

                var rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'fc-rm';
                rm.title = 'حذف فایل';
                rm.setAttribute('aria-label', 'حذف ' + (file.name || 'فایل'));
                rm.textContent = '✕';
                rm.addEventListener('click', function () {
                    if (thumbUrls[i]) { try { URL.revokeObjectURL(thumbUrls[i]); } catch (e) { /* noop */ } }
                    files.splice(i, 1);
                    thumbEls.splice(i, 1);
                    thumbUrls.splice(i, 1);
                    render();
                });
                chip.appendChild(rm);

                list.appendChild(chip);
            });
        }

        /* انتخاب با کلیک روی دراپ‌زون */
        if (zone) {
            zone.addEventListener('click', function () { input.click(); });

            /* کش‌ودرگ */
            var depth = 0;
            root.addEventListener('dragenter', function (e) {
                if (!e.dataTransfer || Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') === -1) { return; }
                e.preventDefault();
                depth++;
                root.classList.add('drag');
            });
            root.addEventListener('dragover', function (e) { e.preventDefault(); });
            root.addEventListener('dragleave', function (e) {
                e.preventDefault();
                depth = Math.max(0, depth - 1);
                if (depth === 0) { root.classList.remove('drag'); }
            });
            root.addEventListener('drop', function (e) {
                e.preventDefault();
                depth = 0;
                root.classList.remove('drag');
                addFiles(e.dataTransfer && e.dataTransfer.files);
            });
        }

        input.addEventListener('change', function () {
            addFiles(input.files);
            input.value = '';
        });

        return {
            files: function () { return files.slice(); },
            clear: function () {
                thumbUrls.forEach(function (u) {
                    if (u) { try { URL.revokeObjectURL(u); } catch (e) { /* noop */ } }
                });
                files = [];
                thumbEls = [];
                thumbUrls = [];
                render();
            }
        };
    }

    /* آپلودر مدارک تکمیلی */
    var extraUploader = createUploader({
        inputId: 'extraDocs',
        listId: 'fupExtraList',
        zoneId: 'fupExtraZone',
        multiple: true,
        maxKb: 5120
    });

    /* آپلودرهای فیلدهای file فرم داینامیک */
    var fieldUploaders = {};

    /* ---------- بارگذاری جزئیات ---------- */
    CN.api('/services/' + serviceId, {
        success: function (resp) {
            detail = resp.data;
            renderDetail();
        },
        error: function (xhr, message) {
            $('#svcName').text('خدمت یافت نشد');
            $('#svcDesc').text(message);
        }
    });

    /* ---------- فاز ۱۵: وضعیت ساعت کاری (برای گارد ثبت) ---------- */
    var workHours = null;
    CN.api('/work-hours', {
        success: function (resp) { workHours = resp.data; }
    });

    var DAY_NAMES = { 6: 'شنبه', 0: 'یکشنبه', 1: 'دوشنبه', 2: 'سه‌شنبه', 3: 'چهارشنبه', 4: 'پنج‌شنبه', 5: 'جمعه' };

    function workHoursModal(wh) {
        var days = (wh.days || []).map(function (d) {
            return '<span class="' + (wh.day_today_open === false && d === wh.day_of_week ? '' : 'on') + '">' + (DAY_NAMES[d] || d) + '</span>';
        }).join('');

        var html =
            '<div class="ann-backdrop" role="dialog" aria-modal="true" aria-labelledby="wh-title">' +
            '  <div class="ann-card">' +
            '    <div class="ann-head">' +
            '      <span class="wh-clock"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>' +
            '      <div class="ann-head-text min-w-0">' +
            '        <span class="ann-kicker">🕒 ساعت کاری</span>' +
            '        <h2 class="ann-title" id="wh-title">در حال حاضر خارج از ساعت کاری هستیم</h2>' +
            '      </div>' +
            '    </div>' +
            '    <div class="ann-body">' +
            '      <p class="ann-text" style="text-align:center">ثبت درخواست در بازهٔ ساعت کاری امکان‌پذیر است. درخواست شما پس از باز شدن دفتر ثبت می‌شود.</p>' +
            (wh.message ? '<p class="ann-text" style="text-align:center;color:var(--brand-700)">' + CN.esc(wh.message) + '</p>' : '') +
            '      <div class="wh-hours"><span class="wh-pill"> از ' + CN.esc(wh.start) + ' </span><span class="wh-pill"> تا ' + CN.esc(wh.end) + ' </span></div>' +
            '      <div class="wh-days">' + days + '</div>' +
            '    </div>' +
            '    <div class="ann-foot"><span class="ann-count"></span><button type="button" class="btn btn-primary ann-ok">متوجه شدم</button></div>' +
            '  </div>' +
            '</div>';

        var $m = $(html);
        $('body').append($m);
        $m.find('.ann-ok').on('click', function () { $m.remove(); });
        $m.on('click', function (e) { if (e.target === $m[0]) { $m.remove(); } });
    }

    function checkWorkHours() {
        if (workHours && workHours.enabled && !workHours.open) {
            workHoursModal(workHours);
            return false;
        }
        return true;
    }

    /* ---------- فاز ۱۵: مودال وضعیت خدمت (قطع/انقضا) + آلرت ---------- */
    var CLOCK_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>';
    var WARN_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.6 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>';

    function stateModal(kind, note, expiresLabel) {
        var isExpired = kind === 'expired';
        var html =
            '<div class="ann-backdrop" role="dialog" aria-modal="true" aria-labelledby="st-title">' +
            '  <div class="ann-card">' +
            '    <div class="ann-head">' +
            '      <span class="wh-clock" style="background:' + (isExpired ? 'linear-gradient(135deg,#d97706,#b45309)' : 'linear-gradient(135deg,#e11d48,#9f1239)') + ';box-shadow:0 12px 28px -8px rgba(225,29,72,.5)">' + WARN_SVG + '</span>' +
            '      <div class="ann-head-text min-w-0">' +
            '        <span class="ann-kicker">' + (isExpired ? '⏰ مهلت خدمت' : '⛔ خدمت قطع است') + '</span>' +
            '        <h2 class="ann-title" id="st-title">' + (isExpired ? 'مهلت این خدمت به پایان رسیده است' : 'این خدمت موقتاً قطع است') + '</h2>' +
            '      </div>' +
            '    </div>' +
            '    <div class="ann-body"><p class="ann-text" style="text-align:center">' + CN.esc(note || '') + '</p>' +
            (expiresLabel ? '<p class="ann-text" style="text-align:center;color:var(--ink-faint)">مهلت: ' + CN.esc(expiresLabel) + '</p>' : '') +
            '    </div>' +
            '    <div class="ann-foot"><span class="ann-count"></span><button type="button" class="btn btn-primary ann-ok">بستن</button></div>' +
            '  </div>' +
            '</div>';

        var $m = $(html);
        $('body').append($m);
        $m.find('.ann-ok').on('click', function () { $m.remove(); });
        $m.on('click', function (e) { if (e.target === $m[0]) { $m.remove(); } });
    }

    function alertModal(alert) {
        var mediaHtml = '';
        if (alert.type === 'image' && alert.image_url) {
            mediaHtml = '<div class="ann-media"><img src="' + CN.esc(alert.image_url) + '" alt="اطلاعیه خدمت"></div>';
        }

        var html =
            '<div class="ann-backdrop" role="dialog" aria-modal="true" aria-labelledby="al-title">' +
            '  <div class="ann-card">' +
            '    <div class="ann-head">' +
            '      <span class="ann-icon">' + CLOCK_SVG + '</span>' +
            '      <div class="ann-head-text min-w-0">' +
            '        <span class="ann-kicker">🔔 اطلاعیه خدمت</span>' +
            '        <h2 class="ann-title" id="al-title">قبل از ثبت، این را بخوانید</h2>' +
            '      </div>' +
            '    </div>' +
            '    <div class="ann-body">' + mediaHtml +
            (alert.text ? '<p class="ann-text" style="text-align:center">' + CN.esc(alert.text) + '</p>' : '') +
            '    </div>' +
            '    <div class="ann-foot"><span class="ann-count"></span><button type="button" class="btn btn-primary ann-ok">متوجه شدم، ادامه می‌دهم</button></div>' +
            '  </div>' +
            '</div>';

        var $m = $(html);
        $('body').append($m);
        $m.find('.ann-ok').on('click', function () { $m.remove(); });
        $m.on('click', function (e) { if (e.target === $m[0]) { $m.remove(); } });
    }

    function renderDetail() {
        var d = detail;

        /* قهرمان */
        $('#svcIcon').text((d.category && d.category.icon) || '📄');
        $('#svcName').text(d.name);
        $('#svcDesc').text(d.description || '');

        /* فاز ۱۵ — تصویر خدمت */
        if (d.image_url) {
            $('#svcHero').prepend('<img class="svc-hero-img" src="' + CN.esc(d.image_url) + '" alt="' + CN.esc(d.name) + '">');
            $('#svcIcon').addClass('hidden');
        }

        /* فاز ۱۵ — وضعیت برخط: قطع/انقضا → بنر + بلوکه کردن فرم */
        if (d.availability_state === 'unavailable' || d.availability_state === 'expired') {
            var isExp = d.availability_state === 'expired';
            var banner =
                '<div class="svc-state-banner ' + (isExp ? 'svc-state--expired' : 'svc-state--unavailable') + '">' +
                '<span class="sb-ico">' + WARN_SVG + '</span>' +
                '<div class="min-w-0"><b>' + (isExp ? 'مهلت خدمت به پایان رسیده است' : 'این خدمت موقتاً از سایت اصلی قطع است') + '</b>' +
                '<p>' + CN.esc(d.availability_note || '') + '</p></div></div>';

            $('#formCard').before(banner);
            $('#formCard').addClass('hidden');
            stateModal(d.availability_state, d.availability_note, isExp ? d.expires_at_label : null);
            return; // فرم رندر نمی‌شود
        }

        /* فاز ۱۵ — آلرت خدمت (متن/تصویر) هنگام باز شدن */
        if (d.alert && (d.alert.text || d.alert.image_url)) {
            window.setTimeout(function () { alertModal(d.alert); }, 600);
        }

        var badges = '';
        if (d.category) {
            badges += '<span class="badge badge-stone">' + CN.esc(d.category.icon || '🏷') + ' ' + CN.esc(d.category.name) + '</span>';
        }
        if (d.is_featured) {
            badges += '<span class="badge badge-amber">⭐ پیشنهاد ویژه</span>';
        }
        if (d.estimated_time_label && d.estimated_time_label !== '—') {
            badges += '<span class="badge badge-stone">⏱ ' + CN.esc(d.estimated_time_label) + '</span>';
        }
        if (d.version) {
            badges += '<span class="badge badge-stone">نسخه ' + CN.toFaDigits(d.version) + ' فرم</span>';
        }
        $('#svcBadges').html(badges);
        $('#svcTime').text(d.estimated_time_label && d.estimated_time_label !== '—' ? d.estimated_time_label : '');

        /* ردیف‌های قیمت */
        var rows = '';
        rows += priceRow('💰', 'کارمزد خدمت', d.base_price, false);
        (d.costs || []).forEach(function (c) {
            rows += priceRow(
                c.type === 'fee' ? '🧾' : '📦',
                c.title + (c.is_commission ? '' : ''),
                c.amount,
                !!c.is_commission
            );
        });
        rows += '<div class="price-row total"><span class="pr-title">هزینهٔ درخواست</span><span class="pr-amount">' + CN.faMoney(d.total_amount) + ' تومان</span></div>';
        $('#costRows').html(rows);
        $('#totalAmount').text(CN.faMoneyUnit(d.total_amount));

        /* فرم داینامیک */
        renderFields(d.form_fields || []);

        /* فاز ۱۲ — آپلودرهای فیلدهای file را بساز */
        initFieldUploaders(d.form_fields || []);

        /* یادداشت مدارک — فقط وقتی خدمت واقعاً نیاز به آپلود دارد نمایش داده می‌شود */
        var needsUpload = !!(d.requires_upload || d.has_file_fields);
        $('#extraDocsGroup').toggleClass('hidden', !needsUpload);
        if (needsUpload) {
            $('#extraDocsGroup .label').text(
                d.requires_upload ? 'مدارک لازم (الزامی برای این خدمت)' : 'مدارک (پیوست فایل فرم)'
            );
        }

        $('#submitOrderBtn').prop('disabled', false);
    }

    function priceRow(icon, title, amount, commission) {
        return '<div class="price-row' + (commission ? ' commission' : '') + '">' +
            '<span class="pr-title">' + CN.esc(icon) + ' ' + CN.esc(title) + '</span>' +
            '<span class="pr-amount">' + CN.faMoney(amount) + ' تومان</span>' +
            '</div>';
    }

    /* ---------- رندر فیلدهای داینامیک ---------- */
    function renderFields(fields) {
        var html = '';

        fields.forEach(function (f) {
            html += fieldHtml(f);
        });

        if (!fields.length) {
            html = '<p class="text-faint tiny">این خدمت فرم ندارد؛ مستقیم ثبت کنید.</p>';
        }

        $('#dynamicFields').html(html);

        /* بایند تقویم شمسی روی فیلدهای تاریخ رندرشده */
        if (window.CNJdp) { window.CNJdp.bindAll(document.getElementById('dynamicFields')); }
    }

    function fieldHtml(f) {
        var req = f.is_required ? ' <span class="req">*</span>' : '';
        var help = f.help_text ? '<p class="help-text">' + CN.esc(f.help_text) + '</p>' : '';
        var err = '<p class="field-error" id="err_' + CN.esc(f.name) + '"></p>';
        var group = '';

        switch (f.field_type) {
            case 'textarea':
                group = inputShell(f, '<textarea class="field" id="f_' + CN.esc(f.name) + '" rows="4" placeholder="' + CN.esc(f.placeholder || '') + '"></textarea>');
                break;

            case 'select':
                var opts = '<option value="">انتخاب کنید…</option>';
                (f.options || []).forEach(function (o) {
                    opts += '<option value="' + CN.esc(o) + '">' + CN.esc(o) + '</option>';
                });
                group = inputShell(f, '<select class="field" id="f_' + CN.esc(f.name) + '">' + opts + '</select>');
                break;

            case 'radio':
                var radios = '<div class="check-grid">';
                (f.options || []).forEach(function (o) {
                    radios += '<label class="check-row"><input type="radio" name="r_' + CN.esc(f.name) + '" value="' + CN.esc(o) + '">' + CN.esc(o) + '</label>';
                });
                radios += '</div>';
                group = shell(f, req, radios, err, help);
                break;

            case 'checkbox':
                var checks = '<div class="check-grid">';
                (f.options || []).forEach(function (o) {
                    checks += '<label class="check-row"><input type="checkbox" name="c_' + CN.esc(f.name) + '" value="' + CN.esc(o) + '">' + CN.esc(o) + '</label>';
                });
                checks += '</div>';
                group = shell(f, req, checks, err, help);
                break;

            case 'file':
                group = shell(f, req,
                    '<div class="fup" data-fup="' + CN.esc(f.name) + '">' +
                    '<input type="file" id="ff_' + CN.esc(f.name) + '" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" hidden>' +
                    '<button type="button" class="fup-zone" data-zone="' + CN.esc(f.name) + '">' +
                    '<span class="fz-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 8 5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg></span>' +
                    '<span class="fz-txt"><strong>انتخاب فایل یا رها کردن در اینجا</strong><small>PDF، تصویر یا Word — حداکثر ۵ مگابایت</small></span>' +
                    '</button>' +
                    '<div class="fup-list" id="fupList_' + CN.esc(f.name) + '" aria-live="polite"></div>' +
                    '</div>',
                    err, help);
                break;

            case 'mobile':
            case 'national_code':
            case 'number':
                group = inputShell(f, '<input class="field num" id="f_' + CN.esc(f.name) + '" type="tel" inputmode="' + (f.field_type === 'number' ? 'numeric' : 'tel') + '" placeholder="' + CN.esc(f.placeholder || (f.field_type === 'mobile' ? '۰۹…' : '')) + '" dir="ltr" style="text-align:center">');
                break;

            case 'date':
                group = inputShell(f, '<input class="field num" id="f_' + CN.esc(f.name) + '" type="text" inputmode="numeric" placeholder="۱۴۰۰/۰۵/۱۲" dir="ltr" style="text-align:center" data-jdp title="برای انتخاب تاریخ کلیک کنید">');
                break;

            case 'email':
                group = inputShell(f, '<input class="field num" id="f_' + CN.esc(f.name) + '" type="email" placeholder="' + CN.esc(f.placeholder || 'name@mail.com') + '" dir="ltr" style="text-align:center">');
                break;

            default: // text
                group = inputShell(f, '<input class="field" id="f_' + CN.esc(f.name) + '" type="text" placeholder="' + CN.esc(f.placeholder || '') + '" maxlength="255">');
        }

        return group;
    }

    function inputShell(f, inputHtml) {
        var req = f.is_required ? ' <span class="req">*</span>' : '';
        var help = f.help_text ? '<p class="help-text">' + CN.esc(f.help_text) + '</p>' : '';
        return shell(f, req, inputHtml, '<p class="field-error" id="err_' + CN.esc(f.name) + '"></p>', help);
    }

    function shell(f, req, inner, err, help) {
        return '<div class="form-group" data-field="' + CN.esc(f.name) + '">' +
            '<label class="label">' + CN.esc(f.label) + req + '</label>' +
            inner + err + (help || '') +
            '</div>';
    }

    /* ساخت آپلودر برای فیلدهای file رندرشده */
    function initFieldUploaders(fields) {
        fieldUploaders = {};
        fields.forEach(function (f) {
            if (f.field_type !== 'file') { return; }
            fieldUploaders[f.name] = createUploader({
                inputId: 'ff_' + f.name,
                listId: 'fupList_' + f.name,
                multiple: false,
                maxKb: 5120
            });
        });
    }

    /* ---------- جمع‌آوری مقادیر ---------- */
    function collectFormData() {
        var data = {};
        var ok = true;

        (detail.form_fields || []).forEach(function (f) {
            if (f.field_type === 'file') { return; }

            var value = null;

            if (f.field_type === 'select') {
                value = $('#f_' + f.name).val() || null;
            } else if (f.field_type === 'radio') {
                value = $('input[name="r_' + f.name + '"]:checked').val() || null;
            } else if (f.field_type === 'checkbox') {
                var checked = $('input[name="c_' + f.name + '"]:checked').map(function () { return this.value; }).get();
                value = checked.length ? checked : null;
            } else {
                value = ($('#f_' + f.name).val() || '').trim() || null;
                if (value !== null && ['number', 'mobile', 'national_code'].indexOf(f.field_type) !== -1) {
                    value = CN.toEnDigits(value).replace(/[,،]/g, '');
                }
            }

            if (f.is_required && (value === null || value === '' || (Array.isArray(value) && !value.length))) {
                ok = false;
                CN.fieldError(f.name, 'فیلد «' + f.label + '» الزامی است.');
                return;
            }

            if (value !== null) {
                data[f.name] = value;
            }
        });

        return ok ? data : null;
    }

    /* ---------- ثبت سفارش ---------- */
    function submitOrder() {
        CN.clearFieldErrors('#orderForm');

        /* فاز ۱۵ — گارد ساعت کاری (سمت کلاینت؛ سرور هم چک سخت دارد) */
        if (!checkWorkHours()) { return; }

        if (detail && (detail.availability_state === 'unavailable' || detail.availability_state === 'expired')) {
            stateModal(detail.availability_state, detail.availability_note);
            return;
        }

        var formData = collectFormData();
        if (formData === null) {
            CN.toast('لطفاً فیلدهای الزامی را کامل کنید.', 'error');
            $('html, body').animate({ scrollTop: ($('.form-group').first().offset() || { top: 0 }).top - 90 }, 300);
            return;
        }

        var fd = new FormData();
        fd.append('service_id', serviceId);
        fd.append('form_data', JSON.stringify(formData));

        // فایل‌های فیلدهای نوع file — فاز ۱۲: از آپلودرهای زیبا
        (detail.form_fields || []).forEach(function (f) {
            if (f.field_type !== 'file') { return; }
            var up = fieldUploaders[f.name];
            var files = up ? up.files() : [];
            if (files.length) {
                files.forEach(function (file) {
                    fd.append('files[' + f.name + '][]', file);
                });
            } else if (f.is_required) {
                CN.fieldError(f.name, 'بارگذاری «' + f.label + '» الزامی است.');
            }
        });

        // مدارک تکمیلی — فاز ۱۲: از آپلودر زیبا
        extraUploader.files().forEach(function (file) {
            fd.append('documents[]', file);
        });

        CN.btnLoading($('#submitOrderBtn'), true, 'در حال ثبت…');

        CN.api('/orders', {
            method: 'POST',
            formData: fd,
            success: function (resp) {
                CN.btnLoading($('#submitOrderBtn'), false);
                CN.toast('درخواست شما ثبت شد و برای اپراتورها ارسال شد.', 'success', 5200);
                window.location.replace(CN.withPort('/app/orders/' + resp.data.id));
            },
            error: function (xhr, message) {
                CN.btnLoading($('#submitOrderBtn'), false);

                /* فاز ۱۵ — پاسخ‌های ساختاریافتهٔ گاردها → مودال */
                var body = (xhr.responseJSON || {});
                if (body.code === 'outside_work_hours' && body.work_hours) {
                    workHoursModal({
                        start: body.work_hours.start,
                        end: body.work_hours.end,
                        days: body.work_hours.days,
                        message: body.work_hours.message,
                        day_today_open: false
                    });
                    return;
                }
                if (body.code === 'service_unavailable' || body.code === 'service_expired') {
                    stateModal(body.code === 'service_expired' ? 'expired' : 'unavailable', body.message, body.expires_at);
                    return;
                }

                var errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};
                CN.applyErrors(errors);
                CN.toast(message, 'error');
            }
        });
    }

    $('#orderForm').on('submit', function (e) {
        e.preventDefault();
        submitOrder();
    });
})(jQuery);
