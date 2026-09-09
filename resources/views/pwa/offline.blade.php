<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#a8652e">
    <meta name="robots" content="noindex">
    <title>آفلاین هستید — {{ config('app.name') }}</title>
    {{-- همه استایل‌ها inline تا این صفحه با یک URL (precache) کامل کار کند --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; }
        body {
            font-family: Vazirmatn, Tahoma, -apple-system, sans-serif;
            background:
                radial-gradient(ellipse 70% 45% at 50% -10%, rgba(196,127,61,.28), transparent),
                radial-gradient(ellipse 45% 32% at 85% 25%, rgba(211,156,92,.10), transparent),
                #31190e;
            color: #f7ead9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100svh;
            padding: 24px;
            text-align: center;
            -webkit-font-smoothing: antialiased;
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: linear-gradient(160deg, rgba(49,25,14,.92), rgba(29,18,6,.96));
            border: 1px solid rgba(226,186,133,.22);
            border-radius: 28px;
            padding: 42px 28px 34px;
            box-shadow: 0 30px 80px rgba(0,0,0,.55), inset 0 1px 0 rgba(226,186,133,.10);
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(135deg, transparent 0 9px, rgba(226,186,133,.045) 9px 10px);
            pointer-events: none;
        }
        .cup { position: relative; width: 108px; margin: 0 auto 8px; }
        .cup svg { display: block; width: 108px; height: auto; color: #e2ba85; }
        .steam {
            position: absolute;
            top: -26px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
        }
        .steam span {
            width: 6px;
            height: 30px;
            border-radius: 99px;
            background: rgba(247,234,217,.65);
            filter: blur(2px);
            animation: rise 2.4s ease-in-out infinite;
        }
        .steam span:nth-child(2) { animation-delay: .8s; }
        .steam span:nth-child(3) { animation-delay: 1.6s; }
        @keyframes rise {
            0%, 100% { transform: translateY(6px) scaleY(.75); opacity: .25; }
            50%      { transform: translateY(-4px) scaleY(1); opacity: .9; }
        }
        h1 {
            margin: 14px 0 6px;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.02em;
        }
        p {
            margin: 0 auto;
            max-width: 30ch;
            font-size: 13.5px;
            line-height: 2;
            color: rgba(247,234,217,.66);
            font-weight: 300;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 22px;
            padding: 7px 14px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(239,68,68,.12);
            border: 1px solid rgba(239,68,68,.30);
            color: #fca5a5;
            transition: all .4s ease;
        }
        .status .dot {
            width: 8px; height: 8px; border-radius: 99px;
            background: #ef4444;
            animation: blink 1.6s ease-in-out infinite;
        }
        .status.on {
            background: rgba(52,211,153,.12);
            border-color: rgba(52,211,153,.35);
            color: #6ee7b7;
        }
        .status.on .dot { background: #34d399; animation: none; }
        @keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: .25; } }
        button {
            margin-top: 26px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 14px 20px;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(90deg, #c47f3d, #a8652e);
            border: 0;
            border-radius: 16px;
            cursor: pointer;
            box-shadow: 0 14px 34px rgba(168,101,46,.42);
            transition: transform .25s ease, box-shadow .25s ease, opacity .25s ease;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 18px 44px rgba(168,101,46,.55); }
        button:active { transform: translateY(0); }
        button:disabled { opacity: .55; cursor: wait; transform: none; }
        button svg { width: 16px; height: 16px; }
        button.spin svg { animation: rot 1s linear infinite; }
        @keyframes rot { to { transform: rotate(360deg); } }
        .foot {
            margin-top: 20px;
            font-size: 11px;
            color: rgba(226,186,133,.5);
            font-weight: 500;
        }
        @media (max-height: 640px) {
            .card { padding: 26px 22px 22px; }
            .cup { width: 84px; } .cup svg { width: 84px; } .steam { top: -20px; }
            h1 { font-size: 19px; } p { font-size: 12.5px; line-height: 1.8; }
        }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="cup" aria-hidden="true">
            <div class="steam"><span></span><span></span><span></span></div>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 8h1a4 4 0 1 1 0 8h-1"/>
                <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>
                <path d="M6 2v2"/><path d="M10 2v2"/><path d="M14 2v2"/>
            </svg>
        </div>

        <h1>اتصال اینترنت قطع است</h1>
        <p>آخرین قهوه‌مان را دم کردیم و منتظر وصل شدن شبکه هستیم؛ اپ به‌محض اتصال دوباره کار می‌کند.</p>

        <div class="status" id="status"><span class="dot"></span><span id="statusText">آفلاین</span></div>

        <button id="retry" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
            تلاش دوباره
        </button>

        <div class="foot">{{ config('app.name') }} · صفحه آفلاین</div>
    </main>

    {{-- CSP پروژه اسکریپت درون‌خطی را بلاک می‌کند → فایل خارجی (پیش‌کش SW) --}}
    <script src="{{ asset('assets/js/offline.js') }}?v=2"></script>
</body>
</html>
