<?php

namespace App\Http\Controllers\Back\Admin;

use App\Enums\CoffeenetStatus;
use App\Enums\OrderStatus;
use App\Enums\StaffPosition;
use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CoffeenetsController extends Controller
{
    public function __construct(
        protected WalletService $wallets,
    ) {
    }

    public function index(): View
    {
        return view('back.admin.coffeenets.index', [
            'organizations' => Organization::orderBy('name')->get(['id', 'name', 'status']),
            'provinces' => \App\Models\Province::orderBy('name')->get(['id', 'name']),
            'referralReward' => (float) \App\Models\ReferralSetting::current()->introduction_reward,
            'referralActive' => (bool) \App\Models\ReferralSetting::current()->is_active,
        ]);
    }

    /** صفحهٔ جزئیات کامل کافی‌نت — همه‌چیز در یک نگاه (آمار/مالی/کارکنان/سفارش‌ها) */
    public function show(Coffeenet $coffeenet): View
    {
        $coffeenet->load([
            'organization:id,name,status',
            'province:id,name',
            'city:id,name',
            'approvedBy:id,name,family',
            'wallet',
        ]);

        // کارکنان: مدیرها ابتدا، سپس بر اساس نام
        $staff = $coffeenet->staffAssignments()
            ->with('user:id,name,family,mobile,email,created_at')
            ->get()
            ->sort(function (StaffAssignment $a, StaffAssignment $b) {
                $pa = $a->position === StaffPosition::Manager ? 0 : 1;
                $pb = $b->position === StaffPosition::Manager ? 0 : 1;

                return $pa <=> $pb
                    ?: strcmp((string) $a->user?->full_name, (string) $b->user?->full_name);
            })
            ->values();

        $orders = Order::query()->where('coffeenet_id', $coffeenet->id);

        $totalOrders = (clone $orders)->count();
        $activeOrders = (clone $orders)->whereIn('status', [
            OrderStatus::Accepted->value, OrderStatus::InProgress->value, OrderStatus::NeedsInfo->value,
        ])->count();
        $doneOrders = (clone $orders)->whereIn('status', [
            OrderStatus::Delivered->value, OrderStatus::Completed->value,
        ])->count();
        $totalSales = (float) (clone $orders)
            ->whereNotIn('status', [
                OrderStatus::PendingPayment->value, OrderStatus::Cancelled->value, OrderStatus::Refunded->value,
            ])
            ->sum('price');

        $recentOrders = (clone $orders)
            ->with([
                'service:id,name',
                'customer:id,name,family',
                'operator:id,name,family',
            ])
            ->latest('id')
            ->limit(10)
            ->get();

        $conversationsCount = Conversation::query()
            ->whereIn('order_id', (clone $orders)->select('id'))
            ->count();

        $broadcastsCount = $coffeenet->broadcasts()->count();
        $recentBroadcasts = $coffeenet->broadcasts()
            ->with('order:id,order_number,service_id', 'order.service:id,name')
            ->latest('id')
            ->limit(10)
            ->get();

        $wallet = $coffeenet->wallet;
        $balance = $this->wallets->balance($coffeenet);
        $transactions = $wallet
            ? $wallet->transactions()->latest('id')->limit(10)->get()
            : collect();
        $withdrawals = $wallet
            ? Withdrawal::query()->where('wallet_id', $wallet->id)->latest('id')->limit(5)->get()
            : collect();
        $pendingWithdrawals = (int) ($wallet
            ? Withdrawal::query()->where('wallet_id', $wallet->id)->where('status', 'pending')->count()
            : 0);

        // پیش‌نمایش پاداش معرفی (اگر با تأیید این کافی‌نت پرداخت می‌شود)
        $referral = \App\Models\ReferralSetting::current();
        $rewardPreview = ($coffeenet->organization_id
            && ! $coffeenet->introduction_reward_paid
            && $coffeenet->status !== CoffeenetStatus::Approved
            && $referral->is_active
            && (float) $referral->introduction_reward > 0)
            ? (float) $referral->introduction_reward
            : 0.0;

        return view('back.admin.coffeenets.show', [
            'coffeenet' => $coffeenet,
            'staff' => $staff,
            'activeManagers' => $staff->where('is_active', true)->where('position', StaffPosition::Manager)->count(),
            'activeOperators' => $staff->where('is_active', true)->where('position', StaffPosition::Operator)->count(),
            'stats' => [
                'total_orders' => $totalOrders,
                'active_orders' => $activeOrders,
                'done_orders' => $doneOrders,
                'total_sales' => $totalSales,
                'balance' => $balance,
                'pending_withdrawals' => $pendingWithdrawals,
                'conversations' => $conversationsCount,
                'broadcasts' => $broadcastsCount,
            ],
            'recentOrders' => $recentOrders,
            'recentBroadcasts' => $recentBroadcasts,
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
            'rewardPreview' => $rewardPreview,
        ]);
    }

    /** سری روند کاری کافی‌نت (چارت بازه‌ای) — GET /admin/coffeenets/{coffeenet}/trend */
    public function trend(Request $request, Coffeenet $coffeenet, \App\Services\Analytics\WorkTrendService $trend): JsonResponse
    {
        [$from, $to, $resolution] = \App\Services\Analytics\WorkTrendService::resolveRange(
            $request->query('preset'),
            $request->query('from'),
            $request->query('to')
        );

        return response()->json(
            $trend->series((string) $request->query('preset', 'daily'), $from, $to, $resolution, [
                'coffeenet_id' => $coffeenet->id,
            ])
        );
    }

    /** لیست کافی‌نت‌ها (AJAX + جستجو + فیلتر وضعیت/سازمان + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $query = Coffeenet::query()
            ->with(['organization:id,name', 'province:id,name', 'city:id,name'])
            ->withCount([
                'staffAssignments as managers_count' => fn ($q) => $q->where('position', 'manager')->where('is_active', true),
            ]);

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereHas('organization', fn ($o) => $o->where('name', 'like', "%{$q}%"));
            });
        }

        if ($status = (string) $request->query('status')) {
            if (in_array($status, ['pending', 'approved', 'rejected', 'suspended'], true)) {
                $query->where('status', $status);
            }
        }

        // فیلتر سازمان: عدد مثبت = سازمان مشخص، 0 = فقط مستقل‌ها
        $rawOrg = $request->query('organization_id');
        if ($rawOrg !== null && $rawOrg !== '') {
            $orgId = (int) $rawOrg;
            if ($orgId > 0) {
                $query->where('organization_id', $orgId);
            } else {
                $query->whereNull('organization_id');
            }
        }

        $paginator = $query->latest('id')->paginate(25);

        $rows = $paginator->through(fn (Coffeenet $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'organization' => $c->organization?->name,
            'organization_id' => $c->organization_id,
            'is_independent' => $c->organization_id === null,
            'province' => $c->province?->name,
            'city' => $c->city?->name,
            'address' => $c->address,
            'managers_count' => $c->managers_count,
            'manager' => $c->managerUser()?->full_name,
            'manager_user_id' => $c->managerUser()?->id,
            'manager_name' => $c->managerUser()?->name,
            'manager_family' => $c->managerUser()?->family,
            'manager_email' => $c->managerUser()?->email,
            'manager_mobile' => $c->managerUser()?->mobile,
            'manager_is_active' => (bool) $c->managerUser()?->is_active,
            'introduction_reward_paid' => $c->introduction_reward_paid,
            'status' => [
                'value' => $c->status->value,
                'label' => $c->status->label(),
                'color' => $c->status->color(),
            ],
            'approved_at' => $c->approved_at?->diffForHumans(now(), ['locale' => 'fa']) ?? '—',
            'created_at' => $c->created_at?->diffForHumans(now(), ['locale' => 'fa']) ?? '—',
        ]);

        return response()->json($rows);
    }

    /** ایجاد کافی‌نت + کاربر مدیر + عضویت (اتمیک) */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:15'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:1000'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'approve' => ['nullable', 'boolean'],
            // مدیر کافی‌نت
            'manager_name' => ['required', 'string', 'max:100'],
            'manager_family' => ['nullable', 'string', 'max:100'],
            'manager_email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'manager_mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile'],
            'manager_password' => ['required', 'string', 'min:8'],
        ], [
            'name.required' => 'نام کافی‌نت الزامی است.',
            'manager_name.required' => 'نام مدیر کافی‌نت الزامی است.',
            'manager_email.required' => 'ایمیل مدیر کافی‌نت الزامی است.',
            'manager_email.unique' => 'این ایمیل قبلاً در سیستم ثبت شده است.',
            'manager_mobile.regex' => 'فرمت موبایل صحیح نیست (مثل 09123456789).',
            'manager_mobile.unique' => 'این موبایل قبلاً ثبت شده است.',
            'manager_password.required' => 'رمز عبور مدیر الزامی است.',
            'manager_password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
            'organization_id.exists' => 'سازمان انتخابی معتبر نیست.',
        ]);

        $coffeenet = DB::transaction(function () use ($data, $request) {
            $manager = User::create([
                'name' => $data['manager_name'],
                'family' => $data['manager_family'] ?? null,
                'email' => $data['manager_email'],
                'mobile' => $data['manager_mobile'] ?? null,
                'password' => $data['manager_password'],
                'is_active' => true,
            ]);
            $manager->assignRole('coffeenet_manager');

            $coffeenet = Coffeenet::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'province_id' => $data['province_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'address' => $data['address'] ?? null,
                'organization_id' => $data['organization_id'] ?? null,
                'status' => CoffeenetStatus::Pending,
            ]);

            StaffAssignment::create([
                'user_id' => $manager->id,
                'coffeenet_id' => $coffeenet->id,
                'position' => 'manager',
                'assigned_by' => $request->user()->id,
                'is_active' => true,
            ]);

            if (! empty($data['approve'])) {
                $this->approveInternal($coffeenet, $request->user());
            }

            return $coffeenet->refresh();
        });

        AuditLogger::log('coffeenet.created', $coffeenet, null, [
            'name' => $coffeenet->name,
            'organization_id' => $coffeenet->organization_id,
            'status' => $coffeenet->status->value,
            'manager' => $data['manager_email'],
        ], 'ایجاد کافی‌نت و کاربر مدیر آن');

        $extra = $coffeenet->organization
            ? ' (زیرمجموعه سازمان «'.$coffeenet->organization->name.'»)'
            : ' (مستقل)';

        return response()->json([
            'message' => $coffeenet->status === CoffeenetStatus::Approved
                ? 'کافی‌نت «'.$coffeenet->name.'» ایجاد و تأیید شد'.$extra
                : 'کافی‌نت «'.$coffeenet->name.'» با وضعیت «در انتظار تأیید» ثبت شد'.$extra,
        ]);
    }

    /** ویرایش کافی‌نت (AJAX) */
    public function update(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        $manager = $coffeenet->managerUser();
        $managerId = (int) ($manager?->id ?: 0);
        $creatingManager = $manager === null;

        // کافی‌نت‌های معرفی‌شده توسط سازمان هنوز کاربر مدیر ندارند؛
        // در این حالت اطلاعات ورود (نام/ایمیل/رمز) الزامی است و کاربر مدیر ساخته می‌شود.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:15'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:1000'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'manager_name' => [$creatingManager ? 'required' : 'nullable', 'string', 'max:100'],
            'manager_family' => ['nullable', 'string', 'max:100'],
            'manager_email' => [$creatingManager ? 'required' : 'nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($managerId)],
            'manager_mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'max:11', Rule::unique('users', 'mobile')->ignore($managerId)],
            'manager_password' => [$creatingManager ? 'required' : 'nullable', 'string', 'min:8'],
            'manager_is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'نام کافی‌نت الزامی است.',
            'manager_name.required' => 'نام مدیر کافی‌نت الزامی است (این کافی‌نت هنوز کاربر مدیر ندارد).',
            'manager_email.required' => 'ایمیل ورود مدیر الزامی است (این کافی‌نت هنوز کاربر مدیر ندارد).',
            'manager_email.email' => 'ایمیل مدیر کافی‌نت معتبر نیست.',
            'manager_email.unique' => 'این ایمیل متعلق به کاربر دیگری است.',
            'manager_mobile.regex' => 'فرمت موبایل مدیر کافی‌نت صحیح نیست (09xxxxxxxxx).',
            'manager_mobile.unique' => 'این موبایل متعلق به کاربر دیگری است.',
            'manager_password.required' => 'رمز عبور مدیر الزامی است (این کافی‌نت هنوز کاربر مدیر ندارد).',
            'manager_password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
        ]);

        $old = $coffeenet->only(['name', 'phone', 'province_id', 'city_id', 'address', 'organization_id']);
        $managerOld = $manager ? $manager->only(['name', 'family', 'email', 'mobile', 'is_active']) : null;

        DB::transaction(function () use ($coffeenet, $data, $manager, $creatingManager, $request) {
            $coffeenet->fill(collect($data)->only([
                'name', 'phone', 'province_id', 'city_id', 'address', 'organization_id',
            ])->all())->save();

            if ($manager) {
                $manager->fill([
                    'name' => $data['manager_name'] ?? $manager->name,
                    'family' => $data['manager_family'] ?? $manager->family,
                    'email' => $data['manager_email'] ?? $manager->email,
                    'mobile' => $data['manager_mobile'] ?? $manager->mobile,
                    'is_active' => array_key_exists('manager_is_active', $data) && $data['manager_is_active'] !== null
                        ? (bool) $data['manager_is_active']
                        : $manager->is_active,
                ])->save();

                if (! empty($data['manager_password'])) {
                    $manager->update(['password' => $data['manager_password']]);
                }
            } elseif ($creatingManager) {
                // v28 — ساخت کاربر مدیر برای کافی‌نتی که هنوز مدیر ندارد
                // (کافی‌نت‌های معرفی‌شده توسط سازمان؛ ریشهٔ نرسیدن اعلان‌ها به کافی‌نت)
                $newManager = User::create([
                    'name' => $data['manager_name'],
                    'family' => $data['manager_family'] ?? null,
                    'email' => $data['manager_email'],
                    'mobile' => $data['manager_mobile'] ?? null,
                    'password' => $data['manager_password'],
                    'is_active' => true,
                ]);
                $newManager->assignRole('coffeenet_manager');

                StaffAssignment::create([
                    'user_id' => $newManager->id,
                    'coffeenet_id' => $coffeenet->id,
                    'position' => 'manager',
                    'assigned_by' => $request->user()->id,
                    'is_active' => true,
                ]);
            }
        });

        AuditLogger::log('coffeenet.updated', $coffeenet, array_merge($old, ['manager' => $managerOld]),
            array_merge($coffeenet->only(['name', 'phone', 'province_id', 'city_id', 'address', 'organization_id']),
                ['manager' => $coffeenet->managerUser()?->only(['name', 'family', 'email', 'mobile', 'is_active'])]),
            'ویرایش اطلاعات کافی‌نت'.(! empty($data['manager_password']) ? ' + تغییر رمز مدیر' : '')
                .($creatingManager ? ' + ساخت کاربر مدیر کافی‌نت' : ''));

        return response()->json([
            'message' => $creatingManager
                ? 'اطلاعات کافی‌نت ذخیره و کاربر مدیر آن ساخته شد؛ از این پس اعلان‌های کافی‌نت برای او ارسال می‌شود.'
                : 'تغییرات کافی‌نت ذخیره شد.',
        ]);
    }

    /** تغییر وضعیت کافی‌نت (AJAX) — تأیید شامل پرداخت پاداش معرفی */
    public function status(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,suspended'],
        ]);

        $target = CoffeenetStatus::from($data['status']);
        $old = ['status' => $coffeenet->status->value];
        $reward = 0.0;

        DB::transaction(function () use ($coffeenet, $target, $request, &$reward) {
            if ($target === CoffeenetStatus::Approved) {
                $reward = $this->approveInternal($coffeenet, $request->user());
            } else {
                $coffeenet->update(['status' => $target]);
            }
        });

        AuditLogger::log('coffeenet.status_changed', $coffeenet->refresh(), $old,
            ['status' => $target->value, 'reward' => $reward],
            'تغییر وضعیت کافی‌نت به «'.$target->label().'»'
            .($target === CoffeenetStatus::Approved && $reward > 0
                ? " + پرداخت پاداش معرفی {$reward} تومان به سازمان"
                : ''));

        $message = 'وضعیت کافی‌نت به «'.$target->label().'» تغییر کرد.';
        if ($target === CoffeenetStatus::Approved && $reward > 0) {
            $message .= " پاداش معرفی {$reward} تومان به کیف پول سازمان واریز شد.";
        }

        return response()->json([
            'message' => $message,
            'status' => [
                'value' => $target->value,
                'label' => $target->label(),
                'color' => $target->color(),
            ],
            'introduction_reward_paid' => $coffeenet->refresh()->introduction_reward_paid,
        ]);
    }

    /**
     * تأیید داخلی: ثبت تأیید + پرداخت یک‌باره پاداش معرفی به سازمان معرف.
     *
     * @return float مبلغ پاداش پرداخت‌شده (۰ = بدون پرداخت)
     */
    protected function approveInternal(Coffeenet $coffeenet, User $approver): float
    {
        if ($coffeenet->status === CoffeenetStatus::Approved) {
            return 0.0;
        }

        $coffeenet->update([
            'status' => CoffeenetStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);

        if (! $coffeenet->organization_id || $coffeenet->introduction_reward_paid) {
            return 0.0;
        }

        $referral = \App\Models\ReferralSetting::current();

        if (! $referral->is_active || (float) $referral->introduction_reward <= 0) {
            return 0.0;
        }

        $amount = (float) $referral->introduction_reward;
        $this->wallets->credit(
            $coffeenet->organization,
            $amount,
            'reward',
            $coffeenet->id,
            'پاداش معرفی کافی‌نت «'.$coffeenet->name.'»',
            ['coffeenet_id' => $coffeenet->id, 'approved_by' => $approver->id],
        );

        $coffeenet->update(['introduction_reward_paid' => true]);

        AuditLogger::log('referral.reward_paid', $coffeenet, null, [
            'organization_id' => $coffeenet->organization_id,
            'amount' => $amount,
            'coffeenet' => $coffeenet->name,
        ], "پرداخت پاداش معرفی {$amount} تومان");

        return $amount;
    }
}
