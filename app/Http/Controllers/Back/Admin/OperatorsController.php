<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Message;
use App\Models\Order;
use App\Models\SalaryLog;
use App\Models\StaffAssignment;
use App\Models\Withdrawal;
use App\Services\Analytics\WorkTrendService;
use App\Services\Audit\AuditLogger;
use App\Support\OperatorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * فاز ۱۰ — دید کلان مدیر به همهٔ کارکنان/اپراتورهای شبکهٔ کافی‌نت‌ها
 * (فهرست سراسری بر اساس staff_assignments + آمار عملکرد هر نفر)
 *
 * درخواست بازخوردی ۶-۱: show() صفحهٔ پروفایل کامل اپراتور —
 * آمار/عملکرد/دسترسی‌ها/مالی/برداشت‌ها/روند کاری (چارت بازه‌ای).
 */
class OperatorsController extends Controller
{
    /** صفحهٔ فهرست کارکنان (AJAX) */
    public function index(): View
    {
        return view('back.admin.operators.index', [
            'coffeenets' => Coffeenet::query()
                ->where('status', 'approved')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * افزودن اپراتور جدید توسط مدیر کل (فاز ۱۳).
     *
     * قانون: مدیر کل «حتماً» کافی‌نت اپراتور را تعیین می‌کند (اجباری).
     *  - کاربر با نقش operator ساخته می‌شود
     *  - عضویت فعال + تاییدشده در کافی‌نت انتخابی
     *  - قاعدهٔ تک-کافی‌نت: اگر موبایل/ایمیل کاربر موجودی باشد که اپراتورِ فعالِ
     *    کافی‌نت دیگری است، افزودن رد می‌شود (انتقال فقط از مسیر ویرایش)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'family' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8'],
            'coffeenet_id' => ['required', 'integer', 'exists:coffeenets,id'],
            'position' => ['nullable', 'in:manager,operator'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'salary_type' => ['nullable', 'in:percent,fixed_per_order,monthly'],
            'salary_rate' => ['nullable', 'numeric', 'min:0'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0'],
        ], [
            'name.required' => 'نام اپراتور الزامی است.',
            'email.required' => 'ایمیل ورود الزامی است.',
            'email.email' => 'فرمت ایمیل معتبر نیست.',
            'email.unique' => 'این ایمیل متعلق به کاربر دیگری است.',
            'mobile.regex' => 'فرمت موبایل صحیح نیست (مثل 09123456789).',
            'mobile.unique' => 'این شماره موبایل متعلق به کاربر دیگری است.',
            'password.required' => 'رمز عبور الزامی است.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
            'coffeenet_id.required' => 'تعیین کافی‌نت برای اپراتور الزامی است.',
            'coffeenet_id.exists' => 'کافی‌نت انتخابی یافت نشد.',
            'position.in' => 'سمت انتخابی معتبر نیست.',
            'salary_type.in' => 'مدل حقوق معتبر نیست.',
        ]);

        $position = $data['position'] ?? 'operator';

        $net = Coffeenet::query()->findOrFail((int) $data['coffeenet_id']);

        if ($net->status !== \App\Enums\CoffeenetStatus::Approved) {
            return response()->json(['message' => 'کافی‌نت «'.$net->name.'» فعال نیست؛ اپراتور فقط به کافی‌نت‌های فعال اضافه می‌شود.'], 422);
        }

        if (isset($data['salary_type'], $data['salary_rate'])
            && $data['salary_type'] === 'percent' && (float) $data['salary_rate'] > 100) {
            return response()->json(['message' => 'درصد حقوق نمی‌تواند بیش از ۱۰۰ باشد.'], 422);
        }

        $permissions = $position === 'operator'
            ? OperatorPermissions::filter($data['permissions'] ?? OperatorPermissions::defaults())
            : [];

        $assignment = DB::transaction(function () use ($data, $position, $net, $permissions) {
            $user = \App\Models\User::create([
                'name' => $data['name'],
                'family' => $data['family'] ?? null,
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $user->assignRole($position === 'manager' ? 'coffeenet_manager' : 'operator');

            $assignment = StaffAssignment::create([
                'coffeenet_id' => $net->id,
                'user_id' => $user->id,
                'position' => $position,
                'permissions' => $permissions,
                'is_active' => true,
                'approval_status' => StaffAssignment::APPROVAL_APPROVED,
                'assigned_by' => auth()->id(),
            ]);

            // مدل حقوق (اگر فرم پر شده باشد؛ در غیر این صورت درصدی ۵۰٪ برای ادامهٔ تسویه)
            \App\Models\SalarySetting::updateOrCreate(
                ['coffeenet_id' => $net->id, 'user_id' => $user->id],
                [
                    'type' => $data['salary_type'] ?? 'percent',
                    'rate' => isset($data['salary_rate']) ? (float) $data['salary_rate'] : 50,
                    'overtime_rate' => $data['overtime_rate'] ?? null,
                    'is_active' => true,
                ],
            );

            return $assignment;
        });

        // اطلاع به مدیر کافی‌نت (v25 — رویدادی + پوش آفلاین)
        app(\App\Services\Notifications\NotificationService::class)
            ->notifyCoffeenetManagersEvent(
                (int) $net->id,
                'staff.approval_result',
                [
                    'coffeenet' => $net->name,
                    'result' => 'اپراتور «'.trim($data['name'].' '.($data['family'] ?? '')).'» توسط مدیر کل به کافی‌نت شما اضافه شد',
                ],
                ['coffeenet_id' => $net->id, 'assignment_id' => $assignment->id],
            );

        AuditLogger::log('staff.created_by_admin', $assignment, null, [
            'user' => $data['email'],
            'coffeenet_id' => $net->id,
            'position' => $position,
            'permissions' => $permissions,
        ], 'افزودن '.($position === 'operator' ? 'اپراتور' : 'مدیر').' «'.trim($data['name'].' '.($data['family'] ?? '')).'» به کافی‌نت «'.$net->name.'» توسط مدیر کل');

        return response()->json([
            'message' => ($position === 'operator' ? 'اپراتور' : 'مدیر').' «'.trim($data['name'].' '.($data['family'] ?? '')).'» در کافی‌نت «'.$net->name.'» ایجاد شد و هم‌اکنون فعال است.',
            'id' => $assignment->id,
        ]);
    }

    /** پروفایل کامل کارمند/اپراتور — همه‌چیز در یک نگاه */
    public function show(StaffAssignment $assignment): View
    {
        $assignment->load([
            'user:id,name,family,email,mobile,created_at,last_login_at,is_active',
            'coffeenet:id,name,phone,status,organization_id,province_id,city_id',
            'coffeenet.organization:id,name',
            'coffeenet.province:id,name',
            'coffeenet.city:id,name',
            'coffeenet.wallet',
            'assignedBy:id,name,family',
        ]);

        $user = $assignment->user;
        abort_unless($user, 404);

        $orders = Order::query()
            ->where('operator_id', $user->id)
            ->where('coffeenet_id', $assignment->coffeenet_id);

        $totalOrders = (clone $orders)->count();
        $doneOrders = (clone $orders)->whereIn('status', ['delivered', 'completed'])->count();
        $activeOrders = (clone $orders)->whereIn('status', ['accepted', 'in_progress', 'needs_info'])->count();
        $cancelledOrders = (clone $orders)->whereIn('status', ['cancelled', 'refunded'])->count();
        $salesVolume = (float) (clone $orders)
            ->whereNotIn('status', ['pending_payment', 'cancelled', 'refunded'])
            ->whereNotNull('paid_at')
            ->sum('price');

        // میانگین زمان تحویل (ساعت) برای سفارش‌های تحویل‌شده
        $avgDeliveryMinutes = (clone $orders)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereNotNull('delivered_at')
            ->selectRaw('AVG((julianday(delivered_at) - julianday(created_at)) * 1440) as m')
            ->value('m');

        $chatMessages = Message::query()
            ->where('sender_id', $user->id)
            ->count();

        $conversations = \App\Models\Conversation::query()
            ->whereIn('order_id', (clone $orders)->select('id'))
            ->count();

        // آخرین سفارش‌ها
        $recentOrders = (clone $orders)
            ->with(['service:id,name', 'customer:id,name,family'])
            ->latest('id')
            ->limit(10)
            ->get();

        // مالی: مدل حقوق + لاگ‌های حقوق
        $salarySetting = \App\Models\SalarySetting::query()
            ->where('coffeenet_id', $assignment->coffeenet_id)
            ->where('user_id', $user->id)
            ->first();

        $salaryLogs = SalaryLog::query()
            ->where('coffeenet_id', $assignment->coffeenet_id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(8)
            ->get();

        $salaryTotal = (float) SalaryLog::query()
            ->where('coffeenet_id', $assignment->coffeenet_id)
            ->where('user_id', $user->id)
            ->sum('amount');

        // برداشت‌های ثبت‌شده توسط این کاربر (کیف کافی‌نت)
        $wallet = $assignment->coffeenet?->wallet;
        $withdrawals = $wallet
            ? Withdrawal::query()
                ->where('wallet_id', $wallet->id)
                ->where('requested_by', $user->id)
                ->latest('id')
                ->limit(6)
                ->get()
            : collect();

        // دسترسی‌های پنل اپراتور (permissions JSON) + نقش‌های Spatie
        $operatorPermissions = OperatorPermissions::filter($assignment->permissions ?? []);
        $permissionCatalog = OperatorPermissions::CATALOG;
        $roles = $user->getRoleNames()->all();

        return view('back.admin.operators.show', [
            'assignment' => $assignment,
            'user' => $user,
            'stats' => [
                'total_orders' => $totalOrders,
                'done_orders' => $doneOrders,
                'active_orders' => $activeOrders,
                'cancelled_orders' => $cancelledOrders,
                'sales_volume' => $salesVolume,
                'chat_messages' => $chatMessages,
                'conversations' => $conversations,
                'avg_delivery_hours' => $avgDeliveryMinutes !== null ? round($avgDeliveryMinutes / 60, 1) : null,
            ],
            'recentOrders' => $recentOrders,
            'salarySetting' => $salarySetting,
            'salaryLogs' => $salaryLogs,
            'salaryTotal' => $salaryTotal,
            'withdrawals' => $withdrawals,
            'operatorPermissions' => $operatorPermissions,
            'permissionCatalog' => $permissionCatalog,
            'roles' => $roles,
        ]);
    }

    /** سری روند کاری (چارت) — GET /admin/operators/{assignment}/trend */
    public function trend(Request $request, StaffAssignment $assignment, WorkTrendService $trend): JsonResponse
    {
        [$from, $to, $resolution] = WorkTrendService::resolveRange(
            $request->query('preset'),
            $request->query('from'),
            $request->query('to')
        );

        return response()->json(
            $trend->series((string) $request->query('preset', 'daily'), $from, $to, $resolution, [
                'operator_id' => $assignment->user_id,
                'coffeenet_id' => $assignment->coffeenet_id,
            ])
        );
    }

    /** دادهٔ جدول کارکنان (جستجو + فیلتر سمت/وضعیت/کافی‌نت + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $query = StaffAssignment::query()
            ->with(['user:id,name,family,email,mobile,created_at,is_active', 'coffeenet:id,name']);

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('family', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%"))
                    ->orWhereHas('coffeenet', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        if ($position = (string) $request->query('position')) {
            if (in_array($position, ['manager', 'operator'], true)) {
                $query->where('position', $position);
            }
        }

        if ($active = (string) $request->query('active')) {
            if ($active === 'pending') {
                $query->where('approval_status', 'pending');
            } elseif (in_array($active, ['0', '1'], true)) {
                $query->where('is_active', $active === '1');
            }
        }

        if ($netId = (int) $request->query('coffeenet_id')) {
            if ($netId > 0) {
                $query->where('coffeenet_id', $netId);
            }
        }

        $paginator = $query->latest('id')->paginate(25);

        /* شمارش‌های عملکرد فقط برای کاربران همین صفحه (۲۵ ردیف) —
           سه کوئری گروهی سبک به‌جای join سنگین */
        $userIds = $paginator->getCollection()->pluck('user_id')->unique()->values()->all();

        $ordersDone = $userIds
            ? Order::query()
                ->whereIn('operator_id', $userIds)
                ->whereIn('status', ['delivered', 'completed'])
                ->selectRaw('operator_id, count(*) as c')
                ->groupBy('operator_id')
                ->pluck('c', 'operator_id')
            : collect();

        $ordersActive = $userIds
            ? Order::query()
                ->whereIn('operator_id', $userIds)
                ->whereIn('status', ['accepted', 'in_progress', 'needs_info'])
                ->selectRaw('operator_id, count(*) as c')
                ->groupBy('operator_id')
                ->pluck('c', 'operator_id')
            : collect();

        $chatMessages = $userIds
            ? Message::query()
                ->whereIn('sender_id', $userIds)
                ->selectRaw('sender_id, count(*) as c')
                ->groupBy('sender_id')
                ->pluck('c', 'sender_id')
            : collect();

        $rows = $paginator->through(fn (StaffAssignment $s) => [
            'id' => $s->id,
            'user_id' => $s->user_id,
            'staff' => $s->user?->full_name ?? '—',
            'name' => $s->user?->name,
            'family' => $s->user?->family,
            'email' => $s->user?->email,
            'mobile' => $s->user?->mobile,
            'user_is_active' => (bool) $s->user?->is_active,
            'permissions' => $s->permissions ?? [],
            'coffeenet' => $s->coffeenet?->name ?? '—',
            'coffeenet_id' => $s->coffeenet_id,
            'position' => $s->position->value,
            'position_label' => $s->position->label(),
            'is_active' => (bool) $s->is_active,
            'approval_status' => $s->approval_status ?? \App\Models\StaffAssignment::APPROVAL_APPROVED,
            'orders_done' => (int) ($ordersDone[$s->user_id] ?? 0),
            'orders_active' => (int) ($ordersActive[$s->user_id] ?? 0),
            'chat_messages' => (int) ($chatMessages[$s->user_id] ?? 0),
            'joined_at' => $s->user?->created_at
                ? fa_date($s->user->created_at, 'Y/m/d')
                : '—',
        ]);

        return response()->json($rows);
    }

    /**
     * مدل حقوق این کارمند در کافی‌نت جاری (برای مودال ویرایش).
     * GET /admin/operators/{assignment}/salary
     */
    public function salary(Request $request, StaffAssignment $assignment): JsonResponse
    {
        $salary = \App\Models\SalarySetting::query()
            ->where('coffeenet_id', $assignment->coffeenet_id)
            ->where('user_id', $assignment->user_id)
            ->first();

        return response()->json([
            'data' => $salary ? [
                'type' => $salary->type->value,
                'rate' => (float) $salary->rate,
                'overtime_rate' => $salary->overtime_rate !== null ? (float) $salary->overtime_rate : null,
                'is_active' => (bool) $salary->is_active,
            ] : null,
        ]);
    }

    /**
     * ویرایش کامل کارمند/اپراتور توسط مدیر کل (درخواست بازخوردی):
     *  - اطلاعات ورود (ایمیل/موبایل/رمز) و وضعیت حساب
     *  - انتقال به کافی‌نت دیگر (بدون از دست رفتن تاریخچه سفارش‌ها)
     *  - سمت، دسترسی‌ها و مدل حقوق
     */
    public function update(Request $request, StaffAssignment $assignment): JsonResponse
    {
        $user = $assignment->user;
        abort_unless((bool) $user, 404, 'کاربر این عضویت یافت نشد.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'family' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,'.$user->id],
            'mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'user_is_active' => ['nullable', 'boolean'],
            'coffeenet_id' => ['required', 'integer', 'exists:coffeenets,id'],
            'position' => ['required', 'in:manager,operator'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'salary_type' => ['nullable', 'in:percent,fixed_per_order,monthly'],
            'salary_rate' => ['nullable', 'numeric', 'min:0'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0'],
        ], [
            'name.required' => 'نام کارمند الزامی است.',
            'email.required' => 'ایمیل ورود الزامی است.',
            'email.email' => 'فرمت ایمیل معتبر نیست.',
            'email.unique' => 'این ایمیل متعلق به کاربر دیگری است.',
            'mobile.regex' => 'فرمت موبایل صحیح نیست (مثل 09123456789).',
            'mobile.unique' => 'این شماره موبایل متعلق به کاربر دیگری است.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
            'coffeenet_id.required' => 'انتخاب کافی‌نت الزامی است.',
            'coffeenet_id.exists' => 'کافی‌نت انتخابی یافت نشد.',
            'position.in' => 'سمت انتخابی معتبر نیست.',
            'salary_type.in' => 'مدل حقوق معتبر نیست.',
        ]);

        $targetNet = Coffeenet::query()->findOrFail((int) $data['coffeenet_id']);
        $moving = (int) $targetNet->id !== (int) $assignment->coffeenet_id;

        // اعتبارسنجی‌های انتقال
        if ($moving) {
            if ($targetNet->status !== \App\Enums\CoffeenetStatus::Approved) {
                return response()->json(['message' => 'کافی‌نت مقصد فعال نیست؛ اپراتور فقط به کافی‌نت‌های فعال قابل انتقال است.'], 422);
            }

            $already = StaffAssignment::query()
                ->where('user_id', $user->id)
                ->where('coffeenet_id', $targetNet->id)
                ->where('is_active', true)
                ->exists();

            if ($already) {
                return response()->json(['message' => 'این کاربر قبلاً عضو فعال کافی‌نت «'.$targetNet->name.'» است.'], 422);
            }
        }

        // قانون تک-کافینت مدیران
        if ($data['position'] === 'manager') {
            $otherNet = $user->staffAssignments()
                ->where('position', 'manager')
                ->where('is_active', true)
                ->where('coffeenet_id', '!=', $targetNet->id)
                ->with('coffeenet:id,name')
                ->first();

            if ($otherNet) {
                return response()->json([
                    'message' => '«'.$user->full_name.'» مدیر کافی‌نت «'.$otherNet->coffeenet?->name.'» است؛ هر مدیر تنها مدیر یک کافی‌نت می‌تواند باشد.',
                ], 422);
            }
        }

        // قانون تک-کافی‌نت اپراتورها: انتقال به کافی‌نت دیگر توسط «تنها مدیر کل» مجاز است،
        // اما هرگز دو عضویت اپراتوری فعال نمی‌سازیم — سایر عضویت‌های اپراتوری این کاربر
        // در کافی‌نت‌های دیگر هنگام انتقال غیرفعال می‌شوند (تاریخچه حفظ می‌شود).
        $conflict = null;

        if ($moving && $data['position'] === 'operator') {
            $conflict = $user->staffAssignments()
                ->where('position', 'operator')
                ->where('is_active', true)
                ->whereKeyNot($assignment->getKey())
                ->with('coffeenet:id,name')
                ->get();
        }

        if (isset($data['salary_type'], $data['salary_rate'])
            && $data['salary_type'] === 'percent' && (float) $data['salary_rate'] > 100) {
            return response()->json(['message' => 'درصد حقوق نمی‌تواند بیش از ۱۰۰ باشد.'], 422);
        }

        $permissions = $data['position'] === 'operator'
            ? OperatorPermissions::filter($data['permissions'] ?? $assignment->permissions ?? OperatorPermissions::defaults())
            : [];

        $old = [
            'user' => $user->only(['name', 'family', 'email', 'mobile', 'is_active']),
            'coffeenet_id' => $assignment->coffeenet_id,
            'position' => $assignment->position->value,
            'permissions' => $assignment->permissions,
            'assignment_active' => (bool) $assignment->is_active,
        ];

        DB::transaction(function () use ($assignment, $user, $data, $targetNet, $moving, $permissions, $conflict) {
            // اطلاعات حساب
            $user->fill([
                'name' => $data['name'],
                'family' => $data['family'] ?? null,
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null,
                'is_active' => array_key_exists('user_is_active', $data) && $data['user_is_active'] !== null
                    ? (bool) $data['user_is_active']
                    : $user->is_active,
            ])->save();

            if (! empty($data['password'])) {
                $user->update(['password' => $data['password']]);
            }

            // همگام‌سازی نقش سراسری با سمت
            if ($data['position'] === 'manager' && ! $user->hasRole('coffeenet_manager')) {
                $user->assignRole('coffeenet_manager');
                $user->removeRole('operator');
            } elseif ($data['position'] === 'operator' && ! $user->hasRole('operator')) {
                $user->assignRole('operator');
                $user->removeRole('coffeenet_manager');
            }

            // تک-کافی‌نت اپراتور: عضویت‌های اپراتوریِ دیگرِ این کاربر غیرفعال شوند
            if ($conflict && $conflict->isNotEmpty()) {
                $conflict->each->update(['is_active' => false]);
            }

            // عضویت: انتقال یا همان کافی‌نت
            $assignment->update([
                'coffeenet_id' => $targetNet->id,
                'position' => $data['position'],
                'permissions' => $permissions,
                'is_active' => array_key_exists('is_active', $data) && $data['is_active'] !== null
                    ? (bool) $data['is_active']
                    : $assignment->is_active,
                'approval_status' => \App\Models\StaffAssignment::APPROVAL_APPROVED,
            ]);

            // مدل حقوق کافی‌نت مقصد (در انتقال یا ارسال فرم)
            if (isset($data['salary_type'], $data['salary_rate'])) {
                \App\Models\SalarySetting::updateOrCreate(
                    ['coffeenet_id' => $targetNet->id, 'user_id' => $user->id],
                    [
                        'type' => $data['salary_type'],
                        'rate' => (float) $data['salary_rate'],
                        'overtime_rate' => $data['overtime_rate'] ?? null,
                        'is_active' => true,
                    ],
                );
            } elseif ($moving) {
                // انتقال بدون فرم حقوق → مدل درصدی پیش‌فرض ۵۰٪ برای ادامهٔ تسویه
                \App\Models\SalarySetting::updateOrCreate(
                    ['coffeenet_id' => $targetNet->id, 'user_id' => $user->id],
                    ['type' => 'percent', 'rate' => 50, 'is_active' => true],
                );
            }
        });

        AuditLogger::log('staff.updated_by_admin', $assignment, $old, [
            'user' => $user->only(['name', 'family', 'email', 'mobile', 'is_active']),
            'coffeenet_id' => $assignment->coffeenet_id,
            'position' => $assignment->position->value,
            'permissions' => $permissions,
        ], 'ویرایش کامل کارمند «'.$user->full_name.'» توسط مدیر کل'
            .($moving ? ' + انتقال به کافی‌نت «'.$targetNet->name.'»' : '')
            .(! empty($data['password']) ? ' + تغییر رمز عبور' : ''));

        return response()->json([
            'message' => 'اطلاعات «'.$user->full_name.'» ذخیره شد'.($moving ? ' و به کافی‌نت «'.$targetNet->name.'» منتقل شد.' : '.'),
        ]);
    }

    /**
     * تایید کارمند در انتظار (ساخته‌شده توسط مدیر کافی‌نت).
     * PATCH /admin/operators/{assignment}/approve
     */
    public function approve(Request $request, StaffAssignment $assignment): JsonResponse
    {
        if (($assignment->approval_status ?? StaffAssignment::APPROVAL_APPROVED) !== StaffAssignment::APPROVAL_PENDING) {
            return response()->json(['message' => 'این عضویت در انتظار تایید نیست.'], 422);
        }

        $old = ['approval_status' => $assignment->approval_status, 'is_active' => $assignment->is_active];

        // تک-کافی‌نت: اگر کاربر اپراتورِ فعال کافی‌نت دیگری است، آن عضویت غیرفعال می‌شود
        $conflicts = StaffAssignment::query()
            ->where('user_id', $assignment->user_id)
            ->where('position', 'operator')
            ->where('is_active', true)
            ->whereKeyNot($assignment->getKey())
            ->get();
        $conflicts->each->update(['is_active' => false]);

        $assignment->update([
            'approval_status' => StaffAssignment::APPROVAL_APPROVED,
            'is_active' => true,
        ]);

        // اطلاع به مدیر کافی‌نت و خود کارمند (v25 — رویدادی + پوش آفلاین)
        app(\App\Services\Notifications\NotificationService::class)
            ->notifyCoffeenetManagersEvent(
                (int) $assignment->coffeenet_id,
                'staff.approval_result',
                [
                    'coffeenet' => $assignment->coffeenet?->name,
                    'result' => 'کارمند «'.$assignment->user?->full_name.'» توسط مدیر کل تایید و فعال شد',
                ],
                ['coffeenet_id' => $assignment->coffeenet_id, 'assignment_id' => $assignment->id],
            );

        app(\App\Services\Notifications\NotificationService::class)
            ->tryNotifyEvent(
                $assignment->user,
                'staff.approval_result',
                [
                    'coffeenet' => $assignment->coffeenet?->name,
                    'result' => 'تایید شد — از این پس عضو فعال کافی‌نت هستید',
                ],
                ['coffeenet_id' => $assignment->coffeenet_id, 'assignment_id' => $assignment->id],
            );

        AuditLogger::log('staff.approved', $assignment->refresh(), $old, null,
            'تایید کارمند «'.$assignment->user?->full_name.'» در کافی‌نت «'.$assignment->coffeenet?->name.'»');

        return response()->json([
            'message' => 'کارمند «'.$assignment->user?->full_name.'» تایید و فعال شد.',
        ]);
    }

    /**
     * رد کارمند در انتظار.
     * PATCH /admin/operators/{assignment}/reject
     */
    public function reject(Request $request, StaffAssignment $assignment): JsonResponse
    {
        if (($assignment->approval_status ?? StaffAssignment::APPROVAL_APPROVED) !== StaffAssignment::APPROVAL_PENDING) {
            return response()->json(['message' => 'این عضویت در انتظار تایید نیست.'], 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ], ['reason.max' => 'دلیل رد حداکثر ۵۰۰ کاراکتر است.']);

        $old = ['approval_status' => $assignment->approval_status, 'is_active' => $assignment->is_active];

        $assignment->update([
            'approval_status' => StaffAssignment::APPROVAL_REJECTED,
            'is_active' => false,
        ]);

        app(\App\Services\Notifications\NotificationService::class)
            ->notifyCoffeenetManagersEvent(
                (int) $assignment->coffeenet_id,
                'staff.approval_result',
                [
                    'coffeenet' => $assignment->coffeenet?->name,
                    'result' => 'درخواست افزودن کارمند «'.$assignment->user?->full_name.'» رد شد'
                        .(! empty($data['reason']) ? ' — دلیل: '.$data['reason'] : ''),
                ],
                ['coffeenet_id' => $assignment->coffeenet_id, 'assignment_id' => $assignment->id],
            );

        app(\App\Services\Notifications\NotificationService::class)
            ->tryNotifyEvent(
                $assignment->user,
                'staff.approval_result',
                [
                    'coffeenet' => $assignment->coffeenet?->name,
                    'result' => 'رد شد'
                        .(! empty($data['reason']) ? ' — دلیل: '.$data['reason'] : ''),
                ],
                ['coffeenet_id' => $assignment->coffeenet_id, 'assignment_id' => $assignment->id],
            );

        AuditLogger::log('staff.rejected', $assignment->refresh(), $old, ['reason' => $data['reason'] ?? null],
            'رد کارمند «'.$assignment->user?->full_name.'» در کافی‌نت «'.$assignment->coffeenet?->name.'»');

        return response()->json([
            'message' => 'درخواست کارمند «'.$assignment->user?->full_name.'» رد شد.',
        ]);
    }
}
