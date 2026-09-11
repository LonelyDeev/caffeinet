# راهنمای دپلوی و راه‌اندازی پروداکشن — «کافی‌نت آنلاین»

> نسخهٔ سند: ۱.۱۴ (فاز ۲۲ — سرو رسانه از /media + WebP)
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
