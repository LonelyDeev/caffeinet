<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // اعتماد به پروکسی گیت‌وی برای تشخیص صحیح scheme/host
        $middleware->trustProxies(at: '*');

        // گارد پیش‌فرض درخواست‌های API → sanctum (برای auth() در سرویس‌های مشترک)
        $middleware->append(\App\Http\Middleware\ApiDefaultGuard::class);

        // حضور کاربران (v25) — last_seen_at برای نوتیف دستگاه/پیامک آفلاین
        $middleware->web(append: [\App\Http\Middleware\UpdateLastSeen::class]);
        $middleware->api(append: [\App\Http\Middleware\UpdateLastSeen::class]);

        // هدرهای امنیتی + CSP (فاز ۱۱ — hardening)
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // callback درگاه پرداخت بدون CSRF (بازگشت از بانک/درگاه تست)
        $middleware->validateCsrfTokens(except: ['payment/callback']);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'org.context' => \App\Http\Middleware\EnsureOrgContext::class,
            'coffeenet.context' => \App\Http\Middleware\EnsureCoffeenetContext::class,
            'operator.context' => \App\Http\Middleware\EnsureOperatorContext::class,
            'admin.access' => \App\Http\Middleware\AdminSectionAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // فاز ۱۱ — 401 JSON استاندارد برای درخواست مهمانِ API
        // (قبلاً تولید ریدایرکتِ route(login) → 500 می‌شد)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'احراز هویت نشده‌اید؛ توکن معتبر Bearer ارسال کنید.',
                ], 401);
            }

            return null; // مسیر وب: ریدایرکت پیش‌فرض به login سراسری (روت زیر)
        });

        // پیام فارسی برای محدودیت نرخ درخواست API
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);

                return response()->json([
                    'message' => 'درخواست‌های شما بیش از حد مجاز است؛ '.fa_digits($seconds).' ثانیه دیگر تلاش کنید.',
                ], 429, $e->getHeaders());
            }

            return null;
        });
    })->create();
