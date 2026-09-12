<?php

namespace App\Services\Notifications;

/**
 * رجیستری قالب‌های اعلان (v25) — عنوان و متنِ «متفاوت» برای هر رویداد
 * در هر بخش (مشتری / مدیر کل / کافی‌نت / اپراتور / سازمان).
 *
 * هر رویداد یک کلید دارد (مثل ticket.answered_customer) که هم برای
 * اعلان درون‌برنامه‌ای، هم پوش دستگاه (FCM) و هم جایی که لازم شد
 * پیامک استفاده می‌شود؛ جایگاه‌ها به‌صورت {var} جایگزین می‌شوند.
 *
 * قواعد:
 *  • title/body کوتاه و خوانا (محدودیت پوش: ۱۰۰/۲۵۰ کاراکتر)
 *  • type در NotificationService::TYPES
 *  • url معمولاً از فراخواننده می‌آید (data['url'])
 */
class NotificationTemplate
{
    /**
     * @var array<string, array{type:string, title:string, body:string}>
     */
    public const EVENTS = [
        /* ---------- تیکت پشتیبانی ---------- */
        // مشتری وقتی کارشناس به تیکتش پاسخ می‌دهد
        'ticket.answered_customer' => [
            'type' => 'ticket',
            'title' => 'پاسخ جدید به تیکت شما',
            'body' => 'پشتیبانی به تیکت «{ticket}» پاسخ داد؛ پاسخ را در بخش پشتیبانی بخوانید.',
        ],
        // مدیران کل وقتی مشتری تیکت جدید می‌سازد
        'ticket.new_admin' => [
            'type' => 'ticket',
            'title' => 'تیکت پشتیبانی جدید',
            'body' => 'تیکت «{ticket}» با موضوع «{subject}» توسط مشتری ثبت شد.',
        ],
        // مدیر کافی‌نت وقتی برای سفارشش تیکت ثبت شد
        'ticket.new_coffeenet' => [
            'type' => 'ticket',
            'title' => 'تیکت جدید روی سفارش کافی‌نت',
            'body' => 'برای سفارش «{order}» تیکتی با موضوع «{subject}» ثبت شد.',
        ],
        // مدیران/کارشناس ارجاعی وقتی مشتری روی تیکت پاسخ داد
        'ticket.customer_replied' => [
            'type' => 'ticket',
            'title' => 'پاسخ مشتری روی تیکت',
            'body' => 'مشتری روی تیکت «{ticket}» پاسخ تازه‌ای نوشت.',
        ],
        // کارشناس وقتی تیکتی به او ارجاع شد
        'ticket.assigned_staff' => [
            'type' => 'ticket',
            'title' => 'تیکتی به شما ارجاع شد',
            'body' => 'تیکت «{ticket}» با موضوع «{subject}» به شما ارجاع شد.',
        ],
        // مشتری وقتی تیکتش بسته شد
        'ticket.closed_customer' => [
            'type' => 'ticket',
            'title' => 'تیکت شما بسته شد',
            'body' => 'تیکت «{ticket}» بسته شد؛ در صورت نیاز تیکت جدید ثبت کنید.',
        ],

        /* ---------- سفارش‌ها ---------- */
        // مدیران کل وقتی درخواست جدید مشتری ثبت می‌شود
        'order.new_admin' => [
            'type' => 'order',
            'title' => 'درخواست جدید مشتری',
            'body' => 'درخواست «{order}» برای خدمت «{service}» ثبت شد و در صف پخش است.',
        ],
        // مدیران کل وقتی درخواستی مدت‌هاست پذیرفته نشده (دستور زمان‌بندی‌شده)
        'order.unaccepted_admin' => [
            'type' => 'order',
            'title' => 'درخواست بی‌پذیرش',
            'body' => 'درخواست «{order}» پس از {minutes} دقیقه هنوز توسط هیچ کافی‌نتی پذیرفته نشده است.',
        ],
        // مدیر کافی‌نت وقتی ادمین سفارش را به کافی‌نتش انتقال/تخصیص می‌دهد
        'order.transferred_coffeenet' => [
            'type' => 'order',
            'title' => 'سفارشی به کافی‌نت شما تخصیص یافت',
            'body' => 'درخواست «{order}» برای خدمت «{service}» از طریق مدیریت کل به کافی‌نت «{coffeenet}» تخصیص یافت.',
        ],
        // اپراتور وقتی سفارش به او واگذار می‌شود (تخصیص ادمین یا مدیر کافی‌نت)
        'order.assigned_operator' => [
            'type' => 'order',
            'title' => 'سفارش جدید به شما واگذار شد',
            'body' => 'درخواست «{order}» کافی‌نت «{coffeenet}» به شما واگذار شد؛ از بخش «سفارش‌ها» پیگیری کنید.',
        ],
        // مشتری وقتی کارمند/اپراتور مسئول پیگیری سفارشش می‌شود (v28)
        'order.operator_customer' => [
            'type' => 'order',
            'title' => 'کارشناس مسئول سفارش شما',
            'body' => 'کارمند «{operator}» از کافی‌نت «{coffeenet}» مسئول پیگیری درخواست «{order}» شما شد.',
        ],
        // مشتری وقتی سفارشش پذیرفته شد
        'order.accepted_customer' => [
            'type' => 'order',
            'title' => 'پذیرش سفارش',
            'body' => 'درخواست «{order}» توسط {acceptor} پذیرفته شد؛ برای شروع کار، پرداخت را انجام دهید.',
        ],
        // مشتری وقتی سفارش به صف تعیین‌تکلیف رفت
        'order.queued_customer' => [
            'type' => 'order',
            'title' => 'سفارش به صف بررسی رفت',
            'body' => 'درخواست «{order}» به صف بررسی کارشناسان کافی‌نت آنلاین منتقل شد.',
        ],
        // مشتری — تغییر وضعیت‌های پایانی
        'order.delivered_customer' => [
            'type' => 'order',
            'title' => 'سفارش تحویل شد',
            'body' => 'درخواست «{order}» تحویل داده شد؛ نظرسنجی و امتیازدهی در صفحهٔ سفارش فعال است.',
        ],
        'order.cancelled_customer' => [
            'type' => 'order',
            'title' => 'سفارش لغو شد',
            'body' => 'درخواست «{order}» لغو شد. {reason}',
        ],
        'order.status_customer' => [
            'type' => 'order',
            'title' => 'بروزرسانی وضعیت سفارش',
            'body' => 'وضعیت درخواست «{order}» به «{status}» تغییر کرد.',
        ],

        /* ---------- مالی (واریز حقوق / تسویه / برداشت) ---------- */
        // اپراتور — واریز حقوق/درآمد به کیف پول
        'settlement.operator' => [
            'type' => 'settlement',
            'title' => 'واریز درآمد به کیف پول',
            'body' => 'سفارش «{order}» تحویل شد و درآمد شما ({amount}) به کیف پول واریز شد.',
        ],
        // مدیر کافی‌نت — واریز کمیسیون
        'settlement.coffeenet' => [
            'type' => 'settlement',
            'title' => 'واریز کمیسیون به کیف پول',
            'body' => 'سفارش «{order}» تحویل شد و سهم کافی‌نت ({amount}) به کیف پول شما واریز شد.',
        ],
        // مالک سازمان — سهم سازمان/پاداش معرفی
        'settlement.org' => [
            'type' => 'settlement',
            'title' => 'واریز سهم سازمان',
            'body' => 'سفارش «{order}» تحویل شد و {detail} به کیف پول شما واریز شد.',
        ],
        // نتیجهٔ درخواست برداشت
        'withdrawal.result' => [
            'type' => 'withdrawal',
            'title' => 'نتیجهٔ درخواست برداشت',
            'body' => 'درخواست برداشت {amount} تومانی شما {result}.',
        ],
        // درخواست برداشت جدید (به مدیران)
        'withdrawal.requested_admin' => [
            'type' => 'withdrawal',
            'title' => 'درخواست برداشت جدید',
            'body' => '{name} درخواست برداشت {amount} تومان ثبت کرد.',
        ],

        /* ---------- کارکنان ---------- */
        // مدیران کل — نیاز به تایید کارمند جدید
        'staff.approval_admin' => [
            'type' => 'system',
            'title' => 'کارمند جدید در انتظار تایید',
            'body' => 'کارمند «{name}» به کافی‌نت «{coffeenet}» اضافه شد و به تایید شما نیاز دارد.',
        ],
        // کارمند — نتیجهٔ تایید
        'staff.approval_result' => [
            'type' => 'system',
            'title' => 'نتیجهٔ بررسی عضویت شما',
            'body' => 'درخواست عضویت شما در کافی‌نت «{coffeenet}» {result}.',
        ],
    ];

    /**
     * ساخت title/body نهایی برای یک رویداد با جایگذاری متغیرها.
     *
     * @return array{type:string, title:string, body:string}|null
     */
    public static function compose(string $event, array $vars = []): ?array
    {
        $tpl = self::EVENTS[$event] ?? null;

        if (! $tpl) {
            return null;
        }

        $replace = [];

        foreach ($vars as $key => $value) {
            $replace['{'.$key.'}'] = (string) $value;
        }

        return [
            'type' => $tpl['type'],
            'title' => strtr($tpl['title'], $replace),
            'body' => trim(strtr($tpl['body'], $replace)),
        ];
    }

    /** کلیدهای مجاز (برای مستندسازی/دیباگ) */
    public static function events(): array
    {
        return array_keys(self::EVENTS);
    }
}
