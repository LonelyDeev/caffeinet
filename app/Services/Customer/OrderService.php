<?php

namespace App\Services\Customer;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderFile;
use App\Models\Service;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\ServiceVersionManager;
use App\Services\Notifications\NotificationService;
use App\Services\Orders\OrderAssignmentService;
use App\Services\Sms\CustomerSmsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;

/**
 * ساخت و مدیریت سفارش مشتری.
 *
 * مبالغ و فرم از snapshot نسخه خدمت فریز می‌شوند تا تغییرات
 * بعدی مدیریت روی سفارش‌های ثبت‌شده اثری نداشته باشد.
 */
class OrderService
{
    public const MAX_FILE_SIZE_KB = 5120;

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];

    public function __construct(
        protected DynamicFormValidator $formValidator,
        protected ServiceVersionManager $versionManager,
        protected OrderAssignmentService $assignment,
        protected NotificationService $notifications,
        protected CustomerSmsService $customerSms,
    ) {}

    /**
     * ثبت سفارش جدید.
     *
     * @param  array  $formData  پاسخ‌های فرم داینامیک
     * @param  array<string, array<UploadedFile>>  $files  مدارک؛ کلید = نام فنی فیلد یا «_extra»
     */
    public function create(User $customer, Service $service, array $formData, array $files): Order
    {
        if (! $customer->profile_completed) {
            throw ValidationException::withMessages([
                'profile' => ['قبل از ثبت سفارش باید پروفایل خود را کامل کنید.'],
            ]);
        }

        if (! $service->is_active) {
            throw ValidationException::withMessages([
                'service' => ['این خدمت فعال نیست و قابل سفارش نیست.'],
            ]);
        }

        // نسخه جاری (اگر نداره sync می‌سازد — محافظ)
        $version = $service->versions()->orderByDesc('version')->first()
            ?? $this->versionManager->sync($service);

        if (! $version) {
            throw ValidationException::withMessages([
                'service' => ['برای این خدمت نسخه‌ای ثبت نشده است؛ با پشتیبانی تماس بگیرید.'],
            ]);
        }

        $snapshot = $version->snapshot;
        $fields = is_array($snapshot['form_fields'] ?? null) ? $snapshot['form_fields'] : [];
        $costs = is_array($snapshot['costs'] ?? null) ? $snapshot['costs'] : [];

        // اعتبارسنجی پاسخ‌های فرم
        $normalizedData = $this->formValidator->validate($fields, $formData);

        // اعتبارسنجی مدارک و مسطح‌سازی
        $flatFiles = $this->validateFiles($fields, $snapshot, $files);

        // فریز مبالغ از snapshot
        $price = (float) ($snapshot['base_price'] ?? 0);
        $expenses = 0.0;
        $commissionable = 0.0;

        foreach ($costs as $cost) {
            $expenses += (float) ($cost['amount'] ?? 0);
            if (! empty($cost['is_commission'])) {
                $commissionable += (float) ($cost['amount'] ?? 0);
            }
        }

        $order = DB::transaction(function () use ($customer, $service, $version, $normalizedData, $flatFiles, $price, $expenses, $commissionable) {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'service_version_id' => $version->id,
                'status' => OrderStatus::PendingPayment,
                'form_data' => $normalizedData,
                'price' => $price,
                'expenses' => $expenses,
                'commissionable_amount' => $commissionable,
            ]);

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => OrderStatus::PendingPayment->value,
                'user_id' => $customer->id,
                'note' => 'ثبت سفارش توسط مشتری',
                'created_at' => now(),
            ]);

            foreach ($flatFiles as $file) {
                $this->storeFile($order, $customer, $file);
            }

            AuditLogger::log(
                'customer.order_created',
                $order,
                null,
                ['order_number' => $order->order_number, 'service' => $service->name, 'total' => $price + $expenses],
                "ثبت سفارش {$order->order_number} توسط مشتری"
            );

            return $order;
        });

        // اعلان درخواست جدید به مدیران (فاز ۱۰)
        $this->notifications->notifyAdmins(
            'order',
            'درخواست جدید مشتری',
            'درخواست «'.$order->order_number.'» برای خدمت «'.$service->name.'» ثبت و پخش شد.',
            ['url' => '/admin/orders/'.$order->id.'/view', 'ref' => ['order_id' => $order->id, 'order_number' => $order->order_number]],
        );

        // فاز ۱۱ — جریان «اتصال اول، پرداخت بعد»:
        // درخواست بلافاصله بین اپراتورها/کافی‌نت‌ها پخش می‌شود؛
        // مشتری بعد از اتصال اپراتور پرداخت می‌کند.
        try {
            $this->assignment->startBroadcast(
                $order->refresh(),
                null,
                'پخش فوری درخواست پس از ثبت — پرداخت پس از اتصال اپراتور'
            );
        } catch (ValidationException) {
            // گیرنده‌ای نبود → صف تعیین‌تکلیف (ادمین)
            try {
                $this->assignment->queueOrder(
                    $order->refresh(),
                    null,
                    'بدون گیرندهٔ پخش — انتقال مستقیم به صف تعیین‌تکلیف'
                );
            } catch (ValidationException) {
                // نادر — سفارش در وضعیت ثبت باقی می‌ماند و ادمین تعیین‌تکلیف می‌کند
            }
        }

        return $order->refresh();
    }

    /** لغو سفارش (فقط پیش از پرداخت — فاز ۱۱: تا قبل از پرداخت/شروع کار) */
    public function cancel(User $customer, Order $order, ?string $reason = null): Order
    {
        if ($order->customer_id !== $customer->id) {
            abort(403, 'این سفارش متعلق به شما نیست.');
        }

        $cancelable = [
            OrderStatus::PendingPayment,
            OrderStatus::Broadcasting,
            OrderStatus::Queued,
            OrderStatus::Accepted,
        ];

        if (! $order->paid_at && ! in_array($order->status, $cancelable, true)) {
            throw ValidationException::withMessages([
                'status' => ['این سفارش در وضعیت قابل لغو نیست؛ برای سفارش پرداخت‌شده با پشتیبانی تماس بگیرید.'],
            ]);
        }

        if ($order->paid_at) {
            throw ValidationException::withMessages([
                'status' => ['این سفارش پرداخت شده است؛ برای لغو با پشتیبانی تماس بگیرید.'],
            ]);
        }

        return DB::transaction(function () use ($customer, $order, $reason) {
            $from = $order->status;

            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancel_reason' => mb_substr((string) $reason, 0, 490),
                'cancelled_by' => $customer->id,
                'broadcast_expires_at' => null,
            ])->save();

            $order->statusHistory()->create([
                'from_status' => $from->value,
                'to_status' => OrderStatus::Cancelled->value,
                'user_id' => $customer->id,
                'note' => 'لغو توسط مشتری'.($reason ? ' — '.$reason : ''),
                'created_at' => now(),
            ]);

            AuditLogger::log('customer.order_cancelled', $order, null, ['reason' => $reason], "لغو سفارش {$order->order_number} توسط مشتری");

            $result = $order->refresh();

            // پیامک لغو به مشتری (درخواست بازخوردی ۶-۶ — fail-safe)
            try {
                $this->customerSms->orderCancelled($result, $reason);
            } catch (\Throwable) {
                // پیامک لغو را نمی‌شکند
            }

            return $result;
        });
    }

    /** شماره سفارش یکتا: CN{jalali yymmdd}-XXXX */
    protected function generateOrderNumber(): string
    {
        $prefix = 'CN'.Jalalian::now()->format('ymd');

        for ($i = 0; $i < 8; $i++) {
            $number = $prefix.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            if (! Order::query()->where('order_number', $number)->exists()) {
                return $number;
            }
        }

        // بازگشت نهایی
        return $prefix.'-'.str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
    }

    /**
     * اعتبارسنجی مدارک؛ خروجی: لیست مسطح فایل‌ها.
     *
     * @param  array<string, array<UploadedFile>>  $files
     * @return array<UploadedFile>
     */
    protected function validateFiles(array $fields, array $snapshot, array $files): array
    {
        // فیلدهای نوع file — الزام بارگذاری هر کدام
        foreach ($fields as $field) {
            if (($field['field_type'] ?? '') !== 'file' || empty($field['is_required'])) {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            $provided = collect($files[$name] ?? [])->filter(fn ($f) => $f instanceof UploadedFile && $f->isValid());

            if ($provided->isEmpty()) {
                throw ValidationException::withMessages([
                    $name => ['بارگذاری «'.$field['label'].'» الزامی است.'],
                ]);
            }
        }

        $flat = [];

        foreach ($files as $group) {
            foreach ((array) $group as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                $flat[] = $file;
            }
        }

        // الزام کلی مدارک (فلگ خدمت)
        if (! empty($snapshot['requires_upload']) && count($flat) === 0) {
            throw ValidationException::withMessages([
                'files' => ['برای این خدمت بارگذاری حداقل یک مدرک الزامی است.'],
            ]);
        }

        // اعتبارسنجی تک‌تک فایل‌ها
        foreach ($flat as $file) {
            if (! $file->isValid()) {
                throw ValidationException::withMessages([
                    'files' => ['یکی از فایل‌ها به‌درستی ارسال نشده است.'],
                ]);
            }

            if ($file->getSize() > self::MAX_FILE_SIZE_KB * 1024) {
                throw ValidationException::withMessages([
                    'files' => ['حجم فایل «'.$file->getClientOriginalName().'» بیشتر از ۵ مگابایت است.'],
                ]);
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: (string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));

            if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                throw ValidationException::withMessages([
                    'files' => ['فرمت فایل «'.$file->getClientOriginalName().'» مجاز نیست (PDF، تصویر یا Word).'],
                ]);
            }
        }

        return $flat;
    }

    /** ذخیره مدرک رمزنگاری‌شده روی دیسک خصوصی + ثبت OrderFile (فاز ۱۱) */
    protected function storeFile(Order $order, User $customer, UploadedFile $file): OrderFile
    {
        $safeName = str_replace(['\\', '/', "\0"], '-', $file->getClientOriginalName() ?: 'document');
        $storedName = now()->format('His').'-'.bin2hex(random_bytes(4)).'-'.$safeName;

        $path = "orders/{$order->id}/{$storedName}";

        \App\Support\SecureFile::put('local', $path, $file->getContent());

        return $order->files()->create([
            'uploaded_by' => $customer->id,
            'file_type' => 'document',
            'path' => (string) $path,
            'original_name' => mb_substr($safeName, 0, 180),
            'mime' => mb_substr((string) $file->getMimeType(), 0, 95),
            'size' => (int) $file->getSize(),
        ]);
    }
}
