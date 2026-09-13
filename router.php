<?php
/**
 * روتر سرور توسعه — فایل استاتیک موجود را خودِ php -S بدهد؛ بقیه به لاراول.
 * (همان رفتار artisan serve ولی بدون استریپ‌شدن محیط PHPRC/LD_LIBRARY_PATH)
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

if ($uri !== '/' && $uri !== '') {
    $file = __DIR__ . '/public' . $uri;
    if (is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext !== 'php' && $ext !== 'htaccess') {
            return false; // php -S فایل را مستقیم سرو می‌کند
        }
    }
}

require __DIR__ . '/public/index.php';
