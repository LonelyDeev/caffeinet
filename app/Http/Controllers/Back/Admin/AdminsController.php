<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Policies\AdminAccessPolicy;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

/**
 * مدیریت مدیران پنل (درخواست بازخوردی ۶-۴).
 *
 * دو سطح:
 *  - مدیر کل (super_admin)      → دسترسی کامل (Gate::before)
 *  - مدیر دستیار (admin)        → فقط بخش‌هایی که مجوزشان فعال شده
 *                                 (ماتریس مجوز از AdminAccessPolicy)
 */
class AdminsController extends Controller
{
    /** نقش‌های قابل انتخاب در فرم */
    public const ROLES = [
        'super_admin' => 'مدیر کل — دسترسی کامل به همهٔ بخش‌ها',
        'admin' => 'مدیر دستیار — دسترسی به بخش‌های انتخابی',
    ];

    public function index(): View
    {
        return view('back.admin.admins.index', [
            'sectionMatrix' => self::sectionMatrix(),
        ]);
    }

    /** ماتریس بخش‌ها برای UI مجوز (بخش → [label, permission]) */
    public static function sectionMatrix(): array
    {
        $matrix = [];
        foreach (AdminAccessPolicy::SECTION_PERMISSIONS as $section => $permission) {
            $matrix[] = [
                'section' => $section,
                'label' => AdminAccessPolicy::SECTION_LABELS[$section] ?? $section,
                'permission' => $permission,
                'free' => $permission === null,
            ];
        }

        return $matrix;
    }

    /** لیست مدیران (AJAX + جستجو + صفحه‌بندی) — مدیر کل و دستیار */
    public function data(Request $request): JsonResponse
    {
        $query = User::role(['super_admin', 'admin'])
            ->with('roles');

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('family', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%");
            });
        }

        $paginator = $query->latest('id')->paginate(25);

        $rows = $paginator->through(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'family' => $u->family,
            'full_name' => $u->full_name,
            'email' => $u->email,
            'mobile' => $u->mobile,
            'is_active' => $u->is_active,
            'is_super' => $u->hasRole('super_admin'),
            'is_self' => $u->id === $request->user()->id,
            'sections' => $u->sections(),
            'sections_count' => count($u->sections()) === 1 && $u->sections()[0] === '*'
                ? -1
                : count($u->sections()),
            'last_login_at' => $u->last_login_at?->diffForHumans(now(), ['locale' => 'fa']) ?? '—',
            'created_at' => $u->created_at?->diffForHumans(now(), ['locale' => 'fa']) ?? '—',
        ]);

        return response()->json($rows);
    }

    /** ایجاد مدیر جدید (AJAX) */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'family' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:super_admin,admin'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ], [
            'name.required' => 'نام الزامی است.',
            'email.required' => 'ایمیل الزامی است.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'mobile.regex' => 'فرمت موبایل صحیح نیست (مثل 09123456789).',
            'mobile.unique' => 'این موبایل قبلاً ثبت شده است.',
            'password.required' => 'رمز عبور الزامی است.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
            'role.in' => 'سطح دسترسی معتبر نیست.',
        ]);

        $permissions = $data['role'] === 'admin'
            ? $this->filterPermissions($data['permissions'] ?? [])
            : [];

        if ($data['role'] === 'admin' && $permissions === []) {
            return response()->json([
                'errors' => ['permissions' => ['برای مدیر دستیار حداقل یک بخش را فعال کنید.']],
                'message' => 'برای مدیر دستیار حداقل یک بخش را فعال کنید.',
            ], 422);
        }

        $user = User::create([
            ...collect($data)->only(['name', 'family', 'email', 'mobile'])->all(),
            'password' => $data['password'],
            'is_active' => true,
        ]);

        $user->assignRole($data['role']);
        if ($permissions) {
            $user->syncPermissions($permissions);
        }

        AuditLogger::log('admin.created', $user, null, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $data['role'],
            'permissions' => $permissions,
        ], 'ایجاد '.($data['role'] === 'super_admin' ? 'مدیر کل' : 'مدیر دستیار').' جدید');

        return response()->json(['message' => 'مدیر با موفقیت ایجاد شد.']);
    }

    /** ویرایش مدیر (AJAX) */
    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->hasAnyRole(['super_admin', 'admin']), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'family' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,'.$user->id],
            'mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:super_admin,admin'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ], [
            'name.required' => 'نام الزامی است.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'mobile.regex' => 'فرمت موبایل صحیح نیست.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
            'role.in' => 'سطح دسترسی معتبر نیست.',
        ]);

        // مدیر کل نمی‌تواند سطح دسترسی خودش را تنزل دهد (جلوگیری از قفل‌اوت)
        if ($user->id === $request->user()->id
            && $user->hasRole('super_admin')
            && $data['role'] !== 'super_admin') {
            return response()->json([
                'message' => 'نمی‌توانید سطح دسترسی خودتان را از «مدیر کل» تغییر دهید.',
            ], 422);
        }

        $permissions = $data['role'] === 'admin'
            ? $this->filterPermissions($data['permissions'] ?? [])
            : [];

        if ($data['role'] === 'admin' && $permissions === []) {
            return response()->json([
                'errors' => ['permissions' => ['برای مدیر دستیار حداقل یک بخش را فعال کنید.']],
                'message' => 'برای مدیر دستیار حداقل یک بخش را فعال کنید.',
            ], 422);
        }

        $old = [
            ...$user->only(['name', 'family', 'email', 'mobile']),
            'role' => $user->hasRole('super_admin') ? 'super_admin' : 'admin',
            'permissions' => $user->permissions->pluck('name')->all(),
        ];

        DB::transaction(function () use ($user, $data, $permissions) {
            $user->fill(collect($data)->only(['name', 'family', 'email', 'mobile'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();

            // سینک نقش
            if ($data['role'] === 'super_admin') {
                $user->syncRoles(['super_admin']);
            } else {
                $user->syncRoles(['admin']);
            }

            // سینک مجوزها (مدیر کل به مجوز نیاز ندارد — Gate::before)
            $user->syncPermissions($permissions);
        });

        AuditLogger::log('admin.updated', $user, $old, [
            ...$user->only(['name', 'family', 'email', 'mobile']),
            'role' => $data['role'],
            'permissions' => $permissions,
        ], 'ویرایش مدیر و سطح دسترسی‌ها');

        return response()->json(['message' => 'تغییرات ذخیره شد.']);
    }

    /** فعال/غیرفعال کردن (AJAX) */
    public function toggle(Request $request, User $user): JsonResponse
    {
        abort_unless($user->hasAnyRole(['super_admin', 'admin']), 404);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'نمی‌توانید حساب خودتان را غیرفعال کنید.'], 422);
        }

        $old = ['is_active' => $user->is_active];
        $user->update(['is_active' => ! $user->is_active]);

        AuditLogger::log('admin.toggled', $user, $old,
            ['is_active' => $user->is_active],
            $user->is_active ? 'فعال‌سازی مدیر' : 'غیرفعال‌سازی مدیر');

        return response()->json([
            'message' => $user->is_active ? 'مدیر فعال شد.' : 'مدیر غیرفعال شد.',
            'is_active' => $user->is_active,
        ]);
    }

    /** فقط مجوزهای معتبرِ نگاشت‌شده به بخش‌ها را نگه می‌دارد */
    protected function filterPermissions(array $permissions): array
    {
        $valid = array_values(array_filter(
            AdminAccessPolicy::SECTION_PERMISSIONS,
            fn ($p) => $p !== null
        ));

        return array_values(array_intersect(array_unique($permissions), $valid));
    }
}
