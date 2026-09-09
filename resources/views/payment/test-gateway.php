<?php

/**
 * نمای اختصاصی «درگاه پرداخت تست» — بدون حتی یک خط جاوااسکریپت.
 *
 * چرا؟ هدر امنیتی CSP پروژه (script-src 'self') اسکریپت‌های درون‌خطیِ
 * نمای پیش‌فرض پکیج shetabit را بلاک می‌کند و دکمه‌های درگاه بی‌واکنش
 * می‌شوند. این نما با دکمه‌های submit واقعی داخل فرم کار می‌کند و
 * کاملاً CSP-سازگار است (استایل درون‌خطی مجاز است).
 *
 * این فایل توسط RedirectionForm::render() با require لود می‌شود؛
 * متغیرهای $action, $inputs, $method در دسترس‌اند (درایور local).
 *
 * CSP مجاز می‌داند: استایل درون‌خطی (unsafe-inline) + فونت گوگل.
 */

$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$title = $inputs['title'] ?? 'درگاه پرداخت تست';
$description = $inputs['description'] ?? '';
$orderLabel = $inputs['orderLabel'] ?? 'شماره سفارش';
$amountLabel = $inputs['amountLabel'] ?? 'مبلغ قابل پرداخت';
$payButton = $inputs['payButton'] ?? 'پرداخت موفق';
$cancelButton = $inputs['cancelButton'] ?? 'پرداخت ناموفق';
$orderId = $inputs['orderId'] ?? '—';
$price = $inputs['price'] ?? '0';
$successUrl = $inputs['successUrl'] ?? '';
$cancelUrl = $inputs['cancelUrl'] ?? '';

/* تبدیل آدرس مطلق به نسبی تا از هر مبدأ (لوکال/گیت‌وی/دامنه) کار کند */
$relative = function (string $url): string {
    $path = (string) parse_url($url, PHP_URL_PATH);
    $query = (string) parse_url($url, PHP_URL_QUERY);
    if ($path === '' || $path === '/') { return $url; }
    return $query ? $path.'?'.$query : $path;
};
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title><?= $e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-600: #a8652e;
            --brand-700: #8f5424;
            --brand-900: #4a2c12;
            --ink: #2d2317;
            --ink-soft: #6b5d4a;
            --ok-600: #1f7a4d;
            --err-600: #b33636;
            --line: #e9e1d5;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; }
        body {
            min-height: 100%;
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, Arial, sans-serif;
            background:
                radial-gradient(1000px 500px at 100% -10%, rgba(168,101,46,.10), transparent 60%),
                radial-gradient(800px 400px at 0% 110%, rgba(168,101,46,.07), transparent 55%),
                #faf7f2;
            color: var(--ink);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px 40px;
        }
        .gw-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: 0 18px 50px rgba(74, 44, 18, .12);
            overflow: hidden;
        }
        .gw-head {
            background: linear-gradient(125deg, var(--brand-600), var(--brand-900) 75%);
            color: #fff;
            padding: 26px 22px 22px;
            text-align: center;
            position: relative;
        }
        .gw-head::after {
            content: '';
            position: absolute;
            inset-inline-end: -30px;
            top: -30px;
            width: 110px;
            height: 110px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(226,186,133,.35), transparent 70%);
        }
        .gw-logo {
            width: 54px;
            height: 54px;
            margin: 0 auto 10px;
            border-radius: 16px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.25);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gw-logo svg { width: 27px; height: 27px; stroke: #fff; }
        .gw-title { font-size: 17px; font-weight: 800; }
        .gw-sub { font-size: 11.5px; opacity: .85; margin-top: 5px; line-height: 1.9; font-weight: 400; }
        .gw-body { padding: 20px 22px 8px; }
        .gw-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 2px;
            border-bottom: 1px dashed var(--line);
            font-size: 13px;
        }
        .gw-row:last-of-type { border-bottom: 0; }
        .gw-row .k { color: var(--ink-soft); font-weight: 600; display: flex; align-items: center; gap: 7px; }
        .gw-row .k svg { width: 15px; height: 15px; stroke: var(--brand-600); }
        .gw-row .v { font-weight: 700; letter-spacing: .2px; }
        .gw-row .v.num { direction: ltr; }
        .gw-row.total { background: #fdf9f3; border: 1px solid #f0e5d6; border-radius: 12px; padding: 13px 14px; margin-top: 8px; }
        .gw-row.total .v { color: var(--brand-700); font-size: 16px; font-weight: 800; }
        .gw-actions { padding: 16px 22px 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .gw-btn {
            -webkit-appearance: none;
            appearance: none;
            font: inherit;
            cursor: pointer;
            border: 0;
            border-radius: 12px;
            padding: 13px 10px;
            font-size: 13.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: transform .15s, box-shadow .15s, filter .15s;
            width: 100%;
        }
        .gw-btn svg { width: 17px; height: 17px; stroke: currentColor; }
        .gw-btn:focus-visible { outline: 3px solid rgba(168,101,46,.3); outline-offset: 2px; }
        .gw-btn-success {
            background: linear-gradient(100deg, #2e9e63, var(--ok-600));
            color: #fff;
            box-shadow: 0 8px 20px rgba(31,122,77,.30);
        }
        .gw-btn-success:hover { transform: translateY(-1px); box-shadow: 0 10px 26px rgba(31,122,77,.38); }
        .gw-btn-cancel {
            background: #fdf2f2;
            color: var(--err-600);
            border: 1.5px solid #f0d2d2;
        }
        .gw-btn-cancel:hover { background: #fbe7e7; transform: translateY(-1px); }
        .gw-foot {
            text-align: center;
            font-size: 10.5px;
            color: var(--ink-soft);
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .gw-foot svg { width: 13px; height: 13px; stroke: var(--ok-600); }
        @media (max-width: 400px) {
            .gw-actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="gw-card" role="main">
        <div class="gw-head">
            <div class="gw-logo" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20"/>
                </svg>
            </div>
            <h1 class="gw-title"><?= $e($title) ?></h1>
            <?php if ($description): ?>
                <p class="gw-sub"><?= $e($description) ?></p>
            <?php endif; ?>
        </div>

        <div class="gw-body">
            <div class="gw-row">
                <span class="k">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>
                    <?= $e($orderLabel) ?>
                </span>
                <span class="v num"><?= $e($orderId) ?></span>
            </div>
            <div class="gw-row total">
                <span class="k"><?= $e($amountLabel) ?></span>
                <span class="v"><?= $e($price) ?> تومان</span>
            </div>
        </div>

        <div class="gw-actions">
            <form id="success_form" action="<?= $e($relative($successUrl)) ?>" method="post">
                <input type="hidden" name="_token" value="<?= $e(csrf_token()) ?>" autocomplete="off">
                <button type="submit" class="gw-btn gw-btn-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    <?= $e($payButton) ?>
                </button>
            </form>
            <form id="cancel_form" action="<?= $e($relative($cancelUrl)) ?>" method="post">
                <input type="hidden" name="_token" value="<?= $e(csrf_token()) ?>" autocomplete="off">
                <button type="submit" class="gw-btn gw-btn-cancel">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    <?= $e($cancelButton) ?>
                </button>
            </form>
        </div>
    </main>

    <p class="gw-foot">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
        </svg>
        اتصال امن · پرداخت آزمایشی توسعه
    </p>
</body>
</html>
