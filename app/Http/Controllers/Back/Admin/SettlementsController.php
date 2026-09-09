<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayout;
use App\Models\Order;
use App\Services\Finance\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * تسویه‌های کمیسیون (فاز ۸) — دفتر پرداخت‌های کیف پولی سفارش‌های تحویل‌شده.
 */
class SettlementsController extends Controller
{
    public function index(Request $request): View
    {
        $roleSums = CommissionPayout::query()
            ->selectRaw('role, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('role')
            ->get()
            ->keyBy('role');

        return view('back.admin.settlements.index', [
            'summary' => [
                'total' => (float) CommissionPayout::sum('amount'),
                'count' => (int) CommissionPayout::count(),
                'orders' => (int) CommissionPayout::distinct('order_id')->count('order_id'),
                'platform' => (float) ($roleSums['platform']->total ?? 0),
                'organization' => (float) ($roleSums['organization']->total ?? 0),
                'coffeenet' => (float) ($roleSums['coffeenet']->total ?? 0),
                'operator' => (float) ($roleSums['operator']->total ?? 0),
            ],
        ]);
    }

    /** لیست پرداخت‌های تسویه (AJAX + فیلتر نقش + بازهٔ تاریخ + جستجوی شماره سفارش) */
    public function data(Request $request): JsonResponse
    {
        $query = CommissionPayout::query()
            ->with(['order:id,order_number,coffeenet_id,operator_id,service_id', 'order.service:id,name', 'wallet:id,holder_type,holder_id']);

        if ($role = (string) $request->query('role')) {
            if (in_array($role, ['platform', 'organization', 'coffeenet', 'operator'], true)) {
                $query->where('role', $role);
            }
        }

        if ($q = trim((string) $request->query('q'))) {
            $query->whereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$q}%"));
        }

        if ($from = (string) $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = (string) $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $paginator = $query->orderByDesc('id')->paginate(20);

        $rows = $paginator->through(fn (CommissionPayout $p) => [
            'id' => $p->id,
            'order_id' => $p->order_id,
            'order_number' => $p->order?->order_number ?? '—',
            'service' => $p->order?->service?->name ?? '—',
            'role' => $p->role,
            'role_label' => $p->roleLabel(),
            'holder' => $this->holderName($p),
            'amount' => (float) $p->amount,
            'date' => $p->created_at ? jdate($p->created_at)->format('Y/m/d — H:i') : '—',
        ]);

        return response()->json($rows);
    }

    /** جزئیات تسویهٔ یک سفارش (مودال) */
    public function order(Request $request, Order $order): JsonResponse
    {
        $payouts = CommissionPayout::query()
            ->where('order_id', $order->id)
            ->orderByDesc('amount')
            ->get();

        if ($payouts->isEmpty()) {
            return response()->json([
                'message' => 'این سفارش هنوز تسویه نشده است.',
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status_label' => $order->status->label(),
                        'commissionable' => (float) $order->commissionable_amount,
                        'paid_at' => $order->paid_at?->format('Y-m-d H:i'),
                    ],
                    'payouts' => [],
                    'settled' => false,
                ],
            ]);
        }

        $snap = $payouts->first()->snapshot ?? [];

        return response()->json([
            'message' => 'ok',
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status_label' => $order->status->label(),
                    'service' => $order->service?->name,
                    'coffeenet' => $order->coffeenet?->name,
                    'operator' => trim(($order->operator?->name ?? '').' '.($order->operator?->family ?? '')) ?: null,
                    'commissionable' => (float) $order->commissionable_amount,
                    'order_total' => (float) $order->price + (float) $order->expenses,
                    'paid_at' => $order->paid_at?->format('Y-m-d H:i'),
                ],
                'settled' => true,
                'settled_at' => $snap['settled_at'] ?? null,
                'payouts' => $payouts->map(fn (CommissionPayout $p) => [
                    'id' => $p->id,
                    'role' => $p->role,
                    'role_label' => $p->roleLabel(),
                    'holder' => $this->holderName($p),
                    'amount' => (float) $p->amount,
                ]),
                'snapshot' => [
                    'rule' => $this->ruleSnapshotText($snap['rule'] ?? []),
                    'salary' => isset($snap['salary']) && $snap['salary']
                        ? ($snap['salary']['type_label'] ?? '—').' — '.fa_number((float) ($snap['salary']['rate'] ?? 0))
                        : null,
                    'shares' => $snap['shares'] ?? [],
                ],
            ],
        ]);
    }

    /** اجرای مجدد/اصلاحی تسویه برای سفارش (idempotent) */
    public function retry(Request $request, Order $order): JsonResponse
    {
        $result = app(SettlementService::class)->settle($order);

        if ($result['settled'] ?? false) {
            return response()->json([
                'message' => 'تسویه انجام شد — '.count($result['payouts'] ?? []).' پرداخت کیف پولی ثبت شد.',
                'data' => $result,
            ]);
        }

        $reasons = [
            'already_settled' => 'این سفارش قبلاً تسویه شده است.',
            'not_delivered' => 'فقط سفارش‌های تحویل‌شده/تکمیل‌شده قابل تسویه‌اند.',
            'unpaid' => 'مشتری هنوز پرداخت نکرده است.',
            'no_commissionable' => 'مبلغ مشمول کمیسیون این سفارش صفر است.',
            'no_rule' => 'قاعدهٔ کمیسیون فعالی (سراسری یا اختصاصی این خدمت) یافت نشد.',
        ];

        $reason = (string) ($result['reason'] ?? '');

        return response()->json([
            'message' => $reasons[$reason] ?? ('تسویه انجام نشد: '.$reason),
            'data' => $result,
        ], 422);
    }

    /* ---------- ابزارها ---------- */

    protected function holderName(CommissionPayout $p): string
    {
        $holder = $p->holder;

        return match (true) {
            $holder instanceof \App\Models\Coffeenet => 'کافی‌نت «'.($holder->name ?? '—').'»',
            $holder instanceof \App\Models\Organization => 'سازمان «'.($holder->name ?? '—').'»',
            $holder instanceof \App\Models\User => ($p->role === 'platform' ? 'حساب پلتفرم' : 'اپراتور «'.trim(($holder->name ?? '').' '.($holder->family ?? '')).'»'),
            default => '—',
        };
    }

    protected function ruleSnapshotText(array $rule): ?string
    {
        if (! $rule) {
            return null;
        }

        $share = fn ($pair) => (empty($pair[0]) || ($pair[1] ?? null) === null) ? '—'
            : (($pair[0] === 'percent') ? fa_number((float) $pair[1]).'٪' : fa_money((float) $pair[1]));

        return 'قاعدهٔ '.($rule['scope'] === 'service' ? 'اختصاصی خدمت' : 'سراسری')
            .' — پلتفرم: '.$share($rule['platform'] ?? [])
            .' / سازمان: '.$share($rule['organization'] ?? [])
            .' / کافی‌نت: '.$share($rule['coffeenet'] ?? []);
    }
}
