# راهنمای دپلوی و راه‌اندازی پروداکشن — «کافی‌نت آنلاین»

> نسخهٔ سند: ۱.۲۰ (v29 — آستانهٔ آفلاین انتخابی ثانیه‌ای + تایم‌زون + حذف دوره‌ای لاگ‌ها + پیوست تیکت موبایل)
> این سند برای سرور لینوکسی با PHP-FPM + Nginx نوشته شده است.

---

## ۱) پیش‌نیازها

| نیازمندی | نسخه | توضیح |
|---|---|---|
| PHP | ۸.۳+ (توصیه: ۸.۵) | افزونه‌های لازم پایین‌تر |
| Composer | ۲.x | نصب پکیج‌ها (بدون اسکریپت Node — پروژه صفر-Node است) |
| MySQL/MariaDB | ۸+ / ۱۰.۶+ | تصمیم مالک: پروداکشن MySQL |
| وب‌سرور | Nginx/Apache | با HTTPS (Let's Encrypt) |

**افزونه‌های PHP:** `openssl pdo pdo_mysql mbstring fileinfo gd curl iconv tokenizer xml ctype session` (+ `sqlite3` فقط برای محیط تست)

> پروژه از Tailwind CLI مستقل و Chart.js vendored استفاده می‌کند — **هیچ `npm install` / `npm run build` لازم نیست**؛ `public/assets` کامل و آمادهٔ سرو است.

---

## ۲) نصب

```bash
# ۱. دریافت سورس
git clone <repo> caffeinet && cd caffeinet

# ۲. نصب پکیج‌ها
composer install --no-dev --optimize-autoloader --no-interaction

# ۳. تنظیمات محیط
cp .env.example .env
php artisan key:generate

# ۴. کلید مستقل رمزنگاری فایل‌ها (توصیهٔ امنیتی فاز ۱۱)
php -r "file_put_contents('.env', PHP_EOL.'FILE_ENCRYPTION_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL, FILE_APPEND);"

# ۵. پیکربندی دیتابیس (.env)
#    DB_CONNECTION=mysql و DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD

# ۶. مایگریشن + دادهٔ اولیه (استان/شهر، تنظیمات، قالب پیامک، نقش‌ها، کاتالوگ ۸۱ خدمت)
php artisan migrate --force
php artisan db:seed --force

# ۷. کش پیکربندی
php artisan config:cache
php artisan route:cache
```

> **مهم:** اگر بعداً `.env` را تغییر دادید، `php artisan config:clear` سپس `config:cache` دوباره اجرا کنید.

> **تصاویر (فاز ۲۲):** سرو تصاویر خدمات/اطلاعیه‌ها از روت **`/media/{path}`** انجام
> می‌شود (MediaController) و **به symlink `public/storage` نیازی ندارد** — یعنی روی
> هر هاستی (حتی جایی که storage:link قابل اجرا نیست) کار می‌کند. seeder کاتالوگ
> تصاویر WebP را از `database/seeders/assets/services` داخل `storage/app/public/services`
> کپی می‌کند و اگر به‌جای symlink پوشهٔ واقعی قدیمی (خطای zip های قبلی) موجود باشد،
> خودکار پاک و symlink نسبی درست می‌سازد. اجرای `storage:link` اختیاری است.

---

## ۳) مجوزها

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
# دیتابیس SQLite فقط محیط dev است؛ در پروداکشن MySQL استفاده کنید.
```

فایل‌های خصوصی مشتری در `storage/app/private` با **AES-256-GCM** رمزنگاری می‌شوند — پشتیبان این پوشه + `.env` (کلید) با هم معنا دارند؛ بدون کلید، بکاپ فایل‌ها خوانا نیست.

---

## ۴) زمان‌بندی (باید همیشه فعال باشد)

دو رخداد زمان‌بندی‌شده:

| کرون | کار |
|---|---|
| `* * * * *` | تعیین‌تکلیف سفارش‌های پخشِ منقضی (ری‌پخش/صف + پیامک) |
| `30 3 * * *` | پاکسازی دوره‌ای (OTP/اعلان/لاگ‌ها + روتیشن لاگ) |

```bash
# گزینهٔ ا (کرون سیستم — روی سرور مشترک):
(crontab -l 2>/dev/null; echo "* * * * * cd /path/to/caffeinet && php artisan schedule:run >> /dev/null 2>&1") | crontab -

# گزینهٔ ب (پایدارتر — supervisor):
[program:caffeinet-schedule]
command=php /path/to/caffeinet/artisan schedule:work
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/caffeinet/storage/logs/scheduler.log
stopwaitsecs=3600
```

---

## ۵) چک‌لیست امنیت پروداکشن

همهٔ این موارد در **پنل → وضعیت سیستم (`/admin/system`)** به‌صورت زنده قابل بازبینی‌اند:

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false` (در غیر این صورت stack trace فاش می‌شود)
- [ ] `APP_KEY` تولید شده (فراموش نشود؛ توکن‌ها/نشست‌ها به آن وابسته‌اند)
- [ ] `FILE_ENCRYPTION_KEY` مستقل تولید و در جای امن (vault) نگهداری شود
- [ ] HTTPS فعال و هدر `X-Forwarded-Proto` از پروکسی عبور کند (اپ به همهٔ پروکسی‌ها trust دارد)
- [ ] `php artisan files:encrypt` اجرا شده (بک‌فیل فایل‌های قدیمی → پوشش ۱۰۰٪)
- [ ] `chmod 600 .env`
- [ ] پورت MySQL فقط localhost؛ دسترسی ریموت بسته شود
- [ ] فایروال: فقط ۸۰/۴۴۳ باز؛ ۹۰۰۰ (php-fpm) و دیتابیس نباید expose باشند
- [ ] بکاپ شبانه: `database` + `storage/app/private` + `.env` (رمزنگاری‌شده خارج از سرور)
- [ ] مانیتورینگ سلامت: `GET /up` باید ۲۰۰ بدهد (هر ۱ دقیقه)
- [ ] لاگ‌ها: `LOG_CHANNEL=daily` + `LOG_DAYS=14` (روتیشن حجمی هم داخل `system:cleanup` هست)

### هدرهای امنیتی (فعال به‌صورت پیش‌فرض — فاز ۱۱)

`X-Content-Type-Options`، `X-Frame-Options`، `Referrer-Policy`، `Permissions-Policy` و **CSP** با هش اسکریپت ضد-FOUC تم.
اگر اسکریپت درون‌خطی جدید اضافه کردید، هش `sha256-…` آن را به `app/Http/Middleware/SecurityHeaders.php` بیفزایید.

### محدودیت نرخ (فعال)

| لایه | سقف |
|---|---|
| OTP | ۲/دقیقه هر شماره + ۱۰/ساعت هر IP |
| API احرازشده | ۱۲۰/دقیقه هر کاربر |
| ورود پنل‌ها | ۵ تلاش ناموفق → ۶۰ ثانیه تعلیق |
| تیکت/پیام تیکت | ۱۰ و ۲۰ در دقیقه |

---

## ۶) Nginx (نمونه)

```nginx
server {
    listen 443 ssl http2;
    server_name example.ir;

    root /var/www/caffeinet/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/example.ir/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.ir/privkey.pem;

    client_max_body_size 20m;   # پیوست تیکت 15MB + سربار

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) { deny all; }
    location ~* ^/(storage/logs|app/private) { deny all; }  # مسیر داخلی
}

server {
    listen 80;
    server_name example.ir;
    return 301 https://$host$request_uri;
}
```

---

## ۷) درگاه پرداخت و پیامک (پس از نصب)

هیچ کلیدی در `.env` لازم نیست — همه از **پنل مدیریت → تنظیمات**:

1. **پیامک:** پرووایدر (log/kavenegar/fraasms) + کلید API و خط فرستنده — تست زنده از صفحهٔ قالب‌های پیامک.
2. **پرداخت:** درایور shetabit (zarinpal/…) + مرچنت‌کد.
3. **کمیسیون:** قواعد سراسری/اختصاصی از «قواعد کمیسیون».

---

## ۸) بکاپ و بازیابی

```bash
# بکاپ شبانه (cron 04:30)
0 4 * * * /path/to/backup.sh
#!/usr/bin/env bash
mysqldump --single-transaction -u caffeinet -p"$DB_PASS" caffeinet | gzip > /backups/db-$(date +\%F).sql.gz
tar -czf /backups/files-$(date +\%F).tar.gz -C /path/to/caffeinet storage/app/private
tar -czf /backups/env-$(date +\%F).tar.gz -C /path/to/caffeinet .env
# چرخش ۳۰ روزه
find /backups -mtime +30 -delete
```

بازیابی: سورس + `composer install` → بازیابی `.env` + دیتابیس + `storage/app/private` → `migrate --force` → `config:cache`.
فایل‌های رمزنگاری‌شده با همان `FILE_ENCRYPTION_KEY` بازیابی می‌شوند — کلید را جدا از بکاپ نگه دارید.

---

## ۹) ارتقا (آپدیت نسخه)

```bash
cd /path/to/caffeinet
php artisan down            # صفحهٔ نگهداری
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force   # idempotent — کاتالوگ + تصاویر WebP همگام می‌شوند
php artisan config:cache && php artisan route:cache
php artisan files:encrypt   # اگر فایل خام جدیدی هست
php artisan up
```

> **آپدیت از نسخه‌های قبل از v22:** اگر تصاویر قبلاً 403/404 می‌دادند، علت
> پوشهٔ واقعی `public/storage` (به‌جای symlink) در zip های قدیمی بود؛
> seed جدید آن را خودکار تعمیر می‌کند و URL ها به `/media/...` مهاجرت می‌کنند
> (فایل‌های آپلودی قبلی در همان `storage/app/public` می‌مانند و بدون تغییر کار می‌کنند).

> **v23 — تغییرات اپ مشتری:** (۱) حالت شب/روز با دکمهٔ خورشید/ماه در هدر اپ
> (کلید ذخیرهٔ `caffeinet-theme` مشترک با پنل‌ها؛ انتخاب اول از تم سیستم‌عامل)؛
> (۲) لغو سفارش از سمت مشتری حالا **دلیل الزامی** دارد (API: `reason` min 5)؛
> (۳) صفحهٔ پروفایل بازطراحی شد (هرو + آمار سفارش‌ها از `GET /me` → `orders_stats`).
> Client های خارجی که قبلاً `POST /orders/{id}/cancel` را بدون reason صدا می‌زدند
> از این پس پاسخ 422 می‌گیرند — reason را ارسال کنند.

> **v24 — تغییرات اپ مشتری:** (۱) ویجت موجودی کیف پول از هدر حذف شد
> (با مبالغ بزرگ چیدمان هدر به‌هم می‌ریخت؛ دسترسی از ناوبری پایین و کارت کیف پول صفحهٔ پروفایل)
> و در نتیجه فراخوانی `GET /wallet` در هر صفحه هم حذف شد؛
> (۲) در شیت شارژ، مبلغ انتخابی/دستی با جداکنندهٔ هزارگان زیر ورودی پیش‌نمایش می‌شود؛
> (۳) صفحهٔ پروفایل به «نمای کاربر» (هرو + آمار + منوی حساب) و صفحهٔ جدید `/app/profile/edit`
> (فرم ویرایش) تفکیک شد؛
> (۴) ثبت‌نام اولیه مستقیم به `/app/profile/edit?new=1` هدایت می‌شود و
> **تمام صفحات/عملیات اپ نیازمند پروفایل تکمیل‌اند**: فرانت (ریدایرکت خودکار) + بک‌اند
> (ثبت سفارش و شارژ از قبل؛ اکنون **ثبت/پاسخ تیکت** هم — پیام 422 «ابتدا اطلاعات
> پروفایل خود را کامل کنید.»). Client های خارجی تیکت‌زن بدون پروفایل کامل از این پس 422 می‌گیرند.

> **v25 — سامانهٔ اطلاع‌رسانی سه‌لایه:**
> (۱) **صدا**: اعلان جدید در پنل‌ها (مدیر/کافی‌نت/اپراتور/سازمان — نه اپ مشتری) با صدا
> اعلام می‌شود؛ صدای پیش‌فرض `public/assets/sounds/notify.mp3` یا فایل سفارشی از
> پنل تنظیمات → «اعلان‌ها» (تیک «صدای پیش‌فرض» ↔ آپلودر mp3/wav/ogg/m4a تا ۲MB).
> (۲) **نوتیف دستگاه (FCM)**: وقتی کاربر (مشتری یا پرسنل) آنلاین نیست، اعلان با
> Firebase Cloud Messaging به گوشی/ویندوز می‌رسد (PWA باید نصب/اجازه‌دار باشد).
> راه‌اندازی: تنظیمات → اعلان‌ها → پرووایدر Firebase + ۴ فیلد عمومی + Service Account JSON؛
> کاربران با دکمهٔ «فعال‌سازی نوتیف دستگاه» (زیر زنگ اعلان) توکن ثبت می‌کنند
> (API جدید: `POST/DELETE /api/v1/push/token`). توکن‌ها در جدول `push_tokens` (مهاجرت v25).
> (۳) **پیامک‌های رویدادی** (تنظیمات → پیامک → رویدادها): انتقال به کافی‌نت/اپراتور آفلاین،
> واریز حقوق/تسویه، یادآوری «درخواست بی‌پذیرش» (هر ۵ دقیقه با دستور
> `orders:notify-unaccepted` زمان‌بندی‌شده — ستون `orders.unaccepted_notified_at`) و
> قطع/وصل پیامک پاسخ تیکت (قالب‌های جدید در مرکز پیامک: `notify.*`).
> حضور کاربران با `users.last_seen_at` (میدل‌ور UpdateLastSeen) ردیابی می‌شود.
> همچنین عنوان/متن همهٔ اعلان‌ها به قالب‌های «رویدادی» با کلید اختصاصی تبدیل شد
> (هر بخش عنوان و متن خودش را دارد؛ برای پوش و اعلان مشترک است).
> لندینگ: بخش خدمات فقط **دسته‌بندی‌ها** را نمایش می‌دهد (کارت خدمت‌ها برای کاهش اسکرول موبایل حذف شد).
>
> **دپلوی v25 روی نصب موجود:** `php artisan migrate --force` (۳ مهاجرت:
> last_seen_at، unaccepted_notified_at، push_tokens) + `php artisan db:seed --force`
> (کلیدهای تنظیمات اعلان + قالب‌های notify.* پیامک). PHP باید به‌روی اینترنت
> برای FCM/OAuth گوگل دسترسی داشته باشد (درون ایران معمولاً نیاز به پروکسی سرور دارد).

> **v26 — نوتیف دستگاه سه‌سرویسی + انتخاب مدیر:**
> تنظیمات → «اعلان‌ها و پوش» → «سرویس نوتیف دستگاه» حالا **چهار حالت** دارد:
> (۱) **پیش‌فرض (وب‌پوش داخلی)** — پیشنهادی: کلیدهای VAPID با اولین ذخیره خودکار
> ساخته می‌شوند؛ سرور شما پیام رمزنگاری‌شده (RFC 8291/8292 — پیاده‌سازی خالص PHP،
> بدون هیچ وابستگی) مستقیم به سرویس پوش مرورگر (FCM/Mozilla/Apple) می‌فرستد؛
> بدون ثبت‌نام در سرویس بیرونی و بدون محدودیت تعداد. (۲) **Pusher Beams**
> (instance id + primary key از پنل پوشر؛ انتشار با Bearer به interest `user-{id}`).
> (۳) **Firebase (FCM)** — مثل v25. (۴) **خاموش**.
> جریان کلاینت (هر ۵ لایه): دکمهٔ «فعال‌سازی نوتیف دستگاه» زیر زنگ/شیت اعلان →
> push-client.js بر اساس سرویس فعال اشتراک می‌سازد (VAPID / Beams SDK / FCM SDK —
> همگی vendor محلی) → ثبت در `push_tokens` (ستون‌های جدید provider/p256dh/auth).
> «حالت پیش‌فرض» روی ویندوز/اندروید/کروم/ادج/فایرفاکس + iOS 16.4+ PWA نصب‌شده کار می‌کند.
>
> **دپلوی v26 روی نصب موجود:** `php artisan migrate --force` (۱ مهاجرت:
> extend_push_tokens_for_multi_provider) — seed لازم نیست. اگر از فایربیس به
> «پیش‌فرض» مهاجرت می‌کنید کاربران باید یک‌بار دوباره دکمهٔ فعال‌سازی بزنند
> (اشتراک با کلید VAPID تازه ساخته می‌شود؛ فرایند خودکار است چون اجازه قبلاً داده شده).

> **v28 — حذف نرم/دائم + رفع ۵ باگ:**
> (۱) **حذف نرم‌افزاری** در ۷ بخش پنل مدیریت کل (مشتریان/کافی‌net‌ها/کارکنان/
> مدیران/سازمان‌ها/تیکت‌ها/سفارش‌ها): دکمهٔ «حذف» در هر ردیف → پیام تأیید با
> **هشدار وابستگی‌ها** → انتقال به «حذف‌شده‌ها» (دکمهٔ سربرگ هر صفحه) با
> «بازگردانی» و «حذف دائم» (فایل‌ها و گفتگوهای وابسته پاک می‌شوند).
> آبشارها: حذف مدیر کافی‌net ⇒ حذف کافی‌net (و بالعکس)؛ حذف سازمان ⇒
> کافی‌net‌های زیرمجموعه مستقل می‌شوند؛ حذف مشتری ⇒ سفارش‌ها/تیکت‌هایش؛
> حذف سفارش ⇒ تصاویر و گفتگو. کاربر حذف‌شده فوراً از همهٔ ورودها خارج می‌شود.
> (۲) **کافی‌net‌های معرفی‌شده توسط سازمان** حالا اطلاعات ورود (کاربر مدیر)
> در ویرایش ساخته می‌شود — ریشهٔ نرسیدن اعلان‌ها به این کافی‌net‌ها.
> (۳) واگذاری سفارش به اپراتور حالا به **مشتری** هم اعلان می‌دهد.
> (۴) گفتگوی تیکت در پنل‌ها: پیام‌های خودِ کاربر سمت راست.
> (۵) منوی «فازهای بعدی» پنل سازمان/کافی‌net حذف شد؛ چک‌باکس خام ساعت کاری مخفی.
>
> **دپلوی v28 روی نصب موجود:** `php artisan migrate --force` (۲ مهاجرت:
> add_soft_deletes_to_core_entities + add_soft_deletes_to_staff_assignments)
> — seed لازم نیست؛ داده‌ای تغییری نمی‌کند (فقط ستون deleted_at اضافه می‌شود).

> **v29 — آستانهٔ آفلاین انتخابی + تایم‌زون + حذف دوره‌ای لاگ‌ها:**
> (۱) **آستانهٔ «آفلاین»** (تنظیمات ← اعلان‌ها و پوش) حالا انتخابی است: سوییچ روشن →
> ورودی **ثانیه** (پیش‌فرض ۱۸۰ = همان ۳ دقیقهٔ قبلی)؛ سوییچ خاموش → **لحظه‌ای**
> (بلافاصله بعد از آخرین درخواست، کاربر آفلاین = پوش/پیامک آفلاین حتی با پنل باز).
> مقدار قدیمی `offline_minutes` خودکار به ثانیه مهاجرت می‌کند.
> (۲) **منطقهٔ زمانی سامانه** (تنظیمات ← عمومی): انتخاب از فهرست (پیش‌فرض UTC =
> رفتار قبلی؛ برای ایران Asia/Tehran). همهٔ تاریخ‌ها، شمسی‌سازی و زمان‌بندی‌های
> خودکار (۰۳:۳۰ پاکسازی و…) با همین منطقه ارزیابی می‌شوند.
> (۳) **حذف دوره‌ای لاگ‌ها**: کارت «حذف دوره‌ای» روی صفحات «لاگ پیامک‌ها» و
> «لاگ فعالیت» — نگهداشت (روز، مثل ۲۰) ذخیره می‌کنید، قدیمی‌تر از آن هر شب
> ۰۳:۳۰ خودکار حذف می‌شود (از قدیمی‌ترین) و دکمهٔ «پاکسازی قدیمی‌ها الان»
> همان‌جا اجرا می‌کند (`system:cleanup --scope=…`، فقط همان لاگ).
> (۴) **پیوست تیکت در اپ مشتری**: فایل انتخاب‌شده حالا چیپ مشخص زیر جعبهٔ متن
> نشان داده می‌شود (نام + حجم فارسی + دکمهٔ حذف) — قبلاً روی موبایل پیدا نبود.
> (۵) دارک‌مود تیکت: بکگراند اضافی پشت پیام مشتری حذف شد.
>
> **دپلوی v29 روی نصب موجود:** `php artisan migrate --force` (۲ مهاجرت:
> add_offline_threshold_settings + add_timezone_setting) — seed لازم نیست.
> نکته: `schedule:work` را بعد از دپلوی یک‌بار ری‌استارت کنید تا منطقهٔ زمانی
> جدید و کلیدهای پاکسازی را ببیند.

---

## ۱۰) عیب‌یابی سریع

| علامت | بررسی |
|---|---|
| سفارش‌ها در پخش می‌مانند | `schedule:work` فعال است؟ (پنل → وضعیت سیستم) |
| فایل دانلود نمی‌شود/«رمزگشایی ناموفق» | `FILE_ENCRYPTION_KEY` تغییر کرده؟ (گزارش در laravel.log) |
| ۴۲۹ زیاد | سقف API در `app/Providers/AppServiceProvider.php` |
| خطای CSP در کنسول | هش اسکریپت جدید را به SecurityHeaders اضافه کنید |
| تصویر ۴۰۳/۴۰۴ می‌دهد | از روت `/media/...` استفاده می‌شود؟ URL های قدیمی `/storage` به symlink وابسته‌اند؛ `db:seed` دوباره اجرا کنید (تعمیر خودکار symlink) |
| لاگ حجیم | `system:cleanup` + `LOG_DAYS` |
