<?php

namespace App\Http\Controllers\Back\Coffeenet;

use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\SalaryLog;
use App\Models\StaffAssignment;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalariesController extends Controller
{
    /** نوع‌های لاگ حقوق */
    public const TYPES = [
        'monthly' => 'حقوق ماهیانه',
        'overtime' => 'اضافه‌کار',
        'bonus' => 'پاداش',
        'manual' => 'دستی / سایر',
    ];

    /** صفحه حقوق و دستمزد */
    public function index(Request $request, Coffeenet $coffeenet): View
    {
        $this->assertCurrent($request, $coffeenet);

        $currentPeriod = now()->format('Y-m');
        $lastPeriod = now()->subMonth()->format('Y-m');

        $staff = $coffeenet->staffAssignments()
            ->where('is_active', true)
            ->with([
                'user:id,name,family',
                'salarySetting' => fn ($q) => $q->where('salary_settings.coffeenet_id', $coffeenet->id),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (StaffAssignment $s) => [
                'assignment_id' => $s->id,
                'user_id' => $s->user_id,
                'full_name' => $s->user->full_name,
                'salary' => $s->salarySetting ? [
                    'type' => $s->salarySetting->type->value,
                    'type_label' => $s->salarySetting->type->label(),
                    'rate' => (float) $s->salarySetting->rate,
                    'overtime_rate' => $s->salarySetting->overtime_rate !== null ? (float) $s->salarySetting->overtime_rate : null,
                ] : null,
            ]);

        return view('back.coffeenet.salaries.index', [
            'staff' => $staff,
            'types' => self::TYPES,
            'currentPeriod' => $currentPeriod,
            'summary' => [
                'current_total' => (float) SalaryLog::where('coffeenet_id', $coffeenet->id)->where('period', $currentPeriod)->sum('amount'),
                'last_total' => (float) SalaryLog::where('coffeenet_id', $coffeenet->id)->where('period', $lastPeriod)->sum('amount'),
                'logs_count' => SalaryLog::where('coffeenet_id', $coffeenet->id)->count(),
                'paid_staff' => SalaryLog::where('coffeenet_id', $coffeenet->id)->where('period', $currentPeriod)->distinct('user_id')->count('user_id'),
            ],
        ]);
    }

    /** لیست لاگ‌های حقوق (AJAX + فیلتر دوره/کارمند/نوع + صفحه‌بندی) */
    public function data(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        $this->assertCurrent($request, $coffeenet);

        $query = SalaryLog::query()
            ->where('coffeenet_id', $coffeenet->id)
            ->with('user:id,name,family,email', 'loggedBy:id,name,family');

        if ($userId = (int) $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($type = (string) $request->query('type')) {
            if (array_key_exists($type, self::TYPES)) {
                $query->where('type', $type);
            }
        }

        if ($period = trim((string) $request->query('period'))) {
            if (preg_match('/^\d{4}-\d{2}$/', $period)) {
                $query->where('period', $period);
            }
        }

        $paginator = $query->orderByDesc('id')->paginate(25);

        $rows = $paginator->through(fn (SalaryLog $log) => [
            'id' => $log->id,
            'user' => [
                'id' => $log->user_id,
                'full_name' => $log->user?->full_name ?? '—',
            ],
            'period' => $log->period,
            'period_label' => jdate($log->period.'-01')->format('F Y'),
            'type' => $log->type,
            'type_label' => self::TYPES[$log->type] ?? $log->type,
            'amount' => (float) $log->amount,
            'description' => $log->description,
            'logged_by' => $log->loggedBy?->full_name ?? '—',
            'created_at' => $log->created_at?->diffForHumans(now(), ['locale' => 'fa']) ?? '—',
        ]);

        return response()->json($rows);
    }

    /** ثبت پرداخت حقوق/اضافه‌کار/پاداش (لاگ گزارشی) */
    public function store(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        $this->assertCurrent($request, $coffeenet);

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'period' => ['required', 'date_format:Y-m'],
            'type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'user_id.required' => 'انتخاب کارمند الزامی است.',
            'period.required' => 'دوره (ماه) الزامی است.',
            'period.date_format' => 'فرمت دوره معتبر نیست.',
            'type.in' => 'نوع پرداخت معتبر نیست.',
            'amount.required' => 'مبلغ الزامی است.',
            'amount.min' => 'مبلغ باید بیش از صفر باشد.',
            'description.max' => 'توضیحات حداکثر ۵۰۰ کاراکتر.',
        ]);

        $assignment = $coffeenet->staffAssignments()
            ->where('user_id', $data['user_id'])
            ->first();

        if (! $assignment) {
            return response()->json(['message' => 'این کارمند به کافی‌نت شما تعلق ندارد.'], 422);
        }

        $log = DB::transaction(function () use ($data, $coffeenet, $request) {
            return SalaryLog::create([
                'coffeenet_id' => $coffeenet->id,
                'user_id' => $data['user_id'],
                'period' => $data['period'],
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'logged_by' => $request->user()->id,
            ]);
        });

        AuditLogger::log('salary.logged', $log, null, [
            'user_id' => $data['user_id'],
            'period' => $data['period'],
            'type' => $data['type'],
            'amount' => (float) $data['amount'],
        ], 'ثبت پرداخت '.(self::TYPES[$data['type']] ?? '').' دوره '.$data['period']);

        return response()->json([
            'message' => 'پرداخت «'.(self::TYPES[$data['type']] ?? '').'» برای دوره '
                .jdate($data['period'].'-01')->format('F Y').' ثبت شد.',
        ]);
    }

    /** مطمئن شدن کافی‌نت مسیر همان کافی‌نت session است */
    protected function assertCurrent(Request $request, Coffeenet $coffeenet): void
    {
        abort_unless($request->attributes->get('current_coffeenet')?->id === $coffeenet->id, 403);
    }
}
