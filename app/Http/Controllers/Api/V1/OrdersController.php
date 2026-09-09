<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OrderDetailResource;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;
use App\Models\Service;
use App\Services\Customer\OrderService;
use App\Services\Customer\PaymentGatewayService;
use App\Services\Orders\OrderAssignmentService;
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
    public function store(Request $request, OrderService $orders): JsonResponse
    {
        $user = $request->user();

        $serviceId = (int) $request->input('service_id', 0);
        $service = Service::query()->whereKey($serviceId)->where('is_active', true)->first();

        abort_unless($service, 404, 'خدمت درخواستی یافت نشد.');

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

    /** POST /api/v1/orders/{order}/cancel {reason?} */
    public function cancel(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        $orders->cancel(
            $request->user(),
            $order,
            $request->input('reason') ? trim((string) $request->input('reason')) : null
        );

        return response()->json([
            'message' => 'سفارش لغو شد.',
            'data' => OrderDetailResource::make($this->loadDetail($order->refresh())),
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

    /** POST /api/v1/orders/{order}/rating {rating: 1..5, comment?} — نظرسنجی پس از اتمام */
    public function rate(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        if (! in_array($order->status, [OrderStatus::Delivered, OrderStatus::Completed], true)) {
            return response()->json([
                'message' => 'نظرسنجی فقط پس از تحویل یا تکمیل سفارش فعال است.',
            ], 422);
        }

        if ($order->rating()->exists()) {
            return response()->json([
                'message' => 'برای این سفارش قبلاً نظر ثبت شده است.',
            ], 422);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ], [
            'rating.required' => 'انتخاب امتیاز الزامی است.',
            'rating.min' => 'امتیاز باید بین ۱ تا ۵ باشد.',
            'rating.max' => 'امتیاز باید بین ۱ تا ۵ باشد.',
        ]);

        $order->rating()->create([
            'rating' => (int) $data['rating'],
            'comment' => $data['comment'] ?? null,
            'rated_at' => now(),
        ]);

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

        return response()->json([
            'message' => 'از بازخورد شما سپاسگزاریم؛ نظرتان ثبت شد.',
            'data' => OrderDetailResource::make($this->loadDetail($order->refresh())),
        ], 201);
    }
}
