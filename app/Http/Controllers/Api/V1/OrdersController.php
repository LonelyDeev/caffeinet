<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OrderDetailResource;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;
use App\Models\RatingOption;
use App\Models\Service;
use App\Services\Customer\OrderService;
use App\Services\Customer\PaymentGatewayService;
use App\Services\Notifications\NotificationService;
use App\Services\Orders\OrderAssignmentService;
use App\Services\Orders\RatingDistributionService;
use App\Services\Settings\SettingsService;
use App\Services\Settings\WorkingHoursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * سفارش‌های مشتری — ثبت، تاریخچه، جزئیات، پرداخت و لغو.
 */
class OrdersController extends Controller
{
    /** GET /api/v1/orders?status=&page= */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->where('customer_id', $request->user()->id)
            ->with([
                'service' => fn ($q) => $q->select(['id', 'name', 'category_id']),
                'service.category' => fn ($q) => $q->select(['id', 'name', 'icon']),
            ])
            ->orderByDesc('id');

        if ($status = (string) $request->query('status')) {
            $enum = OrderStatus::tryFrom($status);
            abort_if(! $enum, 422, 'وضعیت سفارش نامعتبر است.');

            $query->where('status', $enum->value);
        }

        $paginator = $query->paginate(10)->withQueryString();
        $paginator->getCollection()->transform(fn (Order $order) => OrderResource::make($order)->resolve());

        return response()->json($paginator);
    }

    /** POST /api/v1/orders — multipart: service_id + form_data (JSON) + files */
    public function store(Request $request, OrderService $orders, WorkingHoursService $workHours): JsonResponse
    {
        $user = $request->user();

        /* فاز ۱۵ — گارد ساعت کاری: خارج از ساعت کاری، ثبت سفارش مسدود است.
         * پاسخ با code مشخص برمی‌گردد تا اپ همان مودال زیبا را نمایش دهد. */
        if (! $workHours->isOpen()) {
            $status = $workHours->status();

            return response()->json([
                'code' => 'outside_work_hours',
                'message' => 'در حال حاضر خارج از ساعت کاری هستیم؛ ثبت درخواست ممکن نیست.',
                'work_hours' => [
                    'start' => fa_digits($status['start']),
                    'end' => fa_digits($status['end']),
                    'days' => $status['days'],
                    'message' => $status['message'],
                ],
            ], 403);
        }

        $serviceId = (int) $request->input('service_id', 0);
        $service = Service::query()->whereKey($serviceId)->where('is_active', true)->first();

        abort_unless($service, 404, 'خدمت درخواستی یافت نشد.');

        /* فاز ۱۵ — گارد وضعیت خدمت: قطع از سایت اصلی یا پایان مهلت */
        $state = $service->availabilityState();

        if ($state === 'unavailable') {
            return response()->json([
                'code' => 'service_unavailable',
                'message' => $service->availabilityNote() ?? 'این خدمت در حال حاضر از سایت اصلی قطع است.',
            ], 403);
        }

        if ($state === 'expired') {
            return response()->json([
                'code' => 'service_expired',
                'message' => $service->availabilityNote() ?? 'مهلت این خدمت به پایان رسیده است.',
                'expires_at' => $service->expiresAtLabel(),
            ], 403);
        }

        // form_data ممکن است JSON string باشد (multipart) یا آرایه مستقیم
        $formData = $request->input('form_data', '[]');

        if (is_string($formData)) {
            $decoded = json_decode($formData, true);
            $formData = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;

            if ($formData === null) {
                return response()->json([
                    'message' => 'ساختار form_data نامعتبر است.',
                    'errors' => ['form_data' => ['قالب JSON فرم ارسال‌شده نامعتبر است.']],
                ], 422);
            }
        } elseif (! is_array($formData)) {
            $formData = [];
        }

        // مدارک: files[نام‌فیلد] برای فیلدهای نوع file + documents[]
        $fileMap = [];

        foreach ($request->file('files', []) as $key => $group) {
            $fileMap[(string) $key] = array_values(
                array_filter(is_array($group) ? $group : [$group], 'is_object')
            );
        }

        // documents می‌تواند فایل تکی یا آرایه‌ای باشد
        $documents = $request->file('documents');
        if ($documents) {
            $docList = is_array($documents) ? array_values($documents) : [$documents];
            $fileMap['_extra'] = array_merge($fileMap['_extra'] ?? [], array_values(array_filter($docList, 'is_object')));
        }

        $order = $orders->create($user, $service, $formData, $fileMap);

        return response()->json([
            'message' => 'سفارش با موفقیت ثبت شد.',
            'data' => OrderDetailResource::make($this->loadDetail($order)),
        ], 201);
    }

    /** GET /api/v1/orders/{order} — با انقضای تنبلِ پخش (تایمر ۶۰ ثانیه بدون cron دقیق) */
    public function show(Request $request, Order $order, OrderAssignmentService $assignment): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        $assignment->expireStale();

        $order->refresh();

        return response()->json([
            'data' => OrderDetailResource::make($this->loadDetail($order)),
        ]);
    }

    /** POST /api/v1/orders/{order}/pay {method: wallet|online} */
    public function pay(Request $request, Order $order, PaymentGatewayService $payments): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        $method = (string) $request->input('method', '');

        if (! in_array($method, ['wallet', 'online'], true)) {
            return response()->json([
                'message' => 'روش پرداخت نامعتبر است.',
                'errors' => ['method' => ['روش پرداخت باید wallet یا online باشد.']],
            ], 422);
        }

        if ($method === 'wallet') {
            $payments->payWithWallet($order, $request->user());

            return response()->json([
                'message' => 'پرداخت از کیف پول انجام شد.',
                'data' => OrderDetailResource::make($this->loadDetail($order->refresh())),
            ]);
        }

        $payment = $payments->startOnline($order, $request->user());

        return response()->json([
            'message' => 'در حال انتقال به درگاه پرداخت…',
            'payment_url' => $payments->paymentUrl($payment),
            'payment_path' => $payments->paymentPath($payment),
            'data' => OrderDetailResource::make($this->loadDetail($order->refresh())),
        ]);
    }

    /** POST /api/v1/orders/{order}/cancel {reason} — دلیل لغو از سمت مشتری الزامی است */
    public function cancel(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:490'],
        ], [
            'reason.required' => 'دلیل لغو الزامی است؛ لطفاً آن را بنویسید.',
            'reason.min' => 'دلیل لغو باید حداقل :min نویسه باشد.',
            'reason.max' => 'دلیل لغو نباید بیشتر از :max نویسه باشد.',
            'reason.string' => 'دلیل لغو باید متن باشد.',
        ], [
            'reason' => 'دلیل لغو',
        ]);

        $orders->cancel(
            $request->user(),
            $order,
            trim((string) $data['reason'])
        );

        return response()->json([
            'message' => 'سفارش لغو شد.',
            'data' => OrderDetailResource::make($this->loadDetail($order->refresh())),
        ]);
    }

    /** POST /api/v1/orders/{order}/contact-preference {preference}
     * v39 — مشتری پس از پایان مهلت پخش بدون پذیرش، راه ارتباطی دلخواه خود را ثبت می‌کند. */
    public function contactPreference(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        /*
         * v40 — مدل جدید: تماس تلفنی (چک‌باکس مستقل) + یکی از راه‌های چت.
         * ورودی: { call: bool, chat: 'app_chat'|'telegram'|'whatsapp'|'bale'|'eitaa'|'any' }
         * حداقل یکی از دو بخش باید انتخاب شود.
         */
        $data = $request->validate([
            'call' => ['nullable', 'boolean'],
            'chat' => ['nullable', 'string', 'in:'.implode(',', array_map(
                fn (\App\Enums\ContactPreference $c) => $c->value,
                \App\Enums\ContactPreference::chatOptions()
            ))],
        ], [
            'chat.in' => 'راه ارتباطی انتخاب‌شده معتبر نیست.',
        ], [
            'call' => 'تماس تلفنی',
            'chat' => 'راه ارتباطی چت',
        ]);

        // فقط در وضعیت‌های پیش از اتصال اپراتور معنا دارد (صف تعیین‌تکلیف و پخشِ تمام‌شده)
        if (! in_array($order->status?->value, ['queued', 'broadcasting'], true)) {
            return response()->json([
                'message' => 'در وضعیت فعلی سفارش، ثبت راه ارتباطی امکان‌پذیر نیست.',
            ], 422);
        }

        $call = (bool) ($data['call'] ?? false);
        $chat = $data['chat'] ?? null;

        if (! $call && ! $chat) {
            return response()->json([
                'message' => 'حداقل یکی از «تماس تلفنی» یا یک راه چت را انتخاب کنید.',
                'errors' => ['chat' => ['حداقل یک روش ارتباطی انتخاب کنید.']],
            ], 422);
        }

        $preference = \App\Enums\ContactPreference::fromParts($call, $chat);

        $order->forceFill(['contact_preference' => $preference])->save();

        \App\Services\Audit\AuditLogger::log('order.contact_preference', $order, null,
            ['preference' => $preference],
            'انتخاب راه ارتباطی مشتری: '.(\App\Enums\ContactPreference::describe($preference) ?: $preference).' (سفارش '.$order->order_number.')');

        return response()->json([
            'message' => 'انتخاب شما ثبت شد؛ کارشناسان ما از همین راه با شما در تماس می‌شوند.',
            'preference' => $preference,
            'preference_label' => \App\Enums\ContactPreference::describe($preference),
        ]);
    }

    /** سفارشِ خودت یا ۴۰۴ (عدم افشای وجود) */
    protected function authorizeOwner(Request $request, Order $order): void
    {
        if ((int) $order->customer_id !== (int) $request->user()->id) {
            abort(404, 'سفارش یافت نشد.');
        }
    }

    protected function loadDetail(Order $order): Order
    {
        return $order->load([
            'service' => fn ($q) => $q->select(['id', 'name', 'description', 'category_id', 'estimated_time']),
            'service.category' => fn ($q) => $q->select(['id', 'name', 'icon']),
            'serviceVersion:id,service_id,version,snapshot',
            'coffeenet:id,name',
            'operator:id,name,family',
            'files',
            'statusHistory',
            'payments',
            'rating',
        ]);
    }

    /**
     * POST /api/v1/orders/{order}/rating — نظرسنجی کامل پس از اتمام (v33)
     *
     * {rating: 1..5, operator_rating?: 1..5, options?: [id...], comment?}
     *  - rating: امتیاز کلی تجربه (ستاره‌ها)
     *  - operator_rating: امتیاز اپراتور (فقط وقتی سفارش اپراتور دارد)
     *  - options: گزینه‌های دلایل (چک‌باکس‌های کارتی) — اسنپ‌شات ذخیره می‌شود
     */
    public function rate(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        if (! in_array($order->status, [OrderStatus::Delivered, OrderStatus::Completed], true)) {
            return response()->json([
                'message' => 'نظرسنجی فقط پس از تحویل یا تکمیل سفارش فعال است.',
            ], 422);
        }

        if (! app(SettingsService::class)->get('ratings.survey_enabled', true)) {
            return response()->json([
                'message' => 'نظرسنجی در حال حاضر غیرفعال است.',
            ], 422);
        }

        if ($order->rating()->exists()) {
            return response()->json([
                'message' => 'برای این سفارش قبلاً نظر ثبت شده است.',
            ], 422);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'operator_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'options' => ['nullable', 'array', 'max:8'],
            'options.*' => ['integer'],
            'comment' => ['nullable', 'string', 'max:500'],
        ], [
            'rating.required' => 'انتخاب امتیاز الزامی است.',
            'rating.min' => 'امتیاز باید بین ۱ تا ۵ باشد.',
            'rating.max' => 'امتیاز باید بین ۱ تا ۵ باشد.',
            'operator_rating.min' => 'امتیاز اپراتور باید بین ۱ تا ۵ باشد.',
            'operator_rating.max' => 'امتیاز اپراتور باید بین ۱ تا ۵ باشد.',
            'options.max' => 'حداکثر ۸ دلیل را می‌توانید انتخاب کنید.',
        ]);

        // گزینه‌های انتخابی — فقط گزینه‌های فعال و موجود؛ اسنپ‌شات (مستقل از حذف آینده)
        $selected = [];
        $optionIds = array_values(array_unique(array_map('intval', $data['options'] ?? [])));
        if ($optionIds) {
            $selected = RatingOption::query()
                ->active()
                ->whereKey($optionIds)
                ->ordered()
                ->get(['id', 'title', 'type'])
                ->map(fn (RatingOption $o) => ['id' => $o->id, 'title' => $o->title, 'type' => $o->type])
                ->values()
                ->all();
        }

        // امتیاز اپراتور فقط وقتی معنا دارد که سفارش اپراتور دارد
        $operatorRating = $order->operator_id && ! empty($data['operator_rating'])
            ? (int) $data['operator_rating']
            : null;

        $order->rating()->create([
            'rating' => (int) $data['rating'],
            'operator_rating' => $operatorRating,
            'comment' => $data['comment'] ?? null,
            'options' => $selected ?: null,
            'rated_at' => now(),
        ]);

        // کش آمار امتیاز کافی‌نت‌ها (پخش هوشمند) فوراً تازه شود
        app(RatingDistributionService::class)->flush();

        // اتمام نهایی: سفارشِ «تحویل‌شده» با ثبت نظر به «تکمیل‌شده» می‌رسد
        if ($order->status === OrderStatus::Delivered) {
            $order->forceFill([
                'status' => OrderStatus::Completed->value,
                'completed_at' => now(),
            ])->save();

            $order->statusHistory()->create([
                'from_status' => OrderStatus::Delivered->value,
                'to_status' => OrderStatus::Completed->value,
                'user_id' => $request->user()->id,
                'note' => 'ثبت نظرسنجی مشتری — تکمیل نهایی سفارش',
                'created_at' => now(),
            ]);
        }

        // اعلان امتیاز پایین به مدیر کل + مدیر کافی‌net (v33)
        $this->notifyLowRating($order, (int) $data['rating'], (string) ($data['comment'] ?? ''));

        return response()->json([
            'message' => 'از بازخورد شما سپاسگزاریم؛ نظرتان ثبت شد.',
            'data' => OrderDetailResource::make($this->loadDetail($order->refresh())),
        ], 201);
    }

    /** GET /api/v1/rating-options — گزینه‌های فعال نظرسنجی برای اپ مشتری */
    public function ratingOptions(): JsonResponse
    {
        $rows = RatingOption::query()->active()->ordered()->get(['id', 'title', 'type']);

        return response()->json([
            'data' => $rows->map(fn (RatingOption $o) => [
                'id' => $o->id,
                'title' => $o->title,
                'type' => $o->type,
            ]),
        ]);
    }

    /** اعلان امتیاز پایین (رویدادی + آفلاین پوش) — بی‌صدا و غیرمسدودکننده */
    protected function notifyLowRating(Order $order, int $rating, string $comment): void
    {
        $settings = app(SettingsService::class);

        if (! $settings->get('ratings.notify_low', true)) {
            return;
        }

        $threshold = (int) $settings->get('ratings.notify_low_threshold', 2);
        if ($rating > max(1, min(4, $threshold))) {
            return;
        }

        try {
            $notifications = app(NotificationService::class);

            $vars = [
                'order' => $order->order_number,
                'rating' => fa_digits((string) $rating),
                'coffeenet' => $order->coffeenet?->name ?? '—',
                'comment' => $comment !== '' ? mb_substr($comment, 0, 120) : '—',
            ];

            $notifications->notifyAdminsEvent('order.rating_low_admin', $vars, [
                'url' => '/admin/orders/'.$order->id.'/view',
            ]);

            if ($order->coffeenet_id) {
                $notifications->notifyCoffeenetManagersEvent(
                    (int) $order->coffeenet_id,
                    'order.rating_low_coffeenet',
                    $vars,
                    ['url' => '/coffeenet/'.$order->coffeenet_id.'/orders'],
                );
            }
        } catch (\Throwable) {
            // اعلان هرگز ثبت نظر را متوقف نمی‌کند
        }
    }
}
