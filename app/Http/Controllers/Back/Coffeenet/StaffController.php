<?php

namespace App\Http\Controllers\Back\Coffeenet;

use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\SalarySetting;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Settings\SettingsService;
use App\Support\OperatorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffController extends Controller
{
    use WorksInCoffeenet;

    /** صفحه کارمندان */
    public function index(Request $request): View
    {
        $this->currentCoffeenet($request);

        return view('back.coffeenet.staff.index', [
            'permissionCatalog' => OperatorPermissions::CATALOG,
            'permissionDefaults' => OperatorPermissions::defaults(),
        ]);
    }

    /** لیست کارمندان کافی‌نت جاری (AJAX + جستجو + فیلتر + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $coffeenet = $this->currentCoffeenet($request);

        $query = StaffAssignment::query()
            ->where('coffeenet_id', $coffeenet->id)
            ->with([
                'user:id,name,family,email,mobile,last_login_at,is_active',
                'salarySetting' => fn ($q) => $q->where('salary_settings.coffeenet_id', $coffeenet->id),
            ]);

        if ($q = trim((string) $request->query('q'))) {
            $query->whereHas('user', function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('family', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%");
            });
        }

        if ($position = (string) $request->query('position')) {
            if (in_array($position, ['manager', 'operator'], true)) {
                $query->where('position', $position);
            }
        }

        if ($request->query('status') !== null && $request->query('status') !== '') {
            if ($request->query('status') === 'pending') {
                $query->where('approval_status', 'pending');
            } else {
                $query->where('is_active', $request->query('status') === '1');
            }
        }

        $paginator = $query->orderByDesc('id')->paginate(25);

        $rows = $paginator->through(function (StaffAssignment $s) use ($request) {
            $salary = $s->salarySetting;

            return [
                'id' => $s->id,
                'name' => $s->user->name,
                'family' => $s->user->family,
                'full_name' => $s->user->full_name,
                'email' => $s->user->email,
                'mobile' => $s->user->mobile,
                'position' => [
                    'value' => $s->position->value,
                    'label' => $s->position->label(),
                ],
                'is_active' => $s->is_active,
                'user_active' => (bool) $s->user->is_active,
                'is_self' => $s->user_id === $request->user()->id,
                'approval_status' => $s->approval_status ?? StaffAssignment::APPROVAL_APPROVED,
                'permissions' => $s->permissions ?? [],
                'permissions_count' => is_array($s->permissions) ? count($s->permissions) : 0,
                'salary' => $salary ? [
                    'type' => $salary->type->value,
                    'type_label' => $salary->type->label(),
                    'rate' => (float) $salary->rate,
                    'overtime_rate' => $salary->overtime_rate !== null ? (float) $salary->overtime_rate : null,
                    'is_active' => (bool) $salary->is_active,
                ] : null,
                'last_login_at' => $s->user->last_login_at?->diffForHumans(now(), ['locale' => 'fa']) ?? '—',
                'created_at' => $s->created_at?->diffForHumans(now(), ['locale' => 'fa']) ?? '—',
            ];
        });

        return response()->json($rows);
    }

    /** افزودن کارمند (کاربر جدید یا کاربر موجود) + تنظیم حقوق + دسترسی‌ها
     *
     * قواعد:
     *  - هر اپراتور فقط در یک کافی‌نت فعالیت می‌کند (تک-کافی‌نت)
     *  - سیاست تایید از تنظیمات مدیر کل خوانده می‌شود:
     *      staff.hiring.mode = approval → عضویت «در انتظار تایید مدیر کل» ساخته می‌شود
     *      staff.hiring.mode = auto (پیش‌فرض) → تایید خودکار و فعال
     */
    public function store(Request $request, SettingsService $settings, NotificationService $notifications): JsonResponse
    {
        $coffeenet = $this->currentCoffeenet($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'family' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile'],
            'password' => ['nullable', 'string', 'min:8'],
            'position' => ['required', 'in:manager,operator'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'salary_type' => ['required', 'in:percent,fixed_per_order,monthly'],
            'salary_rate' => ['required', 'numeric', 'min:0'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0'],
        ], [
            'name.required' => 'نام کارمند الزامی است.',
            'email.required' => 'ایمیل کارمند الزامی است.',
            'email.email' => 'فرمت ایمیل معتبر نیست.',
            'mobile.regex' => 'فرمت موبایل صحیح نیست (مثل 09123456789).',
            'mobile.unique' => 'این شماره موبایل قبلاً برای کاربر دیگری ثبت شده است.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
            'position.in' => 'سمت انتخابی معتبر نیست.',
            'salary_type.in' => 'مدل حقوق معتبر نیست.',
            'salary_rate.required' => 'مقدار حقوق الزامی است.',
            'salary_rate.numeric' => 'مقدار حقوق باید عدد باشد.',
        ]);

        if ($data['salary_type'] === 'percent' && (float) $data['salary_rate'] > 100) {
            return response()->json(['message' => 'درصد حقوق نمی‌تواند بیش از ۱۰۰ باشد.'], 422);
        }

        $existing = User::query()->where('email', $data['email'])->first();

        if ($existing && $existing->staffAssignments()->where('coffeenet_id', $coffeenet->id)->exists()) {
            return response()->json(['message' => 'این کاربر قبلاً به این کافی‌نت اضافه شده است.'], 422);
        }

        // قانون تک-کافی‌نت اپراتورها: هر اپراتور فقط در یک کافی‌نت فعالیت می‌کند.
        // انتقال اپراتور بین کافی‌نت‌ها فقط توسط مدیر کل انجام می‌شود.
        if ($existing && $data['position'] === 'operator') {
            $otherNet = $existing->staffAssignments()
                ->where('position', 'operator')
                ->where('is_active', true)
                ->where('coffeenet_id', '!=', $coffeenet->id)
                ->with('coffeenet:id,name')
                ->first();

            if ($otherNet) {
                return response()->json([
                    'message' => '«'.$existing->full_name.'» اپراتور فعال کافی‌نت «'.$otherNet->coffeenet?->name.'» است؛ هر اپراتور فقط در یک کافی‌نت می‌تواند فعالیت کند. انتقال فقط توسط مدیر کل انجام می‌شود.',
                ], 422);
            }
        }

        // قانون تک-کافینت مدیران: هر مدیر فقط مدیر یک کافی‌نت است
        if ($data['position'] === 'manager' && $existing) {
            $otherNet = $existing->staffAssignments()
                ->where('position', 'manager')
                ->where('is_active', true)
                ->where('coffeenet_id', '!=', $coffeenet->id)
                ->with('coffeenet:id,name')
                ->first();

            if ($otherNet) {
                return response()->json([
                    'message' => '«'.$existing->full_name.'» پیش‌تر مدیر کافی‌نت «'.$otherNet->coffeenet?->name.'» است؛ هر مدیر تنها مدیر یک کافی‌نت می‌تواند باشد.',
                ], 422);
            }
        }

        if (! $existing && empty($data['password'])) {
            return response()->json([
                'errors' => ['password' => ['برای کارمند جدید تعیین رمز عبور الزامی است.']],
                'message' => 'برای کارمند جدید تعیین رمز عبور الزامی است.',
            ], 422);
        }

        $permissions = $data['position'] === 'operator'
            ? OperatorPermissions::filter($data['permissions'] ?? OperatorPermissions::defaults())
            : [];

        // سیاست تایید کارمندان (تنظیم مدیر کل):
        // approval → عضویت غیرفعال + «در انتظار تایید»؛ auto → فعال + تاییدشده
        $needsApproval = $settings->get('staff.hiring.mode', 'auto') === 'approval';
        $approvalStatus = $needsApproval
            ? StaffAssignment::APPROVAL_PENDING
            : StaffAssignment::APPROVAL_APPROVED;

        [$assignment, $user, $isNewUser] = DB::transaction(function () use ($request, $data, $coffeenet, $existing, $permissions, $approvalStatus, $needsApproval) {
            if ($existing) {
                // کاربر موجود → فقط اتصال به این کافی‌نت
                $user = $existing;
                $user->fill(array_filter([
                    'name' => $data['name'],
                    'family' => $data['family'] ?? null,
                    'mobile' => $data['mobile'] ?? null,
                ], fn ($v) => $v !== null))->save();

                if (! empty($data['password'])) {
                    $user->update(['password' => $data['password']]);
                }

                $isNewUser = false;
            } else {
                $user = User::create([
                    'name' => $data['name'],
                    'family' => $data['family'] ?? null,
                    'email' => $data['email'],
                    'mobile' => $data['mobile'] ?? null,
                    'password' => $data['password'],
                    'is_active' => true,
                ]);
                $user->assignRole($data['position'] === 'manager' ? 'coffeenet_manager' : 'operator');

                $isNewUser = true;
            }

            $assignment = StaffAssignment::create([
                'user_id' => $user->id,
                'coffeenet_id' => $coffeenet->id,
                'position' => $data['position'],
                'permissions' => $permissions,
                'assigned_by' => $request->user()->id,
                'is_active' => ! $needsApproval,
                'approval_status' => $approvalStatus,
            ]);

            SalarySetting::updateOrCreate(
                ['coffeenet_id' => $coffeenet->id, 'user_id' => $user->id],
                [
                    'type' => $data['salary_type'],
                    'rate' => $data['salary_rate'],
                    'overtime_rate' => $data['overtime_rate'] ?? null,
                    'is_active' => true,
                ],
            );

            return [$assignment, $user, $isNewUser];
        });

        AuditLogger::log('staff.created', $assignment, null, [
            'coffeenet_id' => $coffeenet->id,
            'user' => $user->email,
            'position' => $data['position'],
            'salary' => $data['salary_type'].':'.$data['salary_rate'],
            'permissions' => $permissions,
            'approval_status' => $approvalStatus,
        ], 'افزودن کارمند «'.$user->full_name.'» به کافی‌نت'.($needsApproval ? ' — در انتظار تایید مدیر کل' : ''));

        if ($needsApproval) {
            // اطلاع به مدیران کل برای تایید
            $notifications->notifyAdmins(
                'staff',
                'کارمند جدید در انتظار تایید',
                'مدیر کافی‌نت «'.$coffeenet->name.'» کارمند «'.$user->full_name.'» را اضافه کرد؛ منتظر تایید شماست.',
                ['coffeenet_id' => $coffeenet->id, 'assignment_id' => $assignment->id],
            );

            return response()->json([
                'message' => 'کارمند «'.$user->full_name.'» ثبت شد و پس از تایید مدیر کل فعال می‌شود.',
                'pending_approval' => true,
            ]);
        }

        return response()->json([
            'message' => 'کارمند «'.$user->full_name.'»'.($isNewUser ? ' با حساب جدید' : ' (کاربر موجود)').' اضافه شد.',
        ]);
    }

    /** ویرایش کارمند: اطلاعات، سمت، دسترسی‌ها و مدل حقوق */
    public function update(Request $request, Coffeenet $coffeenet, StaffAssignment $assignment): JsonResponse
    {
        $session = $this->currentCoffeenet($request);

        if ($coffeenet->id !== $session->id) {
            return response()->json(['message' => 'کافی‌نت مسیر با جلسه فعلی شما مطابقت ندارد.'], 403);
        }

        if ($assignment->coffeenet_id !== $session->id) {
            return response()->json(['message' => 'این کارمند به کافی‌نت شما تعلق ندارد.'], 403);
        }

        $isSelf = $assignment->user_id === $request->user()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'family' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile,'.$assignment->user_id],
            'password' => ['nullable', 'string', 'min:8'],
            'position' => ['required', 'in:manager,operator'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'salary_type' => ['required', 'in:percent,fixed_per_order,monthly'],
            'salary_rate' => ['required', 'numeric', 'min:0'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0'],
        ], [
            'name.required' => 'نام کارمند الزامی است.',
            'mobile.regex' => 'فرمت موبایل صحیح نیست (مثل 09123456789).',
            'mobile.unique' => 'این شماره موبایل قبلاً برای کاربر دیگری ثبت شده است.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
            'position.in' => 'سمت انتخابی معتبر نیست.',
            'salary_rate.required' => 'مقدار حقوق الزامی است.',
        ]);

        if ($data['salary_type'] === 'percent' && (float) $data['salary_rate'] > 100) {
            return response()->json(['message' => 'درصد حقوق نمی‌تواند بیش از ۱۰۰ باشد.'], 422);
        }

        // مدیر خودش: سمت قابل تغییر نیست (جلوگیری از قفل‌اوت)
        if ($isSelf && $data['position'] !== 'manager') {
            return response()->json(['message' => 'نمی‌توانید سمت مدیریتی خودتان را تغییر دهید.'], 422);
        }

        // قانون تک-کافینت: ارتقای کاربر به مدیر، وقتی مدیرِ کافی‌نت دیگری است ممنوع
        if ($data['position'] === 'manager' && $assignment->position !== \App\Enums\StaffPosition::Manager) {
            $otherNet = $assignment->user->staffAssignments()
                ->where('position', 'manager')
                ->where('is_active', true)
                ->where('coffeenet_id', '!=', $coffeenet->id)
                ->with('coffeenet:id,name')
                ->first();

            if ($otherNet) {
                return response()->json([
                    'message' => '«'.$assignment->user->full_name.'» مدیر کافی‌نت «'.$otherNet->coffeenet?->name.'» است؛ هر مدیر تنها مدیر یک کافی‌نت می‌تواند باشد.',
                ], 422);
            }
        }

        $permissions = $data['position'] === 'operator'
            ? OperatorPermissions::filter($data['permissions'] ?? OperatorPermissions::defaults())
            : [];

        $old = [
            'position' => $assignment->position->value,
            'permissions' => $assignment->permissions,
            'salary' => $assignment->salarySetting?->only(['type', 'rate', 'overtime_rate']),
        ];

        DB::transaction(function () use ($assignment, $data, $permissions, $isSelf, $coffeenet) {
            $assignment->user->fill(array_filter([
                'name' => $data['name'],
                'family' => $data['family'] ?? null,
                'mobile' => $data['mobile'] ?? null,
            ], fn ($v) => $v !== null))->save();

            if (! empty($data['password'])) {
                $assignment->user->update(['password' => $data['password']]);
            }

            $assignment->update([
                'position' => $data['position'],
                'permissions' => $permissions,
            ]);

            SalarySetting::updateOrCreate(
                ['coffeenet_id' => $coffeenet->id, 'user_id' => $assignment->user_id],
                [
                    'type' => $data['salary_type'],
                    'rate' => $data['salary_rate'],
                    'overtime_rate' => $data['overtime_rate'] ?? null,
                ],
            );

            // مدیر دیگرِ این کافی‌نت نیست → سقوط نقش سراسری coffeenet_manager
            if (! $isSelf && $data['position'] === 'operator'
                && $assignment->user->hasRole('coffeenet_manager')
                && ! $assignment->user->staffAssignments()
                    ->where('position', 'manager')->where('is_active', true)
                    ->where('coffeenet_id', '!=', $coffeenet->id)->exists()) {
                $assignment->user->removeRole('coffeenet_manager');
                if (! $assignment->user->hasRole('operator')) {
                    $assignment->user->assignRole('operator');
                }
            }
        });

        AuditLogger::log('staff.updated', $assignment->refresh(), $old, [
            'position' => $data['position'],
            'salary' => $data['salary_type'].':'.$data['salary_rate'],
            'permissions' => $permissions,
        ], 'ویرایش کارمند «'.$assignment->user->full_name.'»');

        return response()->json(['message' => 'تغییرات کارمند ذخیره شد.']);
    }

    /** فعال/غیرفعال‌سازی کارمند */
    public function toggle(Request $request, Coffeenet $coffeenet, StaffAssignment $assignment): JsonResponse
    {
        $session = $this->currentCoffeenet($request);

        if ($coffeenet->id !== $session->id) {
            return response()->json(['message' => 'کافی‌نت مسیر با جلسه فعلی شما مطابقت ندارد.'], 403);
        }

        if ($assignment->coffeenet_id !== $session->id) {
            return response()->json(['message' => 'این کارمند به کافی‌نت شما تعلق ندارد.'], 403);
        }

        if ($assignment->user_id === $request->user()->id) {
            return response()->json(['message' => 'امکان تغییر وضعیت حساب خودتان وجود ندارد.'], 422);
        }

        $old = ['is_active' => $assignment->is_active];
        $assignment->update(['is_active' => ! $assignment->is_active]);

        $active = $assignment->is_active;

        AuditLogger::log('staff.'.($active ? 'activated' : 'deactivated'), $assignment->refresh(), $old, null,
            ($active ? 'فعال‌سازی' : 'غیرفعال‌سازی').' کارمند «'.$assignment->user->full_name.'»');

        return response()->json([
            'message' => 'کارمند «'.$assignment->user->full_name.'» '.($active ? 'فعال' : 'غیرفعال').' شد.',
            'is_active' => $active,
        ]);
    }
}
