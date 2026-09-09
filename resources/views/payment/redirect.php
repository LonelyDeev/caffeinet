<?php

/**
 * نمای انتقال به درگاه واقعی (زرین‌پال و…) — CSP-سازگار.
 *
 * نمای پیش‌فرض پکیج با اسکریپت درون‌خطیِ اتوسابمیت کار می‌کند که CSP
 * پروژه بلاکش می‌کند. این نما فرم را با دکمهٔ submit واقعی می‌فرستد
 * (بدون جاوااسکریپت) — یک کلیک اضافه، اما همیشه کار می‌کند.
 *
 * متغیرهای $action, $inputs, $method از RedirectionForm::render().
 */

$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

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
    <title>انتقال به درگاه پرداخت</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #faf7f2;
            color: #2d2317;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .rd-card {
            width: 100%;
            max-width: 380px;
            background: #fff;
            border: 1px solid #e9e1d5;
            border-radius: 20px;
            box-shadow: 0 18px 50px rgba(74, 44, 18, .12);
            padding: 30px 24px 26px;
            text-align: center;
        }
        .rd-dots { display: flex; justify-content: center; gap: 8px; margin-bottom: 18px; }
        .rd-dots span { width: 12px; height: 12px; border-radius: 999px; background: #a8652e; opacity: .25; }
        .rd-dots span:nth-child(1) { opacity: 1; }
        .rd-dots span:nth-child(2) { opacity: .55; }
        .rd-title { font-size: 16.5px; font-weight: 800; }
        .rd-desc { font-size: 12.5px; color: #6b5d4a; margin: 8px 0 20px; line-height: 2; }
        .rd-btn {
            -webkit-appearance: none; appearance: none; font: inherit; cursor: pointer; border: 0;
            width: 100%;
            background: linear-gradient(100deg, #b9743a, #8f5424);
            color: #fff;
            border-radius: 12px;
            padding: 14px 10px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(168, 101, 46, .30);
            transition: transform .15s, box-shadow .15s;
        }
        .rd-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 26px rgba(168, 101, 46, .38); }
        .rd-btn:focus-visible { outline: 3px solid rgba(168,101,46,.3); outline-offset: 2px; }
    </style>
</head>
<body>
    <div class="rd-card">
        <div class="rd-dots" aria-hidden="true"><span></span><span></span><span></span></div>
        <h1 class="rd-title">در حال انتقال به درگاه پرداخت</h1>
        <p class="rd-desc">برای ادامهٔ پرداخت امن، روی دکمهٔ زیر بزنید.</p>
        <form method="<?= $e($method) ?>" action="<?= $e($relative($action)) ?>">
            <?php foreach (($inputs ?? []) as $name => $value): ?>
                <input type="hidden" name="<?= $e($name) ?>" value="<?= $e($value) ?>">
            <?php endforeach; ?>
            <button type="submit" class="rd-btn">ادامهٔ پرداخت</button>
        </form>
    </div>
</body>
</html>
