<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Province;
use App\Models\User;
use App\Services\Analytics\WorkTrendService;
use App\Services\Auth\LoginLogger;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\WalletService;
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * مدیریت مشتریان — پنل مدیریت کل (درخواست بازخوردی).
 *
 * فهرست سراسری مشتریان (نقش customer) با آمار هر نفر + پروفایل کامل:
 * سفارش‌ها، نمودار روند سفارش، کیف پول و تراکنش‌ها، پرداخت‌های درگاه،
 * نظرسنجی‌ها، تاریخچهٔ ورود/خروج و لاگ تغییرات مدیریت.
 *
 * امکانات:
 *  - ویرایش کامل اطلاعات (مشخصات، جغرافیا، تاریخ تولد شمسی، وضعیت پروفایل)
 *  - مسدودسازی (بن) با دلیل اجباری + ابطال همهٔ توکن‌ها + رفع مسدودی
 *  - تنظیم دستی کیف پول (بستانکار/بدهکار) با شرح
 */
class CustomersController extends Controller
{
    /** GET /admin/customers — صفحهٔ فهرست (AJAX) */
    public function index(): View
    {
        return view('back.admin.customers.index', [
            'provinces' => Province::query()->orderBy('sort')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** GET /admin/customers/data — جدول AJAX (جستجو + فیلتر + مرتب‌سازی + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $query = User::query()
            ->role('customer')
            ->with(['province:id,name', 'city:id,name']);

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('family', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $status = (string) $request->query('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'banned') {
            $query->where('is_active', false);
        }

        $profile = (string) $request->query('profile');
        if ($profile === 'complete') {
            $query->where('profile_completed', true);
        } elseif ($profile === 'incomplete') {
            $query->where('profile_completed', false);
        }

        if ($provinceId = (int) $request->query('province_id')) {
            $query->where('province_id', $provinceId);
        }

        $sort = (string) $request->query('sort', 'newest');
        match ($sort) {
            'orders' => $query->withCount('orders')->orderByDesc('orders_count'),
            'login' => $query->orderByDesc('last_login_at'),
            'old' => $query->orderBy('id'),
            'spent', 'wallet' => null, // بعداً روی صفحهٔ جاری دستی مرتب می‌شوند
            default => $query->latest('id'),
        };

        $paginator = $query->paginate(25);

        $users = $paginator->getCollection();
        $ids = $users->pluck('id')->values()->all();

        /* آمار گروهیِ همین صفحه (سبک — بدون N+1) */
        $ordersCount = $ids ? Order::query()->whereIn('customer_id', $ids)
            ->selectRaw('customer_id, count(*) as c')->groupBy('customer_id')->pluck('c', 'customer_id') : collect();
        $spentMap = $ids ? Payment::query()->whereIn('user_id', $ids)
            ->where('status', 'success')
            ->selectRaw('user_id, sum(amount) as s')->groupBy('user_id')->pluck('s', 'user_id') : collect();
        $wallets = $ids ? \App\Models\Wallet::query()
            ->where('holder_type', User::class)->whereIn('holder_id', $ids)
            ->pluck('balance', 'holder_id') : collect();

        $rows = $users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'family' => $u->family,
            'full_name' => $u->full_name,
            'mobile' => $u->mobile,
            'email' => $u->email,
            'gender' => $u->gender?->value,
            'gender_label' => $u->gender?->label(),
            'province' => $u->province?->name,
            'province_id' => $u->province_id,
            'city' => $u->city?->name,
            'city_id' => $u->city_id,
            'birthdate' => $u->birthdate?->toDateString(),
            'birthdate_fa' => $u->birthdate ? fa_date($u->birthdate, 'Y/m/d') : null,
            'profile_completed' => (bool) $u->profile_completed,
            'is_active' => (bool) $u->is_active,
            'orders' => (int) ($ordersCount[$u->id] ?? 0),
            'spent' => (float) ($spentMap[$u->id] ?? 0),
            'wallet' => (float) ($wallets[$u->id] ?? 0),
            'last_login_fa' => $u->last_login_at ? fa_date($u->last_login_at, 'Y/m/d H:i') : null,
            'joined_fa' => fa_date($u->created_at, 'Y/m/d'),
        ])->values();

        /* مرتب‌سازی‌های مالی روی کل مجموعه (سبک برای ≤ چند هزار مشتری) */
        if ($sort === 'spent') {
            $rows = $rows->sortByDesc('spent')->values();
        } elseif ($sort === 'wallet') {
            $rows = $rows->sortByDesc('wallet')->values();
        }

        return response()->json([
            'data' => $rows->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => max(1, $paginator->lastPage()),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
        ]);
    }

    /** GET /admin/customers/{customer} — پروفایل کامل مشتری */
    public function show(User $customer): View
    {
        abort_unless($customer->hasRole('customer'), 404, 'مشتری یافت نشد.');

        $customer->load(['province:id,name', 'city:id,name', 'wallet']);

        $orders = Order::query()->where('customer_id', $customer->id);
        $totalOrders = (clone $orders)->count();
        $doneOrders = (clone $orders)->whereIn('status', ['delivered', 'completed'])->count();
        $activeOrders = (clone $orders)->whereIn('status', ['accepted', 'paid', 'in_progress', 'needs_info', 'queued', 'broadcasting', 'pending_payment'])->count();
        $cancelledOrders = (clone $orders)->whereIn('status', ['cancelled', 'refunded'])->count();

        $paidVolume = (float) (clone $orders)
            ->whereNotIn('status', ['pending_payment', 'cancelled', 'refunded'])
            ->whereNotNull('paid_at')
            ->sum('price');

        $avgDeliveryMinutes = (clone $orders)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereNotNull('delivered_at')
            ->selectRaw('AVG((julianday(delivered_at) - julianday(created_at)) * 1440) as m')
            ->value('m');

        /* نظرسنجی‌ها */
        $ratingsQuery = $customer->orderRatings();
        $ratingsCount = (clone $ratingsQuery)->count();
        $avgRating = $ratingsCount ? round((float) (clone $ratingsQuery)->avg('rating'), 1) : null;
        $recentRatings = (clone $ratingsQuery)->with('order:id,order_number,service_id', 'order.service:id,name')
            ->latest('rated_at')->limit(5)->get();

        /* کیف پول + تراکنش‌ها */
        $wallet = $customer->wallet;
        $transactions = $wallet
            ? $wallet->transactions()->latest('id')->limit(12)->get()
            : collect();

        /* پرداخت‌های درگاه (سفارش + شارژ کیف) */
        $payments = $customer->payments()->with('order:id,order_number')->latest('id')->limit(10)->get();
        $gatewayVolume = (float) $customer->payments()->where('status', 'success')->sum('amount');

        /* تاریخچهٔ ورود/خروج */
        $loginLogs = $customer->loginLogs()->latest('id')->limit(12)->get();
        $totalLogins = $customer->loginLogs()->logins()->count();

        /* تیکت‌های پشتیبانی */
        $ticketsCount = \App\Models\Ticket::query()->where('user_id', $customer->id)->count();

        /* آخرین سفارش‌ها */
        $recentOrders = (clone $orders)
            ->with(['service:id,name', 'coffeenet:id,name'])
            ->latest('id')->limit(10)->get();

        /* تاریخچهٔ تغییرات مدیریتی روی این مشتری (audit) */
        $auditLogs = AuditLog::query()
            ->where(function ($w) use ($customer) {
                $w->where('auditable_type', User::class)
                    ->where('auditable_id', $customer->id);
            })
            ->latest('id')->limit(8)->get();

        return view('back.admin.customers.show', [
            'customer' => $customer,
            'stats' => [
                'total_orders' => $totalOrders,
                'done_orders' => $doneOrders,
                'active_orders' => $activeOrders,
                'cancelled_orders' => $cancelledOrders,
                'paid_volume' => $paidVolume,
                'avg_delivery_hours' => $avgDeliveryMinutes !== null ? round($avgDeliveryMinutes / 60, 1) : null,
                'ratings_count' => $ratingsCount,
                'avg_rating' => $avgRating,
                'gateway_volume' => $gatewayVolume,
                'total_logins' => $totalLogins,
                'tickets_count' => $ticketsCount,
            ],
            'recentOrders' => $recentOrders,
            'recentRatings' => $recentRatings,
            'transactions' => $transactions,
            'payments' => $payments,
            'loginLogs' => $loginLogs,
            'auditLogs' => $auditLogs,
        ]);
    }

    /** GET /admin/customers/{customer}/trend — سری روند سفارش مشتری (چارت) */
    public function trend(Request $request, User $customer, WorkTrendService $trend): JsonResponse
    {
        abort_unless($customer->hasRole('customer'), 404, 'مشتری یافت نشد.');

        [$from, $to, $resolution] = WorkTrendService::resolveRange(
            $request->query('preset'),
            $request->query('from'),
            $request->query('to')
        );

        return response()->json(
            $trend->series((string) $request->query('preset', 'daily'), $from, $to, $resolution, [
                'customer_id' => $customer->id,
            ])
        );
    }

    /** PUT /admin/customers/{customer} — ویرایش کامل اطلاعات مشتری */
    public function update(Request $request, User $customer): JsonResponse
    {
        abort_unless($customer->hasRole('customer'), 404, 'مشتری یافت نشد.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'family' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email,'.$customer->id],
            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile,'.$customer->id],
            'gender' => ['nullable', 'in:male,female'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'birthdate' => ['nullable'], // شمسی «Y/m/d» یا میلادی Y-m-d — نرمال‌سازی می‌شود
            'profile_completed' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'name.required' => 'نام مشتری الزامی است.',
            'mobile.required' => 'موبایل ورود الزامی است.',
            'mobile.regex' => 'فرمت موبایل صحیح نیست (مثل 09123456789).',
            'mobile.unique' => 'این شماره موبایل متعلق به کاربر دیگری است.',
            'email.email' => 'فرمت ایمیل معتبر نیست.',
            'email.unique' => 'این ایمیل متعلق به کاربر دیگری است.',
            'city_id.exists' => 'شهر انتخابی یافت نشد.',
            'province_id.exists' => 'استان انتخابی یافت نشد.',
            'birthdate.date' => 'تاریخ تولد معتبر نیست.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
        ]);

        // شهر باید متعلق به استان انتخابی باشد
        if (! empty($data['city_id']) && ! empty($data['province_id'])) {
            $cityOk = \App\Models\City::query()
                ->where('id', $data['city_id'])
                ->where('province_id', $data['province_id'])
                ->exists();
            if (! $cityOk) {
                return response()->json(['message' => 'شهر انتخابی به استان مربوط تعلق ندارد.'], 422);
            }
        }

        $birthdate = $this->normalizeBirthdate((string) ($data['birthdate'] ?? ''));
        if ($birthdate === '__invalid__') {
            return response()->json(['message' => 'فرمت تاریخ تولد معتبر نیست (مثال: ۱۳۷۰/۰۵/۱۲).'], 422);
        }

        $old = $customer->only(['name', 'family', 'email', 'mobile', 'gender', 'province_id', 'city_id', 'birthdate', 'profile_completed', 'is_active']);

        $changes = [
            'name' => $data['name'],
            'family' => $data['family'] ?? null,
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'],
            'gender' => $data['gender'] ?? null,
            'province_id' => $data['province_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'birthdate' => $birthdate,
            'profile_completed' => array_key_exists('profile_completed', $data) && $data['profile_completed'] !== null
                ? (bool) $data['profile_completed']
                : $customer->profile_completed,
            'is_active' => array_key_exists('is_active', $data) && $data['is_active'] !== null
                ? (bool) $data['is_active']
                : $customer->is_active,
        ];

        DB::transaction(function () use ($customer, $changes, $data) {
            $customer->fill($changes)->save();

            if (! empty($data['password'])) {
                $customer->update(['password' => $data['password']]);
            }
        });

        AuditLogger::log('customer.updated_by_admin', $customer, $old, $changes,
            'ویرایش اطلاعات مشتری «'.$customer->full_name.'» توسط مدیر کل'
            .(! empty($data['password']) ? ' + تغییر رمز عبور' : ''));

        return response()->json([
            'message' => 'اطلاعات «'.$customer->full_name.'» ذخیره شد.',
        ]);
    }

    /**
     * PATCH /admin/customers/{customer}/ban — مسدودسازی (بن).
     * دلیل اجباری است؛ همهٔ توکن‌های فعال باطل می‌شوند تا فوراً خارج شود.
     */
    public function ban(Request $request, User $customer): JsonResponse
    {
        abort_unless($customer->hasRole('customer'), 404, 'مشتری یافت نشد.');
        abort_if((int) $customer->id === (int) auth()->id(), 422, 'حساب خودتان را نمی‌توانید مسدود کنید.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:490'],
        ], [
            'reason.required' => 'ذکر دلیل مسدودسازی الزامی است.',
            'reason.min' => 'حداقل ۳ حرف برای دلیل لازم است.',
        ]);

        if (! $customer->is_active) {
            return response()->json(['message' => 'این مشتری قبلاً مسدود شده است.'], 422);
        }

        DB::transaction(function () use ($customer, $data) {
            $customer->forceFill(['is_active' => false])->save();

            // ابطال همهٔ توکن‌های فعال اپ مشتری → خروج اجباری
            $customer->tokens()->delete();

            LoginLogger::log($customer, 'forced_logout', 'customer');
        });

        AuditLogger::log('customer.banned', $customer, ['is_active' => true], ['is_active' => false, 'reason' => $data['reason']],
            'مسدودسازی مشتری «'.$customer->full_name.'» — دلیل: '.$data['reason']);

        return response()->json([
            'message' => '«'.$customer->full_name.'» مسدود شد؛ دیگر نمی‌تواند وارد اپ شود و جلسه‌های فعالش بسته شد.',
        ]);
    }

    /** PATCH /admin/customers/{customer}/unban — رفع مسدودی */
    public function unban(User $customer): JsonResponse
    {
        abort_unless($customer->hasRole('customer'), 404, 'مشتری یافت نشد.');

        if ($customer->is_active) {
            return response()->json(['message' => 'این مشتری فعال است.'], 422);
        }

        $customer->forceFill(['is_active' => true])->save();

        AuditLogger::log('customer.unbanned', $customer, ['is_active' => false], ['is_active' => true],
            'رفع مسدودی مشتری «'.$customer->full_name.'»');

        return response()->json([
            'message' => '«'.$customer->full_name.'» فعال شد و می‌تواند دوباره وارد اپ شود.',
        ]);
    }

    /**
     * POST /admin/customers/{customer}/wallet — تنظیم دستی کیف پول.
     * amount مثبت = افزایش موجودی، منفی = کاهش؛ با شرح اجباری.
     */
    public function wallet(Request $request, User $customer, WalletService $wallets): JsonResponse
    {
        abort_unless($customer->hasRole('customer'), 404, 'مشتری یافت نشد.');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['required', 'string', 'min:3', 'max:490'],
        ], [
            'amount.required' => 'مبلغ را وارد کنید.',
            'amount.numeric' => 'مبلغ باید عدد باشد.',
            'amount.not_in' => 'مبلغ نمی‌تواند صفر باشد.',
            'description.required' => 'شرح تراکنش الزامی است.',
            'description.min' => 'حداقل ۳ حرف برای شرح لازم است.',
        ]);

        $amount = round((float) $data['amount'], 2);

        try {
            $transaction = $amount > 0
                ? $wallets->credit($customer, $amount, 'admin_adjust', null, 'افزایش دستی کیف پول — '.$data['description'], [
                    'by' => 'admin', 'admin_id' => auth()->id(),
                ])
                : $wallets->debit($customer, abs($amount), 'admin_adjust', null, 'کاهش دستی کیف پول — '.$data['description'], [
                    'by' => 'admin', 'admin_id' => auth()->id(),
                ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'خطا در ثبت تراکنش: '.$e->getMessage()], 422);
        }

        AuditLogger::log('customer.wallet_adjusted', $customer, null, [
            'amount' => $amount,
            'balance_after' => (float) $transaction->balance_after,
            'description' => $data['description'],
        ], 'تنظیم دستی کیف پول مشتری «'.$customer->full_name.'» با مبلغ '
            .fa_money($amount));

        return response()->json([
            'message' => 'تراکنش ثبت شد؛ موجودی جدید: '.fa_money($transaction->balance_after),
            'balance' => (float) $transaction->balance_after,
        ]);
    }

    /** نرمال‌سازی تاریخ تولد: شمسی «Y/m/d» (یا میلادی Y-m-d) → Y-m-d میلادی */
    protected function normalizeBirthdate(string $value): ?string
    {
        $value = en_digits(trim($value));

        if ($value === '') {
            return null;
        }

        // میلادی استاندارد؟
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                Carbon::createFromFormat('Y-m-d', $value);

                return $value;
            } catch (\Throwable) {
                return '__invalid__';
            }
        }

        foreach (['Y/m/d', 'Y-m-d', 'Y.m.d'] as $format) {
            try {
                return Jalalian::fromFormat($format, $value)->toCarbon()->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return '__invalid__';
    }
}
