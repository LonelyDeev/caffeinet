<?php

use App\Http\Controllers\Front\LandingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسیرهای وب (پنل‌ها + صفحه عمومی)
|--------------------------------------------------------------------------
|
| ساختار پوشه‌ای تفکیک‌شده:
|   پنل مدیر کل      → app/Http/Controllers/Back/Admin     → resources/views/back/admin
|   پنل سازمان       → app/Http/Controllers/Back/Org       → resources/views/back/org
|   پنل کافی‌نت      → app/Http/Controllers/Back/Coffeenet  → resources/views/back/coffeenet
|   پنل اپراتور      → app/Http/Controllers/Back/Operator  → resources/views/back/operator
|   API مشتری        → app/Http/Controllers/Api/V1         → routes/api.php
|
*/



Route::get('/', [LandingController::class, 'index'])->name('front.landing');

/* ---------- رسانهٔ عمومی — فاز ۲۲ (رفع 403 تصاویر) ----------
| تصاویر خدمات/اطلاعیه‌ها از storage/app/public مستقیم استریم می‌شوند
| تا سرو تصاویر به symlink باقی‌مانده از storage:link وابسته نباشد
| (روی هاست‌هایی که zip، public/storage را پوشهٔ واقعی باز کرده، 403 می‌داد).
| جزئیات امنیت (لیست سفید پیشوند/پسوند، بدون svg) در MediaController.
*/
Route::get('media/{path}', [\App\Http\Controllers\MediaController::class, 'show'])
    ->where('path', '.*')
    ->middleware('throttle:240,1')
    ->name('media.show');

/* ---------- PWA (فاز ۱۴ — بازطراحی تفکیک‌شده) — مانیفست مستقل هر پنل ----------
| هر بخش «اپ نصب‌شدنی» اختصاصی خودش را دارد؛ نصب از داخل همان پنل انجام
| می‌شود و آیکون نصب‌شده مستقیماً همان پنل را باز می‌کند (نه صفحه فرود):
|   /app/manifest.webmanifest          → اپ مشتریان  (start_url=/app؛ مهمان → ورود، عضو → داشبورد)
|   /admin/manifest.webmanifest        → پنل مدیریت کل
|   /organization/manifest.webmanifest → پنل سازمان
|   /coffeenet/manifest.webmanifest    → پنل کافی‌نت
|   /operator/manifest.webmanifest     → پنل اپراتور
| صفحه فرود (landing) عمداً مانیفست ندارد → قابل نصب نیست.
| مانیفست از روت سرو می‌شود تا در هر وب‌سروری (Apache/Nginx/Caddy/artisan)
| با هدر صحیح application/manifest+json تحویل داده شود.
| آیکون‌ها و sw.js فایل استاتیک public هستند.
*/
Route::get('{panel}/manifest.webmanifest', function (string $panel) {
    $name = (string) config('app.name', 'کافی‌نت آنلاین');

    /* ریشه هر پنل به‌صورت هوشمند عمل می‌کند: مهمان → لاگین همان پنل،
     * کاربر واردشده → داشبورد (یا صفحه انتخاب زمینه) همان پنل. */
    $panels = [
        'app' => [
            'title'       => $name.' — اپ مشتریان',
            'short'       => 'کافینت',
            'description' => 'سفارش خدمات کافی‌نت آنلاین؛ فرم‌ساز پویا، پرداخت آنلاین، پیگیری لحظه‌ای سفارش و چت مستقیم با اپراتور.',
            'start'       => '/app',
            'scope'       => '/',
            'id'          => '/app/',
            'panel_icons' => null,   // اپ مشتری → آیکون برند اصلی
            'orientation' => 'portrait-primary',
            'extras'      => true,   // shortcuts + اسکرین‌شات (فقط اپ مشتری)
        ],
        'admin' => [
            'title'       => $name.' — پنل مدیریت کل',
            'short'       => 'مدیریت کل',
            'description' => 'پنل مدیریت کل کافی‌نت آنلاین؛ داشبورد، مالی، کمیسیون‌ها، کافی‌نت‌ها، اپراتورها و تنظیمات سامانه.',
            'start'       => '/admin',
            'scope'       => '/admin/',
            'id'          => '/admin/',
            'panel_icons' => 'admin',
        ],
        'organization' => [
            'title'       => $name.' — پنل سازمان',
            'short'       => 'سازمان',
            'description' => 'پنل سازمان کافی‌نت آنلاین؛ مدیریت اپراتورها، سفارش‌ها و مالی سازمان.',
            'start'       => '/organization',
            'scope'       => '/organization/',
            'id'          => '/organization/',
            'panel_icons' => 'organization',
        ],
        'coffeenet' => [
            'title'       => $name.' — پنل کافی‌نت',
            'short'       => 'پنل کافی‌نت',
            'description' => 'پنل کافی‌نت آنلاین؛ مدیریت خدمات، سفارش‌ها، اپراتورها و درآمد شعبه.',
            'start'       => '/coffeenet',
            'scope'       => '/coffeenet/',
            'id'          => '/coffeenet/',
            'panel_icons' => 'coffeenet',
        ],
        'operator' => [
            'title'       => $name.' — پنل اپراتور',
            'short'       => 'اپراتور',
            'description' => 'پنل اپراتور کافی‌نت آنلاین؛ صف سفارش‌ها، اجرا و گفتگو با مشتریان.',
            'start'       => '/operator',
            'scope'       => '/operator/',
            'id'          => '/operator/',
            'panel_icons' => 'operator',
        ],
    ];

    abort_unless(isset($panels[$panel]), 404);
    $cfg = $panels[$panel];

    /* آیکون اختصاصی پنل (icons/panels/…) — در نبود فایل‌ها → آیکون برند */
    $usePanelIcons = $cfg['panel_icons'] !== null
        && is_file(public_path('icons/panels/'.$cfg['panel_icons'].'-512.png'));

    $iconUrl = function (int $size) use ($cfg, $usePanelIcons) {
        return $usePanelIcons
            ? url('/icons/panels/'.$cfg['panel_icons'].'-'.$size.'.png')
            : url('/icons/icon-'.$size.'.png');
    };

    $manifest = [
        'id'                     => url($cfg['id']),
        'name'                   => $cfg['title'],
        'short_name'             => $cfg['short'],
        'description'            => $cfg['description'],
        'lang'                   => 'fa',
        'dir'                    => 'rtl',
        // start_url/scope هر دو باید با «/» تمام شوند تا قاعدهٔ «در محدوده بودن»
        // start_url و عدم گسترش ناخواستهٔ scope به کل دامنه رعایت شود.
        'start_url'              => rtrim(url($cfg['start']), '/').'/?source=pwa',
        'scope'                  => rtrim(url($cfg['scope']), '/').'/',
        'display'                => 'standalone',
        'display_override'       => ['standalone', 'minimal-ui'],
        'background_color'       => '#31190e',
        'theme_color'            => '#a8652e',
        'categories'             => ['business', 'productivity', 'shopping'],
        'prefer_related_applications' => false,

        // آیکون‌ها — any + maskable (ترکیب تمام‌صفحه با حاشیه امن)
        'icons' => [
            ['src' => $iconUrl(48),   'sizes' => '48x48',   'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $iconUrl(96),   'sizes' => '96x96',   'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $iconUrl(192),  'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $iconUrl(512),  'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $iconUrl(192),  'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => $iconUrl(512),  'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ];

    if (! empty($cfg['orientation'])) {
        $manifest['orientation'] = $cfg['orientation'];
    }

    // میان‌برهای صفحه اصلی (اندروید لانچر — لمس طولانی آیکون) + اسکرین‌شات‌ها — فقط اپ مشتری
    if (! empty($cfg['extras'])) {
        $manifest['shortcuts'] = [
            [
                'name'       => 'خدمات کافی‌نت',
                'short_name' => 'خدمات',
                'description' => 'کاتالوگ خدمات و فرم سفارش',
                'url'        => url('/app/services?source=pwa-shortcut'),
                'icons'      => [['src' => $iconUrl(96), 'sizes' => '96x96']],
            ],
            [
                'name'       => 'سفارش‌های من',
                'short_name' => 'سفارش‌ها',
                'description' => 'پیگیری سفارش‌ها و گفتگو با اپراتور',
                'url'        => url('/app/orders?source=pwa-shortcut'),
                'icons'      => [['src' => $iconUrl(96), 'sizes' => '96x96']],
            ],
            [
                'name'       => 'پشتیبانی و تیکت',
                'short_name' => 'پشتیبانی',
                'description' => 'تیکت پشتیبانی و پیگیری پاسخ',
                'url'        => url('/app/support?source=pwa-shortcut'),
                'icons'      => [['src' => $iconUrl(96), 'sizes' => '96x96']],
            ],
            [
                'name'       => 'کیف پول',
                'short_name' => 'کیف پول',
                'description' => 'موجودی، واریز و تراکنش‌ها',
                'url'        => url('/app/wallet?source=pwa-shortcut'),
                'icons'      => [['src' => $iconUrl(96), 'sizes' => '96x96']],
            ],
        ];

        $manifest['screenshots'] = [
            ['src' => url('/icons/screenshots/home.png'),  'sizes' => '540x720', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'داشبورد مشتری'],
            ['src' => url('/icons/screenshots/services.png'), 'sizes' => '540x720', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'کاتالوگ خدمات'],
            ['src' => url('/icons/screenshots/order.png'), 'sizes' => '540x720', 'type' => 'image/png', 'form_factor' => 'narrow', 'label' => 'جزئیات سفارش و چت'],
        ];
    }

    return response()->json($manifest)
        ->header('Content-Type', 'application/manifest+json')
        ->header('Cache-Control', 'public, max-age=3600');
})->where('panel', 'app|admin|organization|coffeenet|operator')->name('pwa.manifest');

/* مانیفست قدیمی ریشه → مانیفست اپ مشتری (مهاجرت نصب‌های قبلی، ۳۰۱ دائمی) */
Route::permanentRedirect('manifest.webmanifest', 'app/manifest.webmanifest');

// صفحه آفلاین — بدون نیاز به سشن/لاگین (توسط SW کش می‌شود)
Route::view('offline', 'pwa.offline')->name('pwa.offline');

/* ---------- روت سراسری login (فاز ۱۱ — هاردنینگ) ----------
| ریدایرکتِ احراز هویت ناموفقِ وب به مسیر login پنلِ مربوطه هدایت می‌شود
| (قبلاً route('login') تعریف‌نشده → 500). API مهمان 401 JSON می‌گیرد.
*/
Route::get('login', function (Request $request) {
    $intended = (string) $request->session()->get('url.intended', $request->input('to', ''));

    return match (true) {
        str_starts_with($intended, '/admin') => redirect()->route('admin.login'),
        str_starts_with($intended, '/organization') => redirect()->route('org.login'),
        str_starts_with($intended, '/org') => redirect()->route('org.login'),
        str_starts_with($intended, '/coffeenet') => redirect()->route('coffeenet.login'),
        str_starts_with($intended, '/operator') => redirect()->route('operator.login'),
        default => redirect()->route('admin.login'),
    };
})->name('login');

/* ---------- اپ مشتری (فاز ۵) — Blade + jQuery، داده‌ها از API v1 ---------- */
Route::prefix('app')->name('app.')->group(function () {
    Route::get('/', [App\Http\Controllers\Front\App\PagesController::class, 'index'])->name('index');
    Route::get('auth', [App\Http\Controllers\Front\App\PagesController::class, 'auth'])->name('auth');
    Route::get('home', [App\Http\Controllers\Front\App\PagesController::class, 'home'])->name('home');
    Route::get('services', [App\Http\Controllers\Front\App\PagesController::class, 'services'])->name('services');
    Route::get('service/{service}', [App\Http\Controllers\Front\App\PagesController::class, 'service'])
        ->whereNumber('service')->name('service');
    Route::get('orders', [App\Http\Controllers\Front\App\PagesController::class, 'orders'])->name('orders');
    Route::get('orders/{order}', [App\Http\Controllers\Front\App\PagesController::class, 'orderShow'])
        ->whereNumber('order')->name('orders.show');
    Route::get('wallet', [App\Http\Controllers\Front\App\PagesController::class, 'wallet'])->name('wallet');
    Route::get('profile', [App\Http\Controllers\Front\App\PagesController::class, 'profile'])->name('profile');
    Route::get('profile/edit', [App\Http\Controllers\Front\App\PagesController::class, 'profileEdit'])->name('profile.edit');

    /* پشتیبانی و تیکت‌ها (فاز ۱۰) */
    Route::get('support', [App\Http\Controllers\Front\App\PagesController::class, 'support'])->name('support');
    Route::get('support/{ticket}', [App\Http\Controllers\Front\App\PagesController::class, 'supportShow'])
        ->whereNumber('ticket')->name('support.show');
});

/* ---------- پرداخت آنلاین (درگاه) ---------- */
Route::get('payment/start/{payment}', [App\Http\Controllers\Front\App\PaymentController::class, 'start'])
    ->whereNumber('payment')->name('payment.start');
Route::match(['get', 'post'], 'payment/callback', [App\Http\Controllers\Front\App\PaymentController::class, 'callback'])
    ->name('payment.callback');

/* ---------- دانلود مدارک (لینک موقت امضاشده) ---------- */
Route::get('files/order/{file}', [App\Http\Controllers\Front\App\FilesController::class, 'download'])
    ->whereNumber('file')->name('files.order');

/* ---------- فایل‌های گفتگو (فاز ۷ — لینک موقت امضاشده) ---------- */
Route::get('files/chat/{message}', [App\Http\Controllers\Front\App\ChatFilesController::class, 'download'])
    ->whereNumber('message')->name('files.chat');

/* ---------- پیوست تیکت پشتیبانی (فاز ۱۰ — لینک موقت امضاشده) ---------- */
Route::get('files/ticket/{message}', [App\Http\Controllers\Front\App\TicketFilesController::class, 'download'])
    ->whereNumber('message')->name('files.ticket');

/* ================== پنل مدیریت کل ================== */
Route::prefix('admin')->name('admin.')->group(function () {

    Route::get('login', [App\Http\Controllers\Back\Admin\AuthController::class, 'showLogin'])
        ->name('login');
    Route::post('login', [App\Http\Controllers\Back\Admin\AuthController::class, 'login'])
        ->name('login.attempt');

    Route::middleware(['admin.access'])->group(function () {
        Route::post('logout', [App\Http\Controllers\Back\Admin\AuthController::class, 'logout'])
            ->name('logout');

        Route::get('/', [App\Http\Controllers\Back\Admin\DashboardController::class, 'index'])
            ->name('dashboard');

        /* مدیریت مدیران (AJAX) */
        Route::get('admins', [App\Http\Controllers\Back\Admin\AdminsController::class, 'index'])
            ->name('admins.index');
        Route::get('admins/data', [App\Http\Controllers\Back\Admin\AdminsController::class, 'data'])
            ->name('admins.data');
        Route::post('admins', [App\Http\Controllers\Back\Admin\AdminsController::class, 'store'])
            ->name('admins.store');
        Route::put('admins/{user}', [App\Http\Controllers\Back\Admin\AdminsController::class, 'update'])
            ->name('admins.update');
        Route::patch('admins/{user}/toggle', [App\Http\Controllers\Back\Admin\AdminsController::class, 'toggle'])
            ->name('admins.toggle');

        /* تنظیمات (AJAX) */
        Route::get('settings', [App\Http\Controllers\Back\Admin\SettingsController::class, 'edit'])
            ->name('settings.edit');
        Route::put('settings', [App\Http\Controllers\Back\Admin\SettingsController::class, 'save'])
            ->name('settings.save');
        Route::put('settings/referral', [App\Http\Controllers\Back\Admin\SettingsController::class, 'saveReferral'])
            ->name('settings.referral');
        Route::post('settings/test-sms', [App\Http\Controllers\Back\Admin\SettingsController::class, 'testSms'])
            ->name('settings.test-sms');
        Route::post('settings/test-pusher', [App\Http\Controllers\Back\Admin\SettingsController::class, 'testPusher'])
            ->name('settings.test-pusher');

        /* اعلان‌ها (v25) — صدای اعلان + پوش فایربیس */
        Route::post('settings/notification/sound', [App\Http\Controllers\Back\Admin\SettingsController::class, 'uploadSound'])
            ->name('settings.notif-sound.upload');
        Route::delete('settings/notification/sound', [App\Http\Controllers\Back\Admin\SettingsController::class, 'deleteSound'])
            ->name('settings.notif-sound.delete');
        Route::post('settings/notification/push-credentials', [App\Http\Controllers\Back\Admin\SettingsController::class, 'uploadFirebaseCredentials'])
            ->name('settings.notif-firebase.upload');
        Route::delete('settings/notification/push-credentials', [App\Http\Controllers\Back\Admin\SettingsController::class, 'deleteFirebaseCredentials'])
            ->name('settings.notif-firebase.delete');
        Route::post('settings/test-push', [App\Http\Controllers\Back\Admin\SettingsController::class, 'testPush'])
            ->name('settings.test-push');
        /* v26 — بازتولید کلیدهای VAPID وب‌پوش داخلی */
        Route::post('settings/notification/webpush-keys', [App\Http\Controllers\Back\Admin\SettingsController::class, 'regenerateWebpushKeys'])
            ->name('settings.notif-webpush.regenerate');

        /* اطلاعیه‌های پنل (فاز ۱۵ — مشترک) */
        Route::get('announcements/pending', [App\Http\Controllers\Back\Shared\PanelAnnouncementsController::class, 'pending'])
            ->name('announcements.pending');
        Route::post('announcements/{announcement}/read', [App\Http\Controllers\Back\Shared\PanelAnnouncementsController::class, 'read'])
            ->whereNumber('announcement')->name('announcements.read');

        /* اطلاعیه‌های سامانه (فاز ۱۵ — AJAX) */
        Route::get('announcements', [App\Http\Controllers\Back\Admin\AnnouncementsController::class, 'index'])
            ->name('announcements.index');
        Route::get('announcements/data', [App\Http\Controllers\Back\Admin\AnnouncementsController::class, 'data'])
            ->name('announcements.data');
        Route::get('announcements/{announcement}', [App\Http\Controllers\Back\Admin\AnnouncementsController::class, 'show'])
            ->whereNumber('announcement')->name('announcements.show');
        Route::post('announcements', [App\Http\Controllers\Back\Admin\AnnouncementsController::class, 'store'])
            ->name('announcements.store');
        Route::put('announcements/{announcement}', [App\Http\Controllers\Back\Admin\AnnouncementsController::class, 'update'])
            ->whereNumber('announcement')->name('announcements.update');
        Route::patch('announcements/{announcement}/toggle', [App\Http\Controllers\Back\Admin\AnnouncementsController::class, 'toggle'])
            ->whereNumber('announcement')->name('announcements.toggle');
        Route::delete('announcements/{announcement}', [App\Http\Controllers\Back\Admin\AnnouncementsController::class, 'destroy'])
            ->whereNumber('announcement')->name('announcements.destroy');


        /* لاگ فعالیت (AJAX) */
        Route::get('audit-logs', [App\Http\Controllers\Back\Admin\AuditLogsController::class, 'index'])
            ->name('audit.index');
        Route::get('audit-logs/data', [App\Http\Controllers\Back\Admin\AuditLogsController::class, 'data'])
            ->name('audit.data');

        /* جغرافیا (مشترک پنل‌ها — سلکت آبشاری) */
        Route::get('geo/cities', [App\Http\Controllers\Back\GeoController::class, 'cities'])
            ->name('geo.cities');

        /* مدیریت سازمان‌ها (AJAX) */
        Route::get('organizations', [App\Http\Controllers\Back\Admin\OrganizationsController::class, 'index'])
            ->name('organizations.index');
        Route::get('organizations/data', [App\Http\Controllers\Back\Admin\OrganizationsController::class, 'data'])
            ->name('organizations.data');
        Route::post('organizations', [App\Http\Controllers\Back\Admin\OrganizationsController::class, 'store'])
            ->name('organizations.store');
        Route::put('organizations/{organization}', [App\Http\Controllers\Back\Admin\OrganizationsController::class, 'update'])
            ->name('organizations.update');
        Route::patch('organizations/{organization}/status', [App\Http\Controllers\Back\Admin\OrganizationsController::class, 'status'])
            ->name('organizations.status');
        Route::get('organizations/{organization}', [App\Http\Controllers\Back\Admin\OrganizationsController::class, 'show'])
            ->name('organizations.show');

        /* مدیریت کافی‌نت‌ها (AJAX) */
        Route::get('coffeenets', [App\Http\Controllers\Back\Admin\CoffeenetsController::class, 'index'])
            ->name('coffeenets.index');
        Route::get('coffeenets/data', [App\Http\Controllers\Back\Admin\CoffeenetsController::class, 'data'])
            ->name('coffeenets.data');
        Route::post('coffeenets', [App\Http\Controllers\Back\Admin\CoffeenetsController::class, 'store'])
            ->name('coffeenets.store');
        Route::put('coffeenets/{coffeenet}', [App\Http\Controllers\Back\Admin\CoffeenetsController::class, 'update'])
            ->name('coffeenets.update');
        Route::patch('coffeenets/{coffeenet}/status', [App\Http\Controllers\Back\Admin\CoffeenetsController::class, 'status'])
            ->name('coffeenets.status');
        Route::get('coffeenets/{coffeenet}', [App\Http\Controllers\Back\Admin\CoffeenetsController::class, 'show'])
            ->name('coffeenets.show');

        /* روند کاری کافی‌نت (درخواست بازخوردی ۶-۲ — چارت بازه‌ای) */
        Route::get('coffeenets/{coffeenet}/trend', [App\Http\Controllers\Back\Admin\CoffeenetsController::class, 'trend'])
            ->name('coffeenets.trend');

        /* دید کلان مدیر به همه کارکنان/اپراتورهای شبکه (فاز ۱۰) */
        Route::get('operators', [App\Http\Controllers\Back\Admin\OperatorsController::class, 'index'])
            ->name('operators.index');
        Route::get('operators/data', [App\Http\Controllers\Back\Admin\OperatorsController::class, 'data'])
            ->name('operators.data');
        Route::post('operators', [App\Http\Controllers\Back\Admin\OperatorsController::class, 'store'])
            ->name('operators.store');

        /* پروفایل کامل اپراتور + روند کاری (درخواست بازخوردی ۶-۱) */
        Route::get('operators/{assignment}', [App\Http\Controllers\Back\Admin\OperatorsController::class, 'show'])
            ->whereNumber('assignment')->name('operators.show');
        Route::get('operators/{assignment}/trend', [App\Http\Controllers\Back\Admin\OperatorsController::class, 'trend'])
            ->whereNumber('assignment')->name('operators.trend');

        /* ویرایش کامل کارمند توسط مدیر کل (اطلاعات ورود + انتقال کافی‌نت) */
        Route::put('operators/{assignment}', [App\Http\Controllers\Back\Admin\OperatorsController::class, 'update'])
            ->whereNumber('assignment')->name('operators.update');
        Route::get('operators/{assignment}/salary', [App\Http\Controllers\Back\Admin\OperatorsController::class, 'salary'])
            ->whereNumber('assignment')->name('operators.salary');

        /* گردش‌کار تایید کارمندان (ساخته‌شده توسط مدیر کافی‌نت) */
        Route::patch('operators/{assignment}/approve', [App\Http\Controllers\Back\Admin\OperatorsController::class, 'approve'])
            ->whereNumber('assignment')->name('operators.approve');
        Route::patch('operators/{assignment}/reject', [App\Http\Controllers\Back\Admin\OperatorsController::class, 'reject'])
            ->whereNumber('assignment')->name('operators.reject');

        /* مدیریت برداشت‌ها (AJAX) */
        Route::get('withdrawals', [App\Http\Controllers\Back\Admin\WithdrawalsController::class, 'index'])
            ->name('withdrawals.index');
        Route::get('withdrawals/data', [App\Http\Controllers\Back\Admin\WithdrawalsController::class, 'data'])
            ->name('withdrawals.data');
        Route::patch('withdrawals/{withdrawal}/review', [App\Http\Controllers\Back\Admin\WithdrawalsController::class, 'review'])
            ->name('withdrawals.review');

        /* ---------- فاز ۸ — مالی و کمیسیون ---------- */

        /* قواعد کمیسیون (سراسری + اختصاصی خدمت) */
        Route::get('commissions', [App\Http\Controllers\Back\Admin\CommissionRulesController::class, 'index'])
            ->name('commissions.index');
        Route::put('commissions/global', [App\Http\Controllers\Back\Admin\CommissionRulesController::class, 'saveGlobal'])
            ->name('commissions.global');
        Route::get('commissions/data', [App\Http\Controllers\Back\Admin\CommissionRulesController::class, 'data'])
            ->name('commissions.data');
        Route::post('commissions', [App\Http\Controllers\Back\Admin\CommissionRulesController::class, 'store'])
            ->name('commissions.store');
        Route::put('commissions/{commission}', [App\Http\Controllers\Back\Admin\CommissionRulesController::class, 'update'])
            ->whereNumber('commission')->name('commissions.update');
        Route::patch('commissions/{commission}/toggle', [App\Http\Controllers\Back\Admin\CommissionRulesController::class, 'toggle'])
            ->whereNumber('commission')->name('commissions.toggle');
        Route::delete('commissions/{commission}', [App\Http\Controllers\Back\Admin\CommissionRulesController::class, 'destroy'])
            ->whereNumber('commission')->name('commissions.destroy');

        /* تسویه‌های کمیسیون */
        Route::get('settlements', [App\Http\Controllers\Back\Admin\SettlementsController::class, 'index'])
            ->name('settlements.index');
        Route::get('settlements/data', [App\Http\Controllers\Back\Admin\SettlementsController::class, 'data'])
            ->name('settlements.data');
        Route::get('settlements/order/{order}', [App\Http\Controllers\Back\Admin\SettlementsController::class, 'order'])
            ->whereNumber('order')->name('settlements.order');
        Route::post('settlements/order/{order}/retry', [App\Http\Controllers\Back\Admin\SettlementsController::class, 'retry'])
            ->whereNumber('order')->name('settlements.retry');

        /* گزارش مالی جامع */
        Route::get('finance', [App\Http\Controllers\Back\Admin\FinanceController::class, 'index'])
            ->name('finance.index');
        Route::get('finance/data', [App\Http\Controllers\Back\Admin\FinanceController::class, 'data'])
            ->name('finance.data');
        Route::get('finance/export', [App\Http\Controllers\Back\Admin\FinanceController::class, 'export'])
            ->name('finance.export');

        /* ---------- فاز ۹ — گزارش تحلیلی و نمودارها ---------- */
        Route::get('analytics', [App\Http\Controllers\Back\Admin\AnalyticsController::class, 'index'])
            ->name('analytics.index');
        Route::get('analytics/data', [App\Http\Controllers\Back\Admin\AnalyticsController::class, 'data'])
            ->name('analytics.data');
        Route::get('analytics/export', [App\Http\Controllers\Back\Admin\AnalyticsController::class, 'export'])
            ->name('analytics.export');

        /* ---------- فاز ۱۰ — تیکت‌های پشتیبانی ---------- */
        Route::get('tickets', [App\Http\Controllers\Back\Admin\TicketsController::class, 'index'])
            ->name('tickets.index');
        Route::get('tickets/data', [App\Http\Controllers\Back\Admin\TicketsController::class, 'data'])
            ->name('tickets.data');
        Route::get('tickets/{ticket}', [App\Http\Controllers\Back\Admin\TicketsController::class, 'show'])
            ->whereNumber('ticket')->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [App\Http\Controllers\Back\Admin\TicketsController::class, 'reply'])
            ->whereNumber('ticket')->name('tickets.reply');
        Route::get('tickets/{ticket}/messages', [App\Http\Controllers\Back\Admin\TicketsController::class, 'messages'])
            ->whereNumber('ticket')->name('tickets.messages');
        Route::patch('tickets/{ticket}/status', [App\Http\Controllers\Back\Admin\TicketsController::class, 'status'])
            ->whereNumber('ticket')->name('tickets.status');
        Route::patch('tickets/{ticket}/priority', [App\Http\Controllers\Back\Admin\TicketsController::class, 'priority'])
            ->whereNumber('ticket')->name('tickets.priority');
        Route::patch('tickets/{ticket}/assign', [App\Http\Controllers\Back\Admin\TicketsController::class, 'assign'])
            ->whereNumber('ticket')->name('tickets.assign');

        /* ---------- فاز ۱۰ — قالب‌های پیامک ---------- */
        Route::get('sms-templates', [App\Http\Controllers\Back\Admin\SmsTemplatesController::class, 'index'])
            ->name('sms-templates.index');
        Route::get('sms-templates/data', [App\Http\Controllers\Back\Admin\SmsTemplatesController::class, 'data'])
            ->name('sms-templates.data');
        Route::put('sms-templates/{template}', [App\Http\Controllers\Back\Admin\SmsTemplatesController::class, 'update'])
            ->whereNumber('template')->name('sms-templates.update');
        Route::patch('sms-templates/{template}/toggle', [App\Http\Controllers\Back\Admin\SmsTemplatesController::class, 'toggle'])
            ->whereNumber('template')->name('sms-templates.toggle');
        Route::post('sms-templates/{template}/test', [App\Http\Controllers\Back\Admin\SmsTemplatesController::class, 'test'])
            ->whereNumber('template')->name('sms-templates.test');

        /* ---------- v16 — لاگ پیامک‌های ارسال‌شده ---------- */
        Route::get('sms-logs', [App\Http\Controllers\Back\Admin\SmsLogsController::class, 'index'])
            ->name('sms-logs.index');
        Route::get('sms-logs/data', [App\Http\Controllers\Back\Admin\SmsLogsController::class, 'data'])
            ->name('sms-logs.data');

        /* ---------- فاز ۱۰ — زنگ اعلان ---------- */
        Route::get('notifications/badge', [App\Http\Controllers\Back\NotificationsController::class, 'badge'])
            ->name('notifications.badge');
        Route::get('notifications/data', [App\Http\Controllers\Back\NotificationsController::class, 'data'])
            ->name('notifications.data');
        Route::post('notifications/read', [App\Http\Controllers\Back\NotificationsController::class, 'read'])
            ->name('notifications.read');

        /* نوتیف دستگاه (Web Push / FCM) — v25 */
        Route::post('push/token', [App\Http\Controllers\Back\PushTokenController::class, 'store'])
            ->name('push.token');
        Route::delete('push/token', [App\Http\Controllers\Back\PushTokenController::class, 'destroy'])
            ->name('push.token.destroy');

        /* ---------- فاز ۱۳ — آموزش پنل (راهنماهای مدیر کل) ---------- */
        Route::get('guide', [App\Http\Controllers\Back\Shared\GuideController::class, 'index'])
            ->name('guide.index')->defaults('guide_role', 'super_admin');
        Route::get('guide/{slug}', [App\Http\Controllers\Back\Shared\GuideController::class, 'show'])
            ->name('guide.show')->defaults('guide_role', 'super_admin');

        /* ---------- فاز ۱۱ — وضعیت سیستم و مستندات API ---------- */
        Route::get('system', [App\Http\Controllers\Back\Admin\SystemController::class, 'index'])
            ->name('system.index');
        Route::post('system/cleanup', [App\Http\Controllers\Back\Admin\SystemController::class, 'runCleanup'])
            ->name('system.cleanup');
        Route::post('system/retention', [App\Http\Controllers\Back\Admin\SystemController::class, 'saveRetention'])
            ->name('system.retention');
        Route::post('system/encrypt', [App\Http\Controllers\Back\Admin\SystemController::class, 'encryptFiles'])
            ->name('system.encrypt');
        Route::get('api-docs', [App\Http\Controllers\Back\Admin\ApiDocsController::class, 'index'])
            ->name('api-docs.index');

        /* دسته‌بندی‌ها (AJAX) */
        Route::get('service-categories', [App\Http\Controllers\Back\Admin\ServiceCategoriesController::class, 'index'])
            ->name('service-categories.index');
        Route::get('service-categories/data', [App\Http\Controllers\Back\Admin\ServiceCategoriesController::class, 'data'])
            ->name('service-categories.data');
        Route::post('service-categories', [App\Http\Controllers\Back\Admin\ServiceCategoriesController::class, 'store'])
            ->name('service-categories.store');
        Route::put('service-categories/{category}', [App\Http\Controllers\Back\Admin\ServiceCategoriesController::class, 'update'])
            ->name('service-categories.update');
        Route::patch('service-categories/{category}/toggle', [App\Http\Controllers\Back\Admin\ServiceCategoriesController::class, 'toggle'])
            ->name('service-categories.toggle');
        Route::delete('service-categories/{category}', [App\Http\Controllers\Back\Admin\ServiceCategoriesController::class, 'destroy'])
            ->name('service-categories.destroy');

        /* خدمات (AJAX + فرم‌ساز) */
        Route::get('services', [App\Http\Controllers\Back\Admin\ServicesController::class, 'index'])
            ->name('services.index');
        Route::get('services/data', [App\Http\Controllers\Back\Admin\ServicesController::class, 'data'])
            ->name('services.data');
        Route::get('services/create', [App\Http\Controllers\Back\Admin\ServicesController::class, 'create'])
            ->name('services.create');
        Route::post('services', [App\Http\Controllers\Back\Admin\ServicesController::class, 'store'])
            ->name('services.store');
        Route::get('services/{service}/edit', [App\Http\Controllers\Back\Admin\ServicesController::class, 'edit'])
            ->name('services.edit');
        Route::put('services/{service}', [App\Http\Controllers\Back\Admin\ServicesController::class, 'update'])
            ->name('services.update');
        Route::patch('services/{service}/toggle', [App\Http\Controllers\Back\Admin\ServicesController::class, 'toggle'])
            ->name('services.toggle');
        Route::delete('services/{service}', [App\Http\Controllers\Back\Admin\ServicesController::class, 'destroy'])
            ->name('services.destroy');
        Route::get('services/{service}/versions', [App\Http\Controllers\Back\Admin\ServicesController::class, 'versions'])
            ->name('services.versions');

        /* ---------- مدیریت سفارش‌ها و موتور تخصیص (فاز ۶) ---------- */
        Route::get('orders', [App\Http\Controllers\Back\Admin\OrdersController::class, 'index'])
            ->name('orders.index');
        Route::get('orders/data', [App\Http\Controllers\Back\Admin\OrdersController::class, 'data'])
            ->name('orders.data');
        Route::get('orders/counts', [App\Http\Controllers\Back\Admin\OrdersController::class, 'counts'])
            ->name('orders.counts');
        Route::get('orders/coffeenets', [App\Http\Controllers\Back\Admin\OrdersController::class, 'coffeenets'])
            ->name('orders.coffeenets');
        Route::get('orders/{order}', [App\Http\Controllers\Back\Admin\OrdersController::class, 'show'])
            ->whereNumber('order')->name('orders.show');
        Route::get('orders/{order}/view', [App\Http\Controllers\Back\Admin\OrdersController::class, 'view'])
            ->whereNumber('order')->name('orders.view');
        Route::get('orders/{order}/operators', [App\Http\Controllers\Back\Admin\OrdersController::class, 'operators'])
            ->whereNumber('order')->name('orders.operators');
        Route::patch('orders/{order}/assign', [App\Http\Controllers\Back\Admin\OrdersController::class, 'assign'])
            ->whereNumber('order')->name('orders.assign');
        Route::patch('orders/{order}/operator', [App\Http\Controllers\Back\Admin\OrdersController::class, 'assignOperator'])
            ->whereNumber('order')->name('orders.operator');
        Route::post('orders/{order}/rebroadcast', [App\Http\Controllers\Back\Admin\OrdersController::class, 'rebroadcast'])
            ->whereNumber('order')->name('orders.rebroadcast');
        Route::patch('orders/{order}/status', [App\Http\Controllers\Back\Admin\OrdersController::class, 'updateStatus'])
            ->whereNumber('order')->name('orders.status');
        Route::patch('orders/{order}/cancel', [App\Http\Controllers\Back\Admin\OrdersController::class, 'cancel'])
            ->whereNumber('order')->name('orders.cancel');

        /* ---------- گفتگوهای سفارش (مدیر کل — درخواست بازخوردی) ---------- */
        Route::get('chats', [App\Http\Controllers\Back\Shared\OrderChatController::class, 'adminIndex'])
            ->name('chats.index');
        Route::get('chats/data', [App\Http\Controllers\Back\Shared\OrderChatController::class, 'adminData'])
            ->name('chats.data');
        Route::get('orders/{order}/chat', [App\Http\Controllers\Back\Shared\OrderChatController::class, 'adminShow'])
            ->whereNumber('order')->name('orders.chat');
        Route::get('orders/{order}/chat/data', [App\Http\Controllers\Back\Shared\OrderChatController::class, 'adminMessages'])
            ->whereNumber('order')->name('orders.chat.data');
        Route::post('orders/{order}/chat/send', [App\Http\Controllers\Back\Shared\OrderChatController::class, 'adminSend'])
            ->whereNumber('order')->name('orders.chat.send');

        /* ---------- مدیریت مشتریان (درخواست بازخوردی — لیست/پروفایل/ویرایش/بن) ---------- */
        Route::get('customers', [App\Http\Controllers\Back\Admin\CustomersController::class, 'index'])
            ->name('customers.index');
        Route::get('customers/data', [App\Http\Controllers\Back\Admin\CustomersController::class, 'data'])
            ->name('customers.data');
        Route::get('customers/{customer}', [App\Http\Controllers\Back\Admin\CustomersController::class, 'show'])
            ->whereNumber('customer')->name('customers.show');
        Route::get('customers/{customer}/trend', [App\Http\Controllers\Back\Admin\CustomersController::class, 'trend'])
            ->whereNumber('customer')->name('customers.trend');
        Route::put('customers/{customer}', [App\Http\Controllers\Back\Admin\CustomersController::class, 'update'])
            ->whereNumber('customer')->name('customers.update');
        Route::patch('customers/{customer}/ban', [App\Http\Controllers\Back\Admin\CustomersController::class, 'ban'])
            ->whereNumber('customer')->name('customers.ban');
        Route::patch('customers/{customer}/unban', [App\Http\Controllers\Back\Admin\CustomersController::class, 'unban'])
            ->whereNumber('customer')->name('customers.unban');
        Route::post('customers/{customer}/wallet', [App\Http\Controllers\Back\Admin\CustomersController::class, 'wallet'])
            ->whereNumber('customer')->name('customers.wallet');
    });
});

/* ---------- پنل سازمان (فاز ۲) ---------- */
Route::prefix('organization')->name('org.')->group(function () {

    Route::get('login', [App\Http\Controllers\Back\Org\AuthController::class, 'showLogin'])
        ->name('login');
    Route::post('login', [App\Http\Controllers\Back\Org\AuthController::class, 'login'])
        ->name('login.attempt');

    Route::middleware(['role:org_manager', 'org.context'])->group(function () {
        Route::post('logout', [App\Http\Controllers\Back\Org\AuthController::class, 'logout'])
            ->name('logout');

        Route::get('choose', [App\Http\Controllers\Back\Org\AuthController::class, 'choose'])
            ->name('choose');
        Route::post('select', [App\Http\Controllers\Back\Org\AuthController::class, 'select'])
            ->name('select');

        Route::get('/', [App\Http\Controllers\Back\Org\DashboardController::class, 'index'])
            ->name('dashboard');

        /* کیف پول */
        Route::get('wallet', [App\Http\Controllers\Back\Org\WalletController::class, 'index'])
            ->name('wallet.index');
        Route::get('wallet/data', [App\Http\Controllers\Back\Org\WalletController::class, 'data'])
            ->name('wallet.data');

        /* برداشت‌ها */
        Route::get('withdrawals', [App\Http\Controllers\Back\Org\WithdrawalsController::class, 'index'])
            ->name('withdrawals.index');
        Route::get('withdrawals/data', [App\Http\Controllers\Back\Org\WithdrawalsController::class, 'data'])
            ->name('withdrawals.data');
        Route::post('withdrawals', [App\Http\Controllers\Back\Org\WithdrawalsController::class, 'store'])
            ->name('withdrawals.store');

        /* کافی‌نت‌های زیرمجموعه + معرفی جدید */
        Route::get('coffeenets', [App\Http\Controllers\Back\Org\CoffeenetsController::class, 'index'])
            ->name('coffeenets.index');
        Route::get('coffeenets/data', [App\Http\Controllers\Back\Org\CoffeenetsController::class, 'data'])
            ->name('coffeenets.data');
        Route::post('coffeenets', [App\Http\Controllers\Back\Org\CoffeenetsController::class, 'store'])
            ->name('coffeenets.store');

        /* جغرافیا (سلکت آبشاری) */
        Route::get('geo/cities', [App\Http\Controllers\Back\GeoController::class, 'cities'])
            ->name('geo.cities');

        /* ---------- فاز ۱۰ — زنگ اعلان (سازمان) ---------- */
        Route::get('notifications/badge', [App\Http\Controllers\Back\NotificationsController::class, 'badge'])
            ->name('notifications.badge');
        Route::get('notifications/data', [App\Http\Controllers\Back\NotificationsController::class, 'data'])
            ->name('notifications.data');
        Route::post('notifications/read', [App\Http\Controllers\Back\NotificationsController::class, 'read'])
            ->name('notifications.read');

        /* نوتیف دستگاه (Web Push / FCM) — v25 */
        Route::post('push/token', [App\Http\Controllers\Back\PushTokenController::class, 'store'])
            ->name('push.token');
        Route::delete('push/token', [App\Http\Controllers\Back\PushTokenController::class, 'destroy'])
            ->name('push.token.destroy');

        /* اطلاعیه‌های پنل (فاز ۱۵ — مشترک) */
        Route::get('announcements/pending', [App\Http\Controllers\Back\Shared\PanelAnnouncementsController::class, 'pending'])
            ->name('announcements.pending');
        Route::post('announcements/{announcement}/read', [App\Http\Controllers\Back\Shared\PanelAnnouncementsController::class, 'read'])
            ->whereNumber('announcement')->name('announcements.read');
        /* ---------- فاز ۱۳ — آموزش پنل (راهنماهای سازمان) ---------- */
        /* ---------- فاز ۱۳ — آموزش پنل (راهنماهای سازمان) ---------- */
        Route::get('guide', [App\Http\Controllers\Back\Shared\GuideController::class, 'index'])
            ->name('guide.index')->defaults('guide_role', 'organization');
        Route::get('guide/{slug}', [App\Http\Controllers\Back\Shared\GuideController::class, 'show'])
            ->name('guide.show')->defaults('guide_role', 'organization');
    });
});

/* ---------- پنل کافی‌نت (فاز ۳) ---------- */
Route::prefix('coffeenet')->name('coffeenet.')->group(function () {

    Route::get('login', [App\Http\Controllers\Back\Coffeenet\AuthController::class, 'showLogin'])
        ->name('login');
    Route::post('login', [App\Http\Controllers\Back\Coffeenet\AuthController::class, 'login'])
        ->name('login.attempt');

    Route::middleware(['role:coffeenet_manager', 'coffeenet.context'])->group(function () {
        Route::post('logout', [App\Http\Controllers\Back\Coffeenet\AuthController::class, 'logout'])
            ->name('logout');

        /* مدیران چند-کافی‌نتی: صفحه و اندپوینت انتخاب زمینه */
        Route::get('choose', [App\Http\Controllers\Back\Coffeenet\AuthController::class, 'choose'])
            ->name('choose');
        Route::post('select', [App\Http\Controllers\Back\Coffeenet\AuthController::class, 'select'])
            ->name('select');

        /* ریشه پنل → داشبورد کافی‌نت جاری session */
        Route::get('/', function (Illuminate\Http\Request $request) {
            $current = $request->attributes->get('current_coffeenet');

            return redirect()->route('coffeenet.dashboard', ['coffeenet' => $current]);
        })->name('home');

        Route::get('{coffeenet}/dashboard', [App\Http\Controllers\Back\Coffeenet\DashboardController::class, 'index'])
            ->name('dashboard');

        /* کارمندان (AJAX) */
        Route::get('{coffeenet}/staff', [App\Http\Controllers\Back\Coffeenet\StaffController::class, 'index'])
            ->name('staff.index');
        Route::get('{coffeenet}/staff/data', [App\Http\Controllers\Back\Coffeenet\StaffController::class, 'data'])
            ->name('staff.data');
        Route::post('{coffeenet}/staff', [App\Http\Controllers\Back\Coffeenet\StaffController::class, 'store'])
            ->name('staff.store');
        Route::put('{coffeenet}/staff/{assignment}', [App\Http\Controllers\Back\Coffeenet\StaffController::class, 'update'])
            ->name('staff.update');
        Route::patch('{coffeenet}/staff/{assignment}/toggle', [App\Http\Controllers\Back\Coffeenet\StaffController::class, 'toggle'])
            ->name('staff.toggle');

        /* حقوق و دستمزد (AJAX) */
        Route::get('{coffeenet}/salaries', [App\Http\Controllers\Back\Coffeenet\SalariesController::class, 'index'])
            ->name('salaries.index');
        Route::get('{coffeenet}/salaries/data', [App\Http\Controllers\Back\Coffeenet\SalariesController::class, 'data'])
            ->name('salaries.data');
        Route::post('{coffeenet}/salaries', [App\Http\Controllers\Back\Coffeenet\SalariesController::class, 'store'])
            ->name('salaries.store');

        /* تنظیمات کافی‌نت (AJAX) */
        Route::get('{coffeenet}/settings', [App\Http\Controllers\Back\Coffeenet\SettingsController::class, 'index'])
            ->name('settings.index');
        Route::put('{coffeenet}/settings', [App\Http\Controllers\Back\Coffeenet\SettingsController::class, 'update'])
            ->name('settings.update');
        Route::put('{coffeenet}/settings/password', [App\Http\Controllers\Back\Coffeenet\SettingsController::class, 'password'])
            ->name('settings.password');

        /* سفارش‌ها و پخش زنده (فاز ۶) */
        Route::get('{coffeenet}/orders', [App\Http\Controllers\Back\Coffeenet\OrdersController::class, 'index'])
            ->name('orders.index');
        Route::get('{coffeenet}/orders/broadcast/data', [App\Http\Controllers\Back\Coffeenet\OrdersController::class, 'broadcastData'])
            ->name('orders.broadcast.data');
        Route::get('{coffeenet}/orders/data', [App\Http\Controllers\Back\Coffeenet\OrdersController::class, 'data'])
            ->name('orders.data');
        Route::post('{coffeenet}/orders/{order}/accept', [App\Http\Controllers\Back\Coffeenet\OrdersController::class, 'accept'])
            ->whereNumber('order')->name('orders.accept');
        Route::get('{coffeenet}/orders/operators', [App\Http\Controllers\Back\Coffeenet\OrdersController::class, 'operators'])
            ->name('orders.operators');
        Route::patch('{coffeenet}/orders/{order}/operator', [App\Http\Controllers\Back\Coffeenet\OrdersController::class, 'assignOperator'])
            ->whereNumber('order')->name('orders.operator');
        Route::patch('{coffeenet}/orders/{order}/status', [App\Http\Controllers\Back\Coffeenet\OrdersController::class, 'updateStatus'])
            ->whereNumber('order')->name('orders.status');

        /* ---------- گفتگوهای سفارش (مدیر کافی‌نت — درخواست بازخوردی) ---------- */
        Route::get('{coffeenet}/chats', [App\Http\Controllers\Back\Shared\OrderChatController::class, 'coffeenetIndex'])
            ->name('chats.index');
        Route::get('{coffeenet}/chats/data', [App\Http\Controllers\Back\Shared\OrderChatController::class, 'coffeenetData'])
            ->name('chats.data');
        Route::get('{coffeenet}/orders/{order}/chat', [App\Http\Controllers\Back\Shared\OrderChatController::class, 'coffeenetShow'])
            ->whereNumber('order')->name('orders.chat');
        Route::get('{coffeenet}/orders/{order}/chat/data', [App\Http\Controllers\Back\Shared\OrderChatController::class, 'coffeenetMessages'])
            ->whereNumber('order')->name('orders.chat.data');
        Route::post('{coffeenet}/orders/{order}/chat/send', [App\Http\Controllers\Back\Shared\OrderChatController::class, 'coffeenetSend'])
            ->whereNumber('order')->name('orders.chat.send');

        /* جغرافیا (سلکت آبشاری) */
        Route::get('geo/cities', [App\Http\Controllers\Back\GeoController::class, 'cities'])
            ->name('geo.cities');

        /* ---------- فاز ۸ — کیف پول و برداشت کافی‌نت ---------- */
        Route::get('{coffeenet}/wallet', [App\Http\Controllers\Back\Coffeenet\WalletController::class, 'index'])
            ->name('wallet.index');
        Route::get('{coffeenet}/wallet/data', [App\Http\Controllers\Back\Coffeenet\WalletController::class, 'data'])
            ->name('wallet.data');

        Route::get('{coffeenet}/withdrawals', [App\Http\Controllers\Back\Coffeenet\WithdrawalsController::class, 'index'])
            ->name('withdrawals.index');
        Route::get('{coffeenet}/withdrawals/data', [App\Http\Controllers\Back\Coffeenet\WithdrawalsController::class, 'data'])
            ->name('withdrawals.data');
        Route::post('{coffeenet}/withdrawals', [App\Http\Controllers\Back\Coffeenet\WithdrawalsController::class, 'store'])
            ->name('withdrawals.store');

        /* ---------- فاز ۱۰ — تیکت‌های پشتیبانی کافی‌نت ---------- */
        Route::get('{coffeenet}/tickets', [App\Http\Controllers\Back\Coffeenet\TicketsController::class, 'index'])
            ->name('tickets.index');
        Route::get('{coffeenet}/tickets/data', [App\Http\Controllers\Back\Coffeenet\TicketsController::class, 'data'])
            ->name('tickets.data');
        Route::get('{coffeenet}/tickets/{ticket}', [App\Http\Controllers\Back\Coffeenet\TicketsController::class, 'show'])
            ->whereNumber('ticket')->name('tickets.show');
        Route::post('{coffeenet}/tickets/{ticket}/reply', [App\Http\Controllers\Back\Coffeenet\TicketsController::class, 'reply'])
            ->whereNumber('ticket')->name('tickets.reply');
        Route::get('{coffeenet}/tickets/{ticket}/messages', [App\Http\Controllers\Back\Coffeenet\TicketsController::class, 'messages'])
            ->whereNumber('ticket')->name('tickets.messages');
        Route::patch('{coffeenet}/tickets/{ticket}/status', [App\Http\Controllers\Back\Coffeenet\TicketsController::class, 'status'])
            ->whereNumber('ticket')->name('tickets.status');

        /* ---------- فاز ۱۰ — زنگ اعلان (کافی‌نت) ---------- */
        Route::get('notifications/badge', [App\Http\Controllers\Back\NotificationsController::class, 'badge'])
            ->name('notifications.badge');
        Route::get('notifications/data', [App\Http\Controllers\Back\NotificationsController::class, 'data'])
            ->name('notifications.data');
        Route::post('notifications/read', [App\Http\Controllers\Back\NotificationsController::class, 'read'])
            ->name('notifications.read');

        /* نوتیف دستگاه (Web Push / FCM) — v25 */
        Route::post('push/token', [App\Http\Controllers\Back\PushTokenController::class, 'store'])
            ->name('push.token');
        Route::delete('push/token', [App\Http\Controllers\Back\PushTokenController::class, 'destroy'])
            ->name('push.token.destroy');

        /* اطلاعیه‌های پنل (فاز ۱۵ — مشترک) */
        Route::get('announcements/pending', [App\Http\Controllers\Back\Shared\PanelAnnouncementsController::class, 'pending'])
            ->name('announcements.pending');
        Route::post('announcements/{announcement}/read', [App\Http\Controllers\Back\Shared\PanelAnnouncementsController::class, 'read'])
            ->whereNumber('announcement')->name('announcements.read');
        /* ---------- فاز ۱۳ — آموزش پنل (راهنماهای مدیر کافی‌نت) ---------- */
        /* ---------- فاز ۱۳ — آموزش پنل (راهنماهای مدیر کافی‌نت) ---------- */
        Route::get('{coffeenet}/guide', [App\Http\Controllers\Back\Shared\GuideController::class, 'index'])
            ->whereNumber('coffeenet')->name('guide.index')->defaults('guide_role', 'coffeenet');
        Route::get('{coffeenet}/guide/{slug}', [App\Http\Controllers\Back\Shared\GuideController::class, 'show'])
            ->whereNumber('coffeenet')->name('guide.show')->defaults('guide_role', 'coffeenet');
    });
});

/* ---------- پنل اپراتور (فاز ۷ — زیرساخت + چت) ---------- */
Route::prefix('operator')->name('operator.')->group(function () {

    Route::get('login', [App\Http\Controllers\Back\Operator\AuthController::class, 'showLogin'])
        ->name('login');
    Route::post('login', [App\Http\Controllers\Back\Operator\AuthController::class, 'login'])
        ->name('login.attempt');

    Route::middleware(['role:operator', 'operator.context'])->group(function () {
        Route::post('logout', [App\Http\Controllers\Back\Operator\AuthController::class, 'logout'])
            ->name('logout');

        /* ریشه پنل → داشبورد (زمینه از session) */
        Route::get('/', function () {
            return redirect()->route('operator.dashboard');
        })->name('home');

        Route::get('dashboard', [App\Http\Controllers\Back\Operator\DashboardController::class, 'index'])
            ->name('dashboard');

        /* سفارش‌ها (AJAX — محدودهٔ دید طبق دسترسی‌ها) */
        Route::get('orders', [App\Http\Controllers\Back\Operator\OrdersController::class, 'index'])
            ->name('orders.index');
        Route::get('orders/data', [App\Http\Controllers\Back\Operator\OrdersController::class, 'data'])
            ->name('orders.data');

        /* فاز ۱۱ — صندوق درخواست‌های مشتری (پذیرش مستقیم اپراتور) */
        Route::get('requests', [App\Http\Controllers\Back\Operator\RequestsController::class, 'index'])
            ->name('requests.index');
        Route::get('requests/data', [App\Http\Controllers\Back\Operator\RequestsController::class, 'data'])
            ->name('requests.data');
        Route::get('requests/badge', [App\Http\Controllers\Back\Operator\RequestsController::class, 'badge'])
            ->name('requests.badge');
        Route::post('requests/{order}/accept', [App\Http\Controllers\Back\Operator\RequestsController::class, 'accept'])
            ->whereNumber('order')->name('requests.accept');

        /* تغییر وضعیت سریع سفارش (فاز ۷) */
        Route::patch('orders/{order}/status', [App\Http\Controllers\Back\Operator\OrdersController::class, 'updateStatus'])
            ->whereNumber('order')->name('orders.status');

        /* گفتگوها (فاز ۷ — چت تلگرام‌گونه) */
        Route::get('chat', [App\Http\Controllers\Back\Operator\ChatController::class, 'index'])
            ->name('chat.index');
        Route::get('chat/data', [App\Http\Controllers\Back\Operator\ChatController::class, 'data'])
            ->name('chat.data');
        Route::get('chat/badge', [App\Http\Controllers\Back\Operator\ChatController::class, 'badge'])
            ->name('chat.badge');
        Route::get('orders/{order}/chat', [App\Http\Controllers\Back\Operator\ChatController::class, 'show'])
            ->whereNumber('order')->name('orders.chat');
        Route::get('orders/{order}/chat/data', [App\Http\Controllers\Back\Operator\ChatController::class, 'messages'])
            ->whereNumber('order')->name('orders.chat.data');
        Route::post('orders/{order}/chat/send', [App\Http\Controllers\Back\Operator\ChatController::class, 'send'])
            ->whereNumber('order')->name('orders.chat.send');

        /* ---------- فاز ۸ — درآمد و کیف پول اپراتور ---------- */
        Route::get('earnings', [App\Http\Controllers\Back\Operator\EarningsController::class, 'index'])
            ->name('earnings.index');
        Route::get('earnings/data', [App\Http\Controllers\Back\Operator\EarningsController::class, 'data'])
            ->name('earnings.data');
        Route::get('earnings/transactions', [App\Http\Controllers\Back\Operator\EarningsController::class, 'transactions'])
            ->name('earnings.transactions');

        /* ---------- فاز ۱۰ — تیکت‌های پشتیبانی اپراتور ---------- */
        Route::get('tickets', [App\Http\Controllers\Back\Operator\TicketsController::class, 'index'])
            ->name('tickets.index');
        Route::get('tickets/data', [App\Http\Controllers\Back\Operator\TicketsController::class, 'data'])
            ->name('tickets.data');
        Route::get('tickets/{ticket}', [App\Http\Controllers\Back\Operator\TicketsController::class, 'show'])
            ->whereNumber('ticket')->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [App\Http\Controllers\Back\Operator\TicketsController::class, 'reply'])
            ->whereNumber('ticket')->name('tickets.reply');
        Route::get('tickets/{ticket}/messages', [App\Http\Controllers\Back\Operator\TicketsController::class, 'messages'])
            ->whereNumber('ticket')->name('tickets.messages');

        /* ---------- فاز ۱۰ — زنگ اعلان (اپراتور) ---------- */
        Route::get('notifications/badge', [App\Http\Controllers\Back\NotificationsController::class, 'badge'])
            ->name('notifications.badge');
        Route::get('notifications/data', [App\Http\Controllers\Back\NotificationsController::class, 'data'])
            ->name('notifications.data');
        Route::post('notifications/read', [App\Http\Controllers\Back\NotificationsController::class, 'read'])
            ->name('notifications.read');

        /* نوتیف دستگاه (Web Push / FCM) — v25 */
        Route::post('push/token', [App\Http\Controllers\Back\PushTokenController::class, 'store'])
            ->name('push.token');
        Route::delete('push/token', [App\Http\Controllers\Back\PushTokenController::class, 'destroy'])
            ->name('push.token.destroy');

        /* اطلاعیه‌های پنل (فاز ۱۵ — مشترک) */
        Route::get('announcements/pending', [App\Http\Controllers\Back\Shared\PanelAnnouncementsController::class, 'pending'])
            ->name('announcements.pending');
        Route::post('announcements/{announcement}/read', [App\Http\Controllers\Back\Shared\PanelAnnouncementsController::class, 'read'])
            ->whereNumber('announcement')->name('announcements.read');

        /* ---------- فاز ۱۳ — آموزش پنل (راهنماهای اپراتور) ---------- */
        Route::get('guide', [App\Http\Controllers\Back\Shared\GuideController::class, 'index'])
            ->name('guide.index')->defaults('guide_role', 'operator');
        Route::get('guide/{slug}', [App\Http\Controllers\Back\Shared\GuideController::class, 'show'])
            ->name('guide.show')->defaults('guide_role', 'operator');
    });
});
