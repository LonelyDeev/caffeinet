/* اپ مشتری — صفحه خانه (کاتالوگ) */
/* global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireAuth()) { return; }

    var state = {
        q: '',
        categoryId: 0,
        page: 1,
        hasMore: false,
        loading: false
    };

    var categories = []; // flat: {id, name, icon, parent_id, children: []}

    /* ---------- دسته‌بندی‌ها ---------- */
    function loadCategories() {
        CN.api('/categories/tree', {
            success: function (resp) {
                categories = flatten(resp.data || []);
                renderChips();
            }
        });
    }

    function flatten(nodes, parent) {
        var out = [];
        (nodes || []).forEach(function (n) {
            out.push({
                id: n.id,
                name: n.name,
                icon: n.icon,
                parent_id: n.parent_id || 0,
                children: (n.children || []).map(function (c) { return c.id; })
            });
            out = out.concat(flatten(n.children || [], n.id));
        });
        return out;
    }

    function renderChips() {
        var $row = $('#categoryChips');
        var html = '<button class="chip' + (state.categoryId === 0 ? ' active' : '') + '" data-cat="0" type="button"><span class="chip-icon">✨</span> همه</button>';

        // اگر دسته فعالِ فرزند است → ریشه + خواهر/برادرها؛ وگرنه ریشه‌ها
        var active = categories.filter(function (c) { return c.id === state.categoryId; })[0];
        var parentId = active ? (active.parent_id || 0) : 0;

        categories
            .filter(function (c) { return (c.parent_id || 0) === parentId; })
            .forEach(function (c) {
                var isActive = state.categoryId === c.id;
                html += '<button class="chip' + (isActive ? ' active' : '') + '" data-cat="' + c.id + '" type="button">' +
                    '<span class="chip-icon">' + CN.esc(c.icon) + '</span> ' + CN.esc(c.name) +
                    '</button>';
            });

        // اگر ریشه انتخاب شده و فرزند دارد → زیر-دسته‌ها
        if (active && active.children.length) {
            html += '<span style="width:14px;flex:none"></span>';
            active.children.forEach(function (childId) {
                var child = categories.filter(function (c) { return c.id === childId; })[0];
                if (!child) { return; }
                html += '<button class="chip' + (state.categoryId === child.id ? ' active' : '') + '" data-cat="' + child.id + '" type="button">' +
                    '<span class="chip-icon">↳</span> ' + CN.esc(child.name) +
                    '</button>';
            });
        }

        $row.html(html);

        $row.off('click', '.chip').on('click', '.chip', function () {
            state.categoryId = +$(this).data('cat');
            renderChips();
            resetAndLoad();
        });
    }

    /* ---------- خدمات ---------- */
    function resetAndLoad() {
        state.page = 1;
        state.hasMore = false;
        $('#servicesList').empty();
        $('#servicesEmpty').addClass('hidden');
        loadServices();
    }

    function loadServices() {
        if (state.loading) { return; }
        state.loading = true;

        if (state.page === 1) {
            $('#servicesList').append('<div class="skeleton svc"></div><div class="skeleton svc"></div>');
        } else {
            $('#servicesMoreLoader').removeClass('hidden');
        }

        var params = '?page=' + state.page;
        if (state.q) { params += '&q=' + encodeURIComponent(state.q); }
        if (state.categoryId) { params += '&category=' + state.categoryId; }

        CN.api('/services' + params, {
            success: function (resp) {
                state.loading = false;
                $('.skeleton').remove();
                $('#servicesMoreLoader').addClass('hidden');

                $('#servicesCount').text(CN.toFaDigits(resp.total || 0) + ' خدمت');

                (resp.data || []).forEach(function (s) {
                    $('#servicesList').append(serviceCard(s));
                });

                state.hasMore = !!resp.next_page_url;
                $('#loadMoreBtn').toggleClass('hidden', !state.hasMore);

                if (!resp.data || !resp.data.length) {
                    $('#servicesEmpty').removeClass('hidden');
                }

                loadFeatured(resp.data || []);
            },
            error: function () {
                state.loading = false;
                $('.skeleton').remove();
                $('#servicesMoreLoader').addClass('hidden');
            }
        });
    }

    function serviceCard(s) {
        var icon = (s.category && s.category.icon) || '📄';
        var catName = (s.category && s.category.name) || '';

        return '<a class="service-card" href="' + CN.withPort('/app/service/' + s.id) + '">' +
            '<span class="svc-icon">' + CN.esc(icon) + '</span>' +
            '<span class="svc-body">' +
            '<span class="svc-name">' + CN.esc(s.name) + '</span>' +
            '<span class="svc-meta">' +
            (catName ? '<span>🏷 ' + CN.esc(catName) + '</span>' : '') +
            (s.estimated_time_label && s.estimated_time_label !== '—' ? '<span>⏱ ' + CN.esc(s.estimated_time_label) + '</span>' : '') +
            '</span>' +
            '</span>' +
            '<span class="svc-price">' +
            '<span class="amount">' + CN.faMoney(s.total_amount) + '</span>' +
            '<span class="unit">تومان</span>' +
            '</span>' +
            '</a>';
    }

    /* ---------- ویژه‌ها (از صفحه اول بدون فیلتر) ---------- */
    var featuredLoaded = false;

    function loadFeatured(firstPage) {
        if (featuredLoaded || state.q || state.categoryId || state.page !== 1) { return; }
        featuredLoaded = true;

        var featured = firstPage.filter(function (s) { return s.is_featured; });
        if (!featured.length) { return; }

        var html = '';
        featured.slice(0, 6).forEach(function (s) {
            html += '<a class="featured-card" href="' + CN.withPort('/app/service/' + s.id) + '">' +
                '<div class="f-icon">' + CN.esc((s.category && s.category.icon) || '⭐') + '</div>' +
                '<div class="f-name">' + CN.esc(s.name) + '</div>' +
                '<div class="f-meta">' + CN.esc((s.category && s.category.name) || '') + ' · ' + CN.esc(s.estimated_time_label || '') + '</div>' +
                '<div class="f-price">' + CN.faMoneyUnit(s.total_amount) + '</div>' +
                '</a>';
        });

        $('#featuredStrip').html(html);
        $('#featuredSection').removeClass('hidden');
    }

    /* ---------- رویدادها ---------- */
    var onSearch = CN.debounce(function () {
        state.q = $('#searchInput').val().trim();
        resetAndLoad();
    }, 420);

    $('#searchInput').on('input', onSearch);

    $('#loadMoreBtn').on('click', function () {
        if (!state.hasMore || state.loading) { return; }
        state.page++;
        loadServices();
    });

    /* ---------- شروع ---------- */
    loadCategories();
    loadServices();
})(jQuery);
