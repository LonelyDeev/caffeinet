<?php

namespace App\Http\Controllers\Back\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Order;
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

class OrganizationsController extends Controller
{
    public function __construct(
        protected WalletService $wallets,
    ) {
    }

    public function index(): View
    {
        return view('back.admin.organizations.index', [
            'provinces' => \App\Models\Province::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** لیست سازمان‌ها (AJAX + جستجو + فیلتر وضعیت + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $query = Organization::query()
            ->with(['owner:id,name,family,email,mobile,is_active', 'province:id,name', 'city:id,name']);

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('national_id', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('family', 'like', "%{$q}%"));
            });
        }

        if ($status = (string) $request->query('status')) {
            if (in_array($status, ['pending', 'approved', 'rejected', 'suspended'], true)) {
                $query->where('status', $status);
            }
        }

        $paginator = $query->latest('id')->paginate(25);

        $rows = $paginator->through(fn (Organization $org) => [
            'id' => $org->id,
            'name' => $org->name,
            'type' => $org->type === 'legal' ? 'حقوقی' : 'حقیقی',
            'national_id' => $org->national_id,
            'phone' => $org->phone,
            'province' => $org->province?->name,
            'city' => $org->city?->name,
            'address' => $org->address,
            'owner' => $org->owner?->full_name,
            'owner_email' => $org->owner?->email,
            'owner_name' => $org->owner?->name,
            'owner_family' => $org->owner?->family,
            'owner_mobile' => $org->owner?->mobile,
            'owner_is_active' => (bool) $org->owner?->is_active,
            'coffeenets_count' => $org->coffeenets()->count(),
            'wallet_balance' => (float) $org->wallet?->balance ?? 0.0,
            'status' => [
                'value' => $org->status->value,
                'label' => $org->status->label(),
                'color' => $org->status->color(),
            ],
            'created_at' => $org->created_at?->diffForHumans(now(), ['locale' => 'fa']) ?? '—',
        ]);

        return response()->json($rows);
    }

    /**
     * صفحهٔ جزئیات سازمان (فاز ۱۰) — آمار، کافی‌نت‌ها، مالی
     */
    public function show(Organization $organization): View
    {
        $organization->load(['owner:id,name,family,mobile,email', 'province:id,name', 'city:id,name']);

        $netIds = $organization->coffeenets()->pluck('id');

        // آمار سفارش‌ها: همهٔ وضعیت‌ها + مجموع فروش (بدون لغو/بازگشت وجه/در انتظار پرداخت)
        $excluded = [OrderStatus::PendingPayment->value, OrderStatus::Cancelled->value, OrderStatus::Refunded->value];
        $validStats = Order::query()
            ->whereIn('coffeenet_id', $netIds)
            ->whereNotIn('status', $excluded)
            ->selectRaw('coffeenet_id, count(*) as orders_count, coalesce(sum(price), 0) as sales_sum')
            ->groupBy('coffeenet_id')
            ->get()
            ->keyBy('coffeenet_id');

        $allOrdersCount = Order::query()
            ->whereIn('coffeenet_id', $netIds)
            ->selectRaw('coffeenet_id, count(*) as c')
            ->groupBy('coffeenet_id')
            ->pluck('c', 'coffeenet_id');

        $coffeenets = $organization->coffeenets()
            ->with(['province:id,name', 'city:id,name'])
            ->withCount([
                'staffAssignments as staff_count',
                'staffAssignments as managers_count' => fn ($q) => $q->where('position', 'manager'),
            ])
            ->orderByDesc('id')
            ->get();

        // کارکنان کل با تفکیک سمت
        $staffTotal = StaffAssignment::query()->whereIn('coffeenet_id', $netIds)->count();
        $staffManagers = $this->staffByPosition($netIds, 'manager')->count();
        $staffOperators = $this->staffByPosition($netIds, 'operator')->count();

        // کیف پول
        $balance = $this->wallets->balance($organization);
        $wallet = $organization->wallet()->first();
        $transactions = $wallet
            ? $wallet->transactions()->latest('id')->limit(10)->get()
            : collect();

        // برداشت‌ها
        $withdrawals = Withdrawal::query()
            ->whereHas('wallet', fn ($w) => $w
                ->where('holder_type', Organization::class)
                ->where('holder_id', $organization->id))
            ->with('requester:id,name,family')
            ->latest('id')
            ->limit(5)
            ->get();
        $pendingWithdrawals = $withdrawals->where('status', 'pending');

        return view('back.admin.organizations.show', [
            'organization' => $organization,
            'coffeenets' => $coffeenets,
            'validStats' => $validStats,
            'allOrdersCount' => $allOrdersCount,
            'staffTotal' => $staffTotal,
            'staffManagers' => $staffManagers,
            'staffOperators' => $staffOperators,
            'ordersTotal' => (int) $allOrdersCount->sum(),
            'ordersValid' => (int) $validStats->sum('orders_count'),
            'salesTotal' => (float) $validStats->sum('sales_sum'),
            'balance' => $balance,
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
            'pendingWithdrawals' => $pendingWithdrawals,
        ]);
    }

    /** کوئری کارکنان بر اساس سمت (فاز ۱۰ — جزئیات سازمان) */
    protected function staffByPosition($netIds, string $position): object
    {
        return StaffAssignment::query()
            ->whereIn('coffeenet_id', $netIds)
            ->where('position', $position);
    }

    /** ایجاد سازمان + کاربر مدیر سازمان + کیف پول (همه اتمیک) */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:legal,individual'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:15'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:pending,approved'],
            'note' => ['nullable', 'string', 'max:1000'],
            // مدیر سازمان
            'owner_name' => ['required', 'string', 'max:100'],
            'owner_family' => ['nullable', 'string', 'max:100'],
            'owner_email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'owner_mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile'],
            'owner_password' => ['required', 'string', 'min:8'],
        ], [
            'name.required' => 'نام سازمان الزامی است.',
            'owner_name.required' => 'نام مدیر سازمان الزامی است.',
            'owner_email.required' => 'ایمیل مدیر سازمان الزامی است.',
            'owner_email.unique' => 'این ایمیل قبلاً در سیستم ثبت شده است.',
            'owner_mobile.regex' => 'فرمت موبایل صحیح نیست (مثل 09123456789).',
            'owner_mobile.unique' => 'این موبایل قبلاً ثبت شده است.',
            'owner_password.required' => 'رمز عبور مدیر سازمان الزامی است.',
            'owner_password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
            'city_id.exists' => 'شهرستان انتخابی معتبر نیست.',
        ]);

        $org = DB::transaction(function () use ($data, $request) {
            $owner = User::create([
                'name' => $data['owner_name'],
                'family' => $data['owner_family'] ?? null,
                'email' => $data['owner_email'],
                'mobile' => $data['owner_mobile'] ?? null,
                'password' => $data['owner_password'],
                'is_active' => true,
            ]);
            $owner->assignRole('org_manager');

            $org = Organization::create([
                'owner_id' => $owner->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'national_id' => $data['national_id'] ?? null,
                'phone' => $data['phone'] ?? null,
                'province_id' => $data['province_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => $data['status'],
                'verified_at' => $data['status'] === 'approved' ? now() : null,
                'note' => $data['note'] ?? null,
            ]);

            // کیف پول سازمان
            \App\Models\Wallet::firstOrCreate(
                ['holder_type' => Organization::class, 'holder_id' => $org->id],
                ['balance' => 0],
            );

            return $org;
        });

        AuditLogger::log('organization.created', $org, null, [
            'name' => $org->name,
            'type' => $org->type,
            'status' => $org->status->value,
            'owner' => $data['owner_email'],
        ], 'ایجاد سازمان و کاربر مدیر آن');

        return response()->json([
            'message' => $data['status'] === 'approved'
                ? 'سازمان «'.$org->name.'» ایجاد و تأیید شد. ورود مدیر: '.$data['owner_email']
                : 'سازمان «'.$org->name.'» با وضعیت «در انتظار بررسی» ایجاد شد.',
        ]);
    }

    /** ویرایش سازمان (AJAX) */
    public function update(Request $request, Organization $organization): JsonResponse
    {
        $ownerId = (int) $organization->owner_id ?: 0;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:legal,individual'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:15'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'owner_name' => ['nullable', 'string', 'max:100'],
            'owner_family' => ['nullable', 'string', 'max:100'],
            'owner_email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($ownerId)],
            'owner_mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'max:11', Rule::unique('users', 'mobile')->ignore($ownerId)],
            'owner_password' => ['nullable', 'string', 'min:8'],
            'owner_is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'نام سازمان الزامی است.',
            'owner_email.email' => 'ایمیل مدیر سازمان معتبر نیست.',
            'owner_email.unique' => 'این ایمیل متعلق به کاربر دیگری است.',
            'owner_mobile.regex' => 'فرمت موبایل مدیر سازمان صحیح نیست (09xxxxxxxxx).',
            'owner_mobile.unique' => 'این موبایل متعلق به کاربر دیگری است.',
            'owner_password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
        ]);

        $old = $organization->only(['name', 'type', 'national_id', 'phone', 'province_id', 'city_id', 'address', 'note']);
        $owner = $organization->owner;
        $ownerOld = $owner ? $owner->only(['name', 'family', 'email', 'mobile', 'is_active']) : [];

        DB::transaction(function () use ($organization, $data, $owner) {
            $organization->fill(collect($data)->only([
                'name', 'type', 'national_id', 'phone', 'province_id', 'city_id', 'address', 'note',
            ])->all())->save();

            if ($owner) {
                $ownerFields = collect($data)->only([
                    'owner_name', 'owner_family', 'owner_email', 'owner_mobile', 'owner_is_active',
                ])->filter(fn ($v) => $v !== null)->all();

                if ($ownerFields) {
                    $owner->fill([
                        'name' => $data['owner_name'] ?? $owner->name,
                        'family' => $data['owner_family'] ?? $owner->family,
                        'email' => $data['owner_email'] ?? $owner->email,
                        'mobile' => $data['owner_mobile'] ?? $owner->mobile,
                        'is_active' => array_key_exists('owner_is_active', $data) && $data['owner_is_active'] !== null
                            ? (bool) $data['owner_is_active']
                            : $owner->is_active,
                    ])->save();
                }

                if (! empty($data['owner_password'])) {
                    $owner->update(['password' => $data['owner_password']]);
                }
            }
        });

        AuditLogger::log('organization.updated', $organization, array_merge($old, ['owner' => $ownerOld]),
            array_merge($organization->only(['name', 'type', 'national_id', 'phone', 'province_id', 'city_id', 'address', 'note']),
                ['owner' => $owner?->only(['name', 'family', 'email', 'mobile', 'is_active'])]),
            'ویرایش اطلاعات سازمان'.(! empty($data['owner_password']) ? ' + تغییر رمز مدیر' : ''));

        return response()->json(['message' => 'تغییرات سازمان ذخیره شد.']);
    }

    /** تغییر وضعیت: تأیید / رد / تعلیق / بازگشت به انتظار (AJAX) */
    public function status(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,suspended'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = OrganizationStatus::from($data['status']);
        $old = ['status' => $organization->status->value];

        DB::transaction(function () use ($organization, $target, $data) {
            $organization->update([
                'status' => $target,
                'verified_at' => $target === OrganizationStatus::Approved ? now() : $organization->verified_at,
                'note' => $data['note'] ?? $organization->note,
            ]);

            // تعلیق سازمان → مدیر سازمان هم از ورود محروم شود
            if ($target === OrganizationStatus::Suspended && $organization->owner) {
                $organization->owner->update(['is_active' => false]);
            } elseif (in_array($target, [OrganizationStatus::Approved, OrganizationStatus::Pending], true) && $organization->owner) {
                $organization->owner->update(['is_active' => true]);
            }
        });

        AuditLogger::log('organization.status_changed', $organization, $old,
            ['status' => $target->value, 'note' => $data['note'] ?? null],
            'تغییر وضعیت سازمان به «'.$target->label().'»');

        return response()->json([
            'message' => 'وضعیت سازمان به «'.$target->label().'» تغییر کرد.',
            'status' => [
                'value' => $target->value,
                'label' => $target->label(),
                'color' => $target->color(),
            ],
        ]);
    }
}
