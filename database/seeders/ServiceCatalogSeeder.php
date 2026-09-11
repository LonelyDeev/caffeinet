<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceCost;
use App\Models\ServiceFormField;

/**
 * کاتالوگ پیش‌فرض دسته‌بندی‌ها و خدمات کافی‌نت آنلاین (v22 — WebP + روت /media)
 * -----------------------------------------------------------------
 * منبع: ویدیوی ارسالی کاربر (منوی خدمات یک سامانهٔ خدمات الکترونیک)
 * + نرمال‌سازی نام‌ها به نام واقعی سامانه‌های ایرانی.
 *
 * • ۱۸ دستهٔ اصلی + ~۸۰ خدمت با قیمت، زمان تقریبی، فرم داینامیک و کارمزد
 * • تصویر هر خدمت: WebP برند (1200×400، قهوه‌ای تیره + گلیف lucide)
 *   از assets آمادهٔ shipping در database/seeders/assets/services کپی
 *   و در storage/app/public/services ذخیره می‌شود — فاز ۲۲: دیگر SVG
 *   تولید نمی‌شود (SVG در آپلود/سرو مسدود است) و سرو تصاویر از روت
 *   /media انجام می‌شود (بدون وابستگی به symlink)
 * • idempotent — اجرای چندباره فقط به‌روزرسانی می‌کند
 */
class ServiceCatalogSeeder extends Seeder
{
    /* =============================================================
     |  قالب فیلدهای فرم (فرم‌ساز)
     * ============================================================= */
    private array $fieldTemplates = [
        'national_code' => ['field_type' => 'national_code', 'label' => 'کد ملی متقاضی', 'name' => 'national_code', 'placeholder' => 'کد ملی ۱۰ رقمی', 'is_required' => true],
        'mobile' => ['field_type' => 'mobile', 'label' => 'شماره موبایل', 'name' => 'mobile', 'placeholder' => '۰۹۱۲ ××× ××××', 'is_required' => true],
        'full_name' => ['field_type' => 'text', 'label' => 'نام و نام خانوادگی', 'name' => 'full_name', 'placeholder' => 'نام کامل (مطابق کارت ملی)', 'is_required' => true],
        'plate' => ['field_type' => 'text', 'label' => 'شماره پلاک یا شاسی', 'name' => 'plate_no', 'placeholder' => 'مثال: ۱۲ ب ۳۴۵ ایران ۲۲'],
        'bill_id' => ['field_type' => 'text', 'label' => 'شناسه اشتراک / قبض', 'name' => 'bill_id', 'placeholder' => 'شناسه روی قبض (۸ تا ۱۲ رقم)'],
        'tracking' => ['field_type' => 'text', 'label' => 'شماره پیگیری / رهگیری', 'name' => 'tracking_no', 'placeholder' => 'کد پیگیری (معمولاً ۱۰ رقم)'],
        'details' => ['field_type' => 'textarea', 'label' => 'توضیحات تکمیلی', 'name' => 'notes', 'placeholder' => 'هر نکته‌ای که اپراتور باید بداند...'],
        'delivery' => ['field_type' => 'select', 'label' => 'روش دریافت نتیجه', 'name' => 'delivery_method', 'options' => ['تحویل حضوری', 'ارسال پستی', 'ارسال به ایمیل']],
    ];

    /* =============================================================
     |  کاتالوگ: ۱۸ دسته + ۸۰ خدمت
     |  (name, slug, glyph, price, time, upload, featured, desc,
     |   fields[], fee[title, amount])
     * ============================================================= */
    private function catalog(): array
    {
        return [
            ['name' => 'خدمات خودرو و رانندگی', 'icon' => '🚗', 'sort' => 10,
             'description' => 'استعلام‌ها، جرائم و مدارک خودرو و گواهینامه',
             'services' => [
                ['استعلام خلافی خودرو و پرداخت جرائم', 'car-fines-inquiry', 'search', 30000, 30, false, true, 'استعلام آنی خلافی خودرو از سامانه راهور و پرداخت جرائم (طرح ترافیک، زوج و فرد، دوربین‌ها).', ['national_code', 'plate'], ['کارمزد سامانه راهور', 8000]],
                ['استعلام اعتبار و نمره منفی گواهینامه', 'license-status-inquiry', 'id', 25000, 20, false, false, 'مشاهده اعتبار گواهینامه، نمره منفی باقی‌مانده و تاریخ انقضا.', ['national_code'], null],
                ['تقاضای صدور یا تمدید گواهینامه', 'license-renewal', 'id', 120000, 240, true, false, 'ثبت درخواست صدور، تمدید یا تعویض گواهینامه از راهور.', ['national_code', 'delivery'], ['کارمزد راهور', 15000]],
                ['رزرو نوبت آزمون رانندگی', 'driving-test-appointment', 'calendar', 35000, 30, false, false, 'رزرو نوبت آزمون آیینی و عملی در مرکز موردنظر.', ['national_code', 'mobile'], null],
                ['درخواست کارت معاینه فنی', 'vehicle-inspection', 'wrench', 40000, 120, false, false, 'دریافت نوبت معاینه فنی و پیگیری نتیجه مراکز مجاز.', ['plate', 'national_code'], ['کارمزد مرکز معاینه', 10000]],
                ['پرداخت جرائم رانندگی (پلیس +۱۰)', 'traffic-fines-payment', 'receipt', 20000, 30, false, false, 'پرداخت جرائم رانندگی با کد رهگیری پلیس +۱۰ بدون نیاز به مراجعه.', ['plate', 'tracking'], ['کارمزد پرداخت', 5000]],
                ['تعویض پلاک خودرو', 'license-plate-replacement', 'car', 250000, 240, true, true, 'ثبت درخواست تعویض پلاک مفقودی، سوخته یا انتقالی.', ['national_code', 'plate', 'delivery'], ['کارمزد راهور', 15000]],
             ]],
            ['name' => 'استعلام‌های برخط', 'icon' => '🔍', 'sort' => 20,
             'description' => 'استعلام‌های آنی و اعتبارسنجی‌های مهم',
             'services' => [
                ['درخواست گواهی عدم سوءپیشینه', 'police-clearance-certificate', 'file-check', 95000, 120, false, true, 'دریافت گواهی عدم سوءپیشینه کیفری از سامانه ثنا.', ['national_code', 'delivery'], ['کارمزد سامانه ثنا', 30000]],
                ['استعلام اعتبارسنجی سجام', 'sejam-credit-check', 'search', 35000, 45, false, false, 'بررسی وضعیت احراز و اعتبارسنجی سجام برای معاملات بانکی.', ['national_code'], null],
                ['استعلام چک برگشتی (صیاد)', 'returned-cheque-inquiry', 'file-text', 25000, 20, false, false, 'استعلام وضعیت چک‌های برگشتی از سامانه صیاد بانک مرکزی.', ['national_code'], null],
                ['استعلام کد اقتصادی', 'economic-code-inquiry', 'briefcase', 30000, 30, false, false, 'بررسی اصالت کد اقتصادی اشخاص حقیقی و حقوقی.', ['national_code'], null],
                ['استعلام بدهی بانکی و کسر اعتبار', 'bank-debt-inquiry', 'landmark', 30000, 30, false, false, 'استعلام بدهی بانکی و وضعیت کسر از اعتبار از سامانه بانک مرکزی.', ['national_code'], null],
             ]],
            ['name' => 'ثبت احوال و مدارک', 'icon' => '🪪', 'sort' => 30,
             'description' => 'کارت ملی، شناسنامه، گذرنامه و خدمات ثبت احوال',
             'services' => [
                ['مفقودی کارت ملی', 'national-card-loss', 'id', 60000, 120, false, false, 'ثبت درخواست مفقودی و صدور مجدد کارت ملی.', ['national_code', 'delivery'], ['کارمزد ثبت احوال', 10000]],
                ['مفقودی شناسنامه', 'birth-certificate-loss', 'book', 70000, 180, true, false, 'ثبت مفقودی شناسنامه و درخواست صدور نسخه جدید.', ['national_code', 'details'], ['کارمزد ثبت احوال', 12000]],
                ['صدور و تمدید کارت ملی هوشمند', 'smart-card-issue', 'id', 120000, 240, true, false, 'درخواست صدور/تمدید کارت ملی هوشمند با تحویل پستی.', ['national_code', 'delivery'], ['کارمزد ثبت احوال', 15000]],
                ['استعلام وضعیت گذرنامه', 'passport-status', 'plane', 30000, 30, false, false, 'پیگیری مراحل صدور و آماده‌بودن گذرنامه.', ['tracking'], null],
                ['ثبت‌نام تولد و شناسنامه نوزاد', 'newborn-registration', 'heart', 50000, 180, false, false, 'ثبت ولادت نوزاد و درخواست شناسنامه از دفاتر ثبت احوال.', ['national_code', 'details'], null],
             ]],
            ['name' => 'خدمات قضایی و دفاتر', 'icon' => '⚖️', 'sort' => 40,
             'description' => 'دادخواست، شکواییه، پیگیری پرونده و دفاتر رسمی',
             'services' => [
                ['ثبت دادخواست الکترونیک', 'lawsuit-filing', 'scale', 80000, 240, true, false, 'تنظیم و ثبت دادخواست مدنی از طریق دفاتر خدمات قضایی.', ['national_code', 'details'], ['کارمزد دفاتر قضایی', 20000]],
                ['ثبت شکواییه کیفری', 'criminal-complaint', 'alert', 60000, 180, true, false, 'تنظیم شکواییه و ثبت آن در مرجع قضایی صالح.', ['national_code', 'details'], ['کارمزد دفاتر قضایی', 15000]],
                ['پیگیری پرونده قضایی (ثنا)', 'case-tracking', 'search', 25000, 30, false, false, 'استعلام آخرین وضعیت و جلسات پرونده از سامانه ثنا.', ['national_code', 'tracking'], null],
                ['رزرو نوبت دفاتر خدمات قضایی', 'judicial-office-appointment', 'calendar', 20000, 30, false, false, 'دریافت نوبت مراجعه به دفاتر خدمات الکترونیک قضایی.', ['national_code', 'mobile'], null],
                ['ترجمه رسمی مدارک', 'official-translation', 'stamp', 45000, 240, true, false, 'تحویل مدارک به دفاتر ترجمه رسمی و دریافت ترجمه.', ['delivery', 'details'], ['کارمزد دفتر ترجمه', 25000]],
             ]],
            ['name' => 'امور مالیاتی', 'icon' => '🧾', 'sort' => 50,
             'description' => 'اظهارنامه، استعلام بدهی و تقسیط مالیات',
             'services' => [
                ['ثبت اظهارنامه مالیات عملکرد', 'tax-return-filing', 'file-text', 90000, 240, true, false, 'تنظیم و ثبت اظهارنامه مالیات عملکرد اشخاص حقیقی.', ['national_code', 'details'], null],
                ['استعلام بدهی و پرداخت مالیات', 'tax-debt-inquiry', 'receipt', 30000, 30, false, false, 'استعلام بدهی مالیاتی و پرداخت اینترنتی آن.', ['national_code'], null],
                ['تقسیط بدهی مالیاتی', 'tax-installments', 'coins', 50000, 120, false, false, 'ثبت درخواست تقسیط بدهی مالیاتی و پیگیری نتیجه.', ['national_code', 'details'], null],
                ['دریافت کارت مالیاتی اشخاص', 'tax-card', 'id', 35000, 120, false, false, 'ثبت درخواست صدور کارت مالیاتی اشخاص حقیقی/حقوقی.', ['national_code'], null],
             ]],
            ['name' => 'امور بانکی و مالی', 'icon' => '🏦', 'sort' => 60,
             'description' => 'انتقال وجه، اقساط و استعلام‌های بانکی',
             'services' => [
                ['انتقال وجه بین‌بانکی (ساتنا/پایا)', 'interbank-transfer', 'send', 25000, 60, false, false, 'انجام انتقال وجه ساتنا/پایا بدون مراجعه به بانک.', ['mobile', 'details'], null],
                ['تسویه و پرداخت اقساط تسهیلات', 'loan-payment', 'banknote', 20000, 30, false, false, 'پرداخت قسط وام و تسویه تسهیلات بانکی.', ['national_code', 'tracking'], null],
                ['استعلام اعتبارسنجی بانکی', 'bank-credit-inquiry', 'credit-card', 30000, 30, false, false, 'بررسی امتیاز اعتباری برای دریافت تسهیلات.', ['national_code'], null],
                ['استعلام شماره شبا و حساب', 'iban-inquiry', 'landmark', 20000, 20, false, false, 'دریافت شماره شبا و اطلاعات حساب‌های بانکی.', ['mobile'], null],
             ]],
            ['name' => 'خدمات بیمه', 'icon' => '🛡️', 'sort' => 70,
             'description' => 'بیمه‌نامه‌های خودرو، عمر و تأمین اجتماعی',
             'services' => [
                ['صدور و تمدید بیمه شخص ثالث', 'third-party-insurance', 'shield', 90000, 60, false, true, 'استعلام و صدور آنی بیمه‌نامه شخص ثالث خودرو.', ['plate', 'national_code'], ['کارمزد صدور', 10000]],
                ['بیمه بدنه خودرو', 'car-body-insurance', 'car', 120000, 120, false, false, 'استعلام قیمت و صدور بیمه بدنه با پوشش‌های انتخابی.', ['plate'], null],
                ['استعلام و تمدید بیمه عمر', 'life-insurance', 'heart', 80000, 120, false, false, 'مشاهده وضعیت بیمه عمر و پرداخت اقساط آن.', ['national_code'], null],
                ['بیمه آتش‌سوزی و زلزله', 'fire-insurance', 'flame', 100000, 120, false, false, 'صدور بیمه‌نامه آتش‌سوزی مسکن و اموال.', ['details'], null],
                ['پرداخت حق بیمه تأمین اجتماعی', 'social-security-payment', 'users', 20000, 30, false, false, 'پرداخت حق بیمه و استعلام لیست بیمه.', ['national_code'], null],
                ['استعلام وضعیت مستمری بازنشستگان', 'pension-status', 'clock', 25000, 30, false, false, 'مشاهده وضعیت مستمری و فیش حقوقی بازنشستگی.', ['national_code'], null],
             ]],
            ['name' => 'خدمات برق', 'icon' => '⚡', 'sort' => 80,
             'description' => 'قبض، کنتور و اشتراک برق',
             'services' => [
                ['مشاهده و پرداخت قبض برق', 'electricity-bill', 'zap', 10000, 20, false, true, 'دریافت و پرداخت قبض برق با شناسه اشتراک.', ['bill_id'], ['کارمزد پرداخت', 5000]],
                ['قرائت و خوداظهاری کنتور برق', 'meter-reading', 'gauge', 12000, 30, false, false, 'ثبت رقم کنتور و اصلاح قبض دوره‌ای.', ['bill_id', 'mobile'], null],
                ['درخواست قطع یا وصل موقت برق', 'power-disconnection', 'power', 25000, 120, false, false, 'ثبت درخواست قطع/وصل موقت برق برای تعمیرات و سفر.', ['bill_id', 'details'], null],
                ['تغییر نام و انتقال کنتور برق', 'meter-transfer', 'file-text', 60000, 240, true, false, 'انتقال رسمی اشتراک برق به نام خریدار ملک.', ['bill_id', 'national_code'], null],
                ['صدور اشتراک جدید برق', 'new-subscription', 'plug', 80000, 240, false, false, 'درخواست انشعاب و صدور اشتراک برق جدید.', ['national_code', 'details'], null],
             ]],
            ['name' => 'خدمات گاز', 'icon' => '🔥', 'sort' => 90,
             'description' => 'قبض، کنتور و سرویس گاز',
             'services' => [
                ['مشاهده و پرداخت قبض گاز', 'gas-bill', 'flame', 10000, 20, false, false, 'دریافت و پرداخت قبض گاز با شناسه اشتراک.', ['bill_id'], ['کارمزد پرداخت', 5000]],
                ['درخواست نصب یا تعویض کنتور گاز', 'gas-meter-install', 'gauge', 70000, 240, false, false, 'ثبت درخواست نصب انشعاب یا تعویض کنتور گاز.', ['national_code', 'details'], null],
                ['قطع و وصل اشتراک گاز', 'gas-disconnection', 'power', 30000, 120, false, false, 'درخواست قطع/وصل موقت گاز برای تعمیرات.', ['bill_id'], null],
                ['نشت‌یابی و سرویس دوره‌ای گاز', 'gas-service', 'wrench', 45000, 120, false, false, 'هماهنگی سرویس کارشناس نشت‌یابی و سرویس دوره‌ای.', ['mobile', 'details'], null],
             ]],
            ['name' => 'خدمات آب', 'icon' => '💧', 'sort' => 100,
             'description' => 'قبض، کنتور و اشتراک آب',
             'services' => [
                ['مشاهده و پرداخت قبض آب', 'water-bill', 'droplet', 10000, 20, false, false, 'دریافت و پرداخت قبض آب با شناسه اشتراک.', ['bill_id'], ['کارمزد پرداخت', 5000]],
                ['قرائت کنتور آب', 'water-meter-reading', 'gauge', 12000, 30, false, false, 'ثبت رقم کنتور آب و اصلاح قبض.', ['bill_id'], null],
                ['درخواست قطع و وصل آب', 'water-disconnection', 'power', 30000, 120, false, false, 'درخواست قطع/وصل موقت آب.', ['bill_id'], null],
                ['تغییر نام کنتور آب', 'water-meter-transfer', 'file-text', 50000, 240, true, false, 'انتقال رسمی اشتراک آب به نام جدید.', ['bill_id', 'national_code'], null],
             ]],
            ['name' => 'خدمات پستی', 'icon' => '📮', 'sort' => 110,
             'description' => 'کد پستی و پیگیری مرسولات',
             'services' => [
                ['استعلام و تشخیص کد پستی', 'postal-code-inquiry', 'map-pin', 20000, 20, false, false, 'یافتن کد پستی دقیق هر نشانی.', ['details'], null],
                ['پیگیری مرسولات پستی', 'parcel-tracking', 'package', 15000, 20, false, false, 'رهگیری بسته و مرسولات پستی داخلی و خارجی.', ['tracking'], null],
                ['ثبت‌نام سامانه سما (مرسولات)', 'sama-registration', 'send', 25000, 60, false, false, 'ایجاد حساب سما و ثبت مرسوله پستی.', ['national_code', 'mobile'], null],
             ]],
            ['name' => 'بهداشت و درمان', 'icon' => '🏥', 'sort' => 120,
             'description' => 'نوبت‌دهی، نسخه و داروخانه',
             'services' => [
                ['نوبت‌دهی پزشک و کلینیک', 'doctor-appointment', 'heart-pulse', 25000, 30, false, true, 'رزرو نوبت پزشک در کلینیک‌ها و مطب‌های طرف قرارداد.', ['mobile', 'details'], null],
                ['پیگیری نسخه الکترونیک و داروخانه', 'e-prescription', 'pill', 20000, 30, false, false, 'پیگیری نسخه پزشک و اطلاع از موجودی داروخانه.', ['national_code', 'tracking'], null],
                ['نوبت‌دهی حضوری بیمارستان', 'hospital-appointment', 'building', 20000, 30, false, false, 'دریافت نوبت خدمات سرپایی و پاراکلینیکی بیمارستان.', ['mobile', 'details'], null],
             ]],
            ['name' => 'آموزش و دانشجویی', 'icon' => '🎓', 'sort' => 130,
             'description' => 'آزمون‌ها، کارنامه و مدارس',
             'services' => [
                ['دریافت کارت ورود به جلسه آزمون', 'exam-admission-card', 'id', 25000, 45, false, false, 'دریافت کارت ورود به جلسه آزمون‌های سراسری و دانشگاهی.', ['national_code', 'tracking'], null],
                ['استعلام ریز نمرات و کارنامه', 'grades-inquiry', 'file-text', 20000, 30, false, false, 'مشاهده ریز نمرات و کارنامه آزمون‌ها.', ['national_code', 'tracking'], null],
                ['خرید و ارسال کتاب درسی', 'textbook-order', 'book', 30000, 120, false, false, 'خرید مجموعه کتاب‌های درسی با ارسال پستی.', ['delivery', 'details'], null],
                ['ثبت‌نام و انتخاب مدرسه', 'school-registration', 'graduation-cap', 40000, 60, false, false, 'ثبت‌نام و انتخاب رشته/مدرسه در سامانه آموزش‌وپرورش.', ['national_code', 'details'], null],
                ['پرداخت شهریه مدارس غیرانتفاعی', 'private-school-tuition', 'wallet', 25000, 30, false, false, 'پرداخت شهریه ماهانه مدارس با شناسه دانش‌آموز.', ['national_code', 'tracking'], null],
             ]],
            ['name' => 'خدمات مسافرتی', 'icon' => '✈️', 'sort' => 140,
             'description' => 'بلیط قطار، اتوبوس، هواپیما و ویزا',
             'services' => [
                ['خرید بلیط قطار', 'train-ticket', 'train', 30000, 45, false, true, 'رزرو و خرید بلیط قطار با بهترین صندلی‌های خالی.', ['mobile', 'details'], null],
                ['خرید بلیط اتوبوس', 'bus-ticket', 'bus', 25000, 45, false, false, 'خرید بلیط اتوبوس‌های VIP بین‌شهری.', ['mobile', 'details'], null],
                ['رزرو بلیط هواپیما', 'flight-ticket', 'plane', 40000, 60, false, false, 'استعلام پروازها و صدور بلیط هواپیما.', ['national_code', 'details'], null],
                ['درخواست ویزا و روادید', 'visa-application', 'globe', 150000, 240, true, false, 'آماده‌سازی مدارک و ثبت درخواست ویزای سفر.', ['national_code', 'details'], null],
             ]],
            ['name' => 'رفاه اجتماعی', 'icon' => '🤝', 'sort' => 150,
             'description' => 'تسهیلات، یارانه و خدمات صندوق‌ها',
             'services' => [
                ['درخواست تسهیلات ازدواج', 'marriage-loan', 'heart', 30000, 120, false, false, 'ثبت درخواست وام ازدواج از صندوق‌های حمایتی.', ['national_code', 'details'], null],
                ['درخواست وام فرزندآوری', 'childbearing-loan', 'gift', 30000, 120, false, false, 'ثبت درخواست تسهیلات فرزندآوری.', ['national_code'], null],
                ['استعلام تسهیلات مسکن', 'housing-loan', 'home', 30000, 60, false, false, 'بررسی سهمیه و شرایط دریافت وام مسکن.', ['national_code'], null],
                ['ثبت‌نام یارانه و کارت سوخت', 'fuel-card', 'fuel', 20000, 45, false, false, 'ثبت‌نام یارانه سوخت و پیگیری کارت.', ['national_code', 'mobile'], null],
             ]],
            ['name' => 'خدمات ارتباطی و اینترنت', 'icon' => '📶', 'sort' => 160,
             'description' => 'شارژ، بسته اینترنت و قبوض مخابرات',
             'services' => [
                ['خرید شارژ و بسته اینترنت همراه', 'mobile-internet-package', 'wifi', 15000, 15, false, true, 'خرید شارژ و بسته‌های اینترنت همه اپراتورها با تخفیف.', ['mobile'], null],
                ['پرداخت قبض تلفن ثابت', 'landline-bill', 'phone', 10000, 20, false, false, 'دریافت و پرداخت قبض تلفن ثابت مخابرات.', ['bill_id'], ['کارمزد پرداخت', 5000]],
                ['پرداخت قبض اینترنت (ADSL)', 'adsl-bill', 'globe', 10000, 20, false, false, 'پرداخت قبض اینترنت تمام اپراتورها.', ['bill_id'], ['کارمزد پرداخت', 5000]],
                ['خرید سیم‌کارت و فلش‌کارت', 'sim-card-purchase', 'cpu', 35000, 60, false, false, 'خرید و ثبت‌نام سیم‌کارت/فلش‌کارت به نام متقاضی.', ['national_code'], null],
             ]],
            ['name' => 'خدمات ملک و املاک', 'icon' => '🏠', 'sort' => 170,
             'description' => 'استعلام اسناد، قرارداد و عوارض ملک',
             'services' => [
                ['استعلام اسناد ملکی (ثبتی)', 'property-deed-inquiry', 'file-check', 40000, 60, false, false, 'استعلام اصالت و وضعیت ثبتی سند ملک.', ['national_code', 'details'], null],
                ['استعلام وثایق و معاملات املاک', 'property-lien-inquiry', 'lock', 35000, 45, false, false, 'بررسی وثایق، بازداشت و معاملات اخیر ملک.', ['details'], null],
                ['ثبت قرارداد اجاره املاک', 'rental-contract', 'file-text', 80000, 240, true, false, 'تنظیم و ثبت رسمی قرارداد اجاره (سامانه معاملات املاک).', ['national_code', 'details'], null],
                ['عوارض نوسازی و ملک', 'renovation-fee', 'receipt', 30000, 60, false, false, 'استعلام و پرداخت عوارض نوسازی ساختمان.', ['bill_id'], null],
             ]],
            ['name' => 'خدمات عمومی کافی‌نت', 'icon' => '🖨️', 'sort' => 180,
             'description' => 'پرینت، اسکن، تایپ و فکس',
             'services' => [
                ['پرینت رنگی و سیاه‌وسفید', 'printing', 'printer', 5000, 15, false, true, 'چاپ فایل‌ها با کیفیت بالا — تحویل فوری.', ['details'], null],
                ['اسکن و ارسال مدارک', 'scanning', 'scan', 8000, 20, false, false, 'اسکن مدارک با کیفیت ۳۰۰dpi و ارسال به ایمیل.', ['delivery', 'details'], null],
                ['تایپ و صفحه‌آرایی', 'typing', 'keyboard', 15000, 120, false, false, 'تایپ فارسی/انگلیسی و صفحه‌آرایی پایان‌نامه و اسناد.', ['details'], null],
                ['ارسال و دریافت فکس', 'fax', 'phone', 10000, 30, false, false, 'ارسال فکس داخلی و خارجی و دریافت پیامکی رسید.', ['details'], null],
                ['پرینت عکس پرسنلی و مهاجرتی', 'photo-printing', 'id', 12000, 30, false, false, 'چاپ عکس پرسنلی ۳×۴ و ۴×۶ با استاندارد مهاجرت.', ['details'], null],
             ]],
        ];
    }

    /* =============================================================
     |  اجرا
     * ============================================================= */
    public function run(): void
    {
        // ۰) اسلاگ تمیز برای دو خدمت دموی موجود (تا upsert آن‌ها را پیدا کند)
        $this->migrateLegacyServices();

        // −۱) تعمیر symlink باقی‌مانده از zip های قبلی (علت 403 تصاویر)
        $this->ensureStorageLink();

        $catalog = $this->catalog();
        $names = array_column($catalog, 'name');

        // ۱) دسته‌ها
        $catIds = [];
        foreach ($catalog as $cat) {
            $row = ServiceCategory::updateOrCreate(
                ['name' => $cat['name']],
                ['icon' => $cat['icon'], 'description' => $cat['description'], 'sort' => $cat['sort'], 'is_active' => true, 'parent_id' => null]
            );
            $catIds[$cat['name']] = $row->id;
        }

        // ۲) خدمات + هزینه‌ها + فرم + آیکون
        $svcCount = 0;
        foreach ($catalog as $cat) {
            foreach ($cat['services'] as $i => [$name, $slug, $glyph, $price, $time, $upload, $featured, $desc, $fields, $fee]) {
                $image = $this->serviceIcon($slug);
                $service = Service::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name, 'category_id' => $catIds[$cat['name']],
                        'description' => $desc, 'base_price' => $price,
                        'estimated_time' => $time, 'requires_upload' => $upload,
                        'requires_verification' => false, 'is_active' => true,
                        'is_featured' => $featured, 'sort' => ($i + 1) * 10,
                        'image_path' => $image,
                    ]
                );
                $svcCount++;

                // همگام‌سازی ردیف‌های هزینه
                ServiceCost::where('service_id', $service->id)->delete();
                if ($fee) {
                    ServiceCost::create([
                        'service_id' => $service->id, 'type' => 'fee',
                        'title' => $fee[0], 'amount' => $fee[1],
                        'is_commission' => true,
                    ]);
                }

                // همگام‌سازی فیلدهای فرم
                ServiceFormField::where('service_id', $service->id)->delete();
                foreach ($fields as $fi => $key) {
                    $tpl = $this->fieldTemplates[$key];
                    ServiceFormField::create([
                        'service_id' => $service->id,
                        'field_type' => $tpl['field_type'], 'label' => $tpl['label'],
                        'name' => $tpl['name'],
                        'placeholder' => $tpl['placeholder'] ?? null,
                        'is_required' => $tpl['is_required'] ?? false,
                        'options' => $tpl['options'] ?? null,
                        'sort' => $fi * 10, 'is_active' => true,
                    ]);
                }
            }
        }

        // ۳) حذف دسته‌های قدیمی خالی (دمو)
        $deleted = ServiceCategory::whereNotIn('name', $names)
            ->whereDoesntHave('services')->delete();

        $this->command?->info("✅ کاتالوگ خدمات: {$svcCount} خدمت در ".count($catalog).' دسته seed شد — '.$deleted.' دستهٔ قدیمی حذف شد.');
        $this->command?->info('🖼 تصاویر WebP در storage/app/public/services کپی شد — سرو از روت /media (بدون نیاز به storage:link).');
    }

    /** دو خدمت دموی موجود را با اسلاگ تمیز کاتالوگ جدید منطبق می‌کند */
    private function migrateLegacyServices(): void
    {
        $map = [
            'drkhoast-goahy-soaapyshynh' => 'police-clearance-certificate',
            'taaoyd-plak-khodro-thran' => 'license-plate-replacement',
        ];
        foreach ($map as $old => $new) {
            Service::where('slug', $old)->update(['slug' => $new]);
        }
    }

    /* =============================================================
     |  تصویر خدمت — WebP آماده (1200×400) کپی از assets seeder
     |  در thumb مربعی و بنر صفحهٔ جزئیات هر دو درست کراپ می‌شود.
     * ============================================================= */
    private function serviceIcon(string $slug): ?string
    {
        $asset = __DIR__.'/assets/services/'.$slug.'.webp';
        if (! is_file($asset)) {
            $this->command?->warn("⚠ asset تصویر WebP یافت نشد: {$slug} (بدون تصویر seed می‌شود)");

            return null;
        }

        $disk = Storage::disk('public');
        $path = 'services/'.$slug.'.webp';

        // حذف نسخهٔ قدیمی SVG همین خدمت (اگر از seed قبلی مانده)
        $disk->delete('services/'.$slug.'.svg');

        if (! $disk->exists($path)) {
            $disk->put($path, file_get_contents($asset));
        }

        return $path;
    }

    /* =============================================================
     |  تعمیر public/storage — zip های قبلی به‌جای symlink، پوشهٔ واقعی
     |  (حاوی کپی قدیمی فایل‌ها) تحویل داده بودند؛ در آن حالت
     |  storage:link با «directory already exists» شکست می‌خورد و
     |  تصاویر آپلودی هرگز از /storage سرو نمی‌شدند (403/404).
     |  اینجا نسخهٔ خراب پاک و symlink نسبی درست ساخته می‌شود.
     |  سرو تصاویر از روت /media به symlink نیاز ندارد؛ این تعمیر فقط
     |  برای سازگاری (دسترسی مستقیم /storage) انجام می‌شود.
     * ============================================================= */
    private function ensureStorageLink(): void
    {
        $link = public_path('storage');

        if (is_link($link)) {
            return; // symlink درست است
        }

        try {
            if (is_dir($link) && ! is_link($link)) {
                // دفاع سخت‌گیرانه: فقط خودِ public/storage را پاک کن —
                // اگر به‌هرعلتی مسیر به ریشهٔ public یا پروژه حل شود، رها کن.
                $linkReal = realpath($link);
                $publicReal = realpath(public_path());
                $baseReal = realpath(base_path());
                if ($linkReal === false || $linkReal === $publicReal || $linkReal === $baseReal) {
                    $this->command?->warn('⚠ مسیر public/storage غیرقابل تعمیر است — سرو تصاویر از /media کار می‌کند.');

                    return;
                }

                // پوشهٔ واقعی (از zip خراب) — خالی‌کردن امن و حذف.
                // File::cleanDirectory از FilesystemIterator با SKIP_DOTS استفاده
                // می‌کند؛ برخلاف glob('.*') هرگز «.» و «..» را برنمی‌گرداند.
                \Illuminate\Support\Facades\File::cleanDirectory($link);
                @rmdir($link);
            }

            if (! file_exists($link) && ! is_link($link)) {
                @symlink('../storage/app/public', $link);
            }

            if (is_link($link)) {
                $this->command?->info('✅ symlink public/storage تعمیر شد (بخشی از seed فاز ۲۲).');
            } else {
                $this->command?->warn('⚠ ساخت symlink ممکن نشد — سرو تصاویر از /media بدون آن کار می‌کند.');
            }
        } catch (\Throwable $e) {
            $this->command?->warn('⚠ تعمیر symlink ناموفق بود — سرو تصاویر از /media بدون آن کار می‌کند.');
        }
    }
}
