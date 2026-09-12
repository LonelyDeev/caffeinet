<?php

namespace App\Providers;

use App\Services\Notifications\NotificationService;
use App\Services\Settings\SettingsService;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsTemplateService;
use App\Support\Gateway;
use App\Support\GatewayUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // سرویس تنظیمات داینامیک (کش‌شده)
        $this->app->singleton(SettingsService::class);

        // مدیریت پیامک — پرووایدر از تنظیمات
        $this->app->singleton(SmsManager::class);

        // قالب‌های پیامک (فاز ۱۰)
        $this->app->singleton(SmsTemplateService::class);

        // پیامک حرکات مشتری (درخواست بازخوردی ۶-۶)
        $this->app->singleton(\App\Services\Sms\CustomerSmsService::class);

        // اعلان‌های درون‌برنامه‌ای (فاز ۱۰)
        $this->app->singleton(NotificationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyTimezone();

        $this->registerGatewayUrlGenerator();
        $this->registerPaginatorPathResolver();
        $this->registerSuperAdminGate();
        $this->registerOtpRateLimiter();
        $this->registerApiRateLimiter();
    }

    /**
     * v29 — منطقهٔ زمانی سامانه از تنظیمات عمومی (general.timezone).
     * پیش‌فرض UTC (رفتار قبلی). در DB در دسترس نبودن/مقدار نامعتبر → بی‌اثر.
     * روی همهٔ تاریخ‌های Carbon (now()) و شمسی‌سازی اثر می‌گذارد؛
     * زمان‌بندی خودکار (schedule) هم با همین منطقه ارزیابی می‌شود.
     */
    protected function applyTimezone(): void
    {
        try {
            $tz = trim((string) app(SettingsService::class)->get('general.timezone', 'UTC'));
        } catch (\Throwable) {
            return;
        }

        if ($tz === '' || ! in_array($tz, timezone_identifiers_list(), true)) {
            return;
        }

        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);
    }

    /**
     * محدودیت نرخ درخواست OTP:
     * حداکثر ۲ در دقیقه برای هر شماره + ۱۰ در ساعت برای هر IP.
     */
    protected function registerOtpRateLimiter(): void
    {
        RateLimiter::for('otp-request', function (Request $request) {
            $mobile = \App\Services\Customer\OtpService::normalizeMobile((string) $request->input('mobile', ''));

            return [
                Limit::perMinute(2)->by('otp:m:'.($mobile ?: 'none')),
                Limit::perHour(10)->by('otp:ip:'.$request->ip()),
            ];
        });
    }

    /**
     * سقف کلی API احرازشده (فاز ۱۱): ۱۲۰ درخواست در دقیقه برای هر کاربر
     * (یا هر IP مهمان) — پوشش پولینگ‌ها با حاشیهٔ امن.
     */
    protected function registerApiRateLimiter(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(120)->by('api:'.$key);
        });
    }

    /**
     * مدیر کل به همه چیز دسترسی دارد.
     * پالیس دسترسی بخش‌های پنل مدیریت برای User ثبت می‌شود (درخواست بازخوردی ۶-۴).
     */
    protected function registerSuperAdminGate(): void
    {
        Gate::before(fn ($user, string $ability) => $user->hasRole('super_admin') ? true : null);

        Gate::policy(\App\Models\User::class, \App\Policies\AdminAccessPolicy::class);
    }

    /**
     * جایگزینی مولد URL پیش‌فرض با نسخهٔ سازگار با گیت‌وی پیش‌نمایش.
     *
     * @see \App\Support\Gateway
     */
    protected function registerGatewayUrlGenerator(): void
    {
        $this->app->singleton('url', function ($app) {
            $routes = $app['router']->getRoutes();

            $app->instance('routes', $routes);

            return new GatewayUrlGenerator(
                $routes,
                $app->rebinding('request', $this->requestRebinder()),
                $app['config']['app.asset_url']
            );
        });

        $this->app->extend('url', function (UrlGenerator $url, $app) {
            $url->setSessionResolver(function () use ($app) {
                return $app['session'] ?? null;
            });

            $url->setKeyResolver(function () use ($app) {
                $config = $app->make('config');

                return [$config->get('app.key'), ...($config->get('app.previous_keys') ?? [])];
            });

            $app->rebinding('routes', function ($app, $routes) {
                $app['url']->setRoutes($routes);
            });

            return $url;
        });
    }

    /**
     * Rebinder درخواست برای مولد URL (مطابق پیاده‌سازی پیش‌فرض لاراول).
     */
    protected function requestRebinder(): \Closure
    {
        return function ($app, $request) {
            $app['url']->setRequest($request);
        };
    }

    /**
     * لینک‌های صفحه‌بندی نیز پارامتر گیت‌وی را حفظ کنند (پیش‌نمایش).
     */
    protected function registerPaginatorPathResolver(): void
    {
        Paginator::currentPathResolver(function () {
            $path = '/'.ltrim(request()->path(), '/');

            return Gateway::append($path);
        });
    }
}
