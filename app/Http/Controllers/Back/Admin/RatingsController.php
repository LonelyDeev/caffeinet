<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Order;
use App\Models\RatingOption;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * v33 — مرکز نظرسنجی‌ها و امتیازهای مشتریان.
 *
 *  - مدیر کل: همهٔ نظرات همهٔ کافی‌netها + مدیریت گزینه‌های دلایل
 *  - مدیر کافی‌net: فقط نظرات سفارش‌های کافی‌net خودش (بدون مدیریت گزینه‌ها)
 *
 * هر نظر: امتیاز کلی (کافی‌net) + امتیاز اپراتور + دلایل تیک‌خورده + متن دیدگاه؛
 * فیلترها: امتیاز (۱..۵ / کم / عالی)، اپراتور، کافی‌net، دلیل، تاریخ، متن.
 */
class RatingsController extends Controller
{
    /** GET /admin/ratings — صفحهٔ نظرسنجی‌ها (مدیریت کل) */
    public function index(Request $request): View
    {
        return $this->renderIndex($request, null, 'back.layouts.panel', 'پنل مدیریت کل');
    }

    /** GET /coffeenet/{coffeenet}/ratings — نظرسنجی‌های همین کافی‌net */
    public function coffeenetIndex(Request $request, Coffeenet $coffeenet): View
    {
        $this->assertSameCoffeenet($request, $coffeenet);

        return $this->renderIndex($request, $coffeenet, 'back.coffeenet.layouts.panel', 'پنل کافی‌net');
    }

    protected function renderIndex(Request $request, ?Coffeenet $coffeenet, string $layout, string $panelLabel): View
    {
        $base = $coffeenet ? "/coffeenet/{$coffeenet->id}/ratings" : '/admin/ratings';

        return view('back.admin.ratings.index', [
            'coffeenet' => $coffeenet,
            'ratingsBase' => $base,
            'isSuperAdmin' => $coffeenet === null,
            'panelLabel' => $panelLabel,
            'layout' => $layout,
            'coffeenets' => $coffeenet ? collect() : $this->coffeenetFilterList(),
            'operators' => $this->operatorFilterList($coffeenet?->id),
            'options' => RatingOption::query()->ordered()->get(),
        ]);
    }

    /** GET {base}/data — لیست AJAX + فیلترها */
    public function data(Request $request): JsonResponse
    {
        [$coffeenetId, $base] = $this->resolveContext($request);

        $query = Order::query()
            ->whereHas('rating')
            ->whereNotNull('coffeenet_id')
            ->with([
                'rating',
                'service' => fn ($q) => $q->select(['id', 'name']),
                'customer' => fn ($q) => $q->select(['id', 'name', 'family', 'mobile']),
                'coffeenet' => fn ($q) => $q->select(['id', 'name']),
                'operator' => fn ($q) => $q->select(['id', 'name', 'family']),
            ]);

        if ($coffeenetId) {
            $query->where('coffeenet_id', $coffeenetId);
        }

        // فیلتر کافی‌net (فقط مدیر کل)
        if (! $coffeenetId && ($net = (int) $request->query('coffeenet_id'))) {
            $query->where('coffeenet_id', $net);
        }

        // فیلتر اپراتور
        if ($op = (int) $request->query('operator_id')) {
            $query->where('operator_id', $op);
        }

        // فیلتر امتیاز کلی (ستاره‌ها — امتیاز کافی‌net)
        $rating = (string) $request->query('rating', '');
        if ($rating !== '') {
            if ($rating === 'low') {
                $query->whereHas('rating', fn ($q) => $q->where('rating', '<=', 2));
            } elseif ($rating === 'high') {
                $query->whereHas('rating', fn ($q) => $q->where('rating', '>=', 4));
            } elseif ($rating === 'with_operator') {
                $query->whereHas('rating', fn ($q) => $q->whereNotNull('operator_rating'));
            } elseif ($rating === 'with_comment') {
                $query->whereHas('rating', fn ($q) => $q->whereNotNull('comment')->where('comment', '!=', ''));
            } else {
                $value = (int) $rating;
                if ($value >= 1 && $value <= 5) {
                    $query->whereHas('rating', fn ($q) => $q->where('rating', $value));
                }
            }
        }

        // فیلتر امتیاز اپراتور
        $opRating = (string) $request->query('operator_rating', '');
        if ($opRating !== '') {
            if ($opRating === 'low') {
                $query->whereHas('rating', fn ($q) => $q->whereNotNull('operator_rating')->where('operator_rating', '<=', 2));
            } elseif ($opRating === 'high') {
                $query->whereHas('rating', fn ($q) => $q->whereNotNull('operator_rating')->where('operator_rating', '>=', 4));
            } else {
                $value = (int) $opRating;
                if ($value >= 1 && $value <= 5) {
                    $query->whereHas('rating', fn ($q) => $q->where('operator_rating', $value));
                }
            }
        }

        // فیلتر دلیل (گزینه) — LIKE دقیق روی اسنپ‌شات JSON (با کامای بعد از id تا ۱۳ با ۱ اشتباه نشود)
        if ($optionId = (int) $request->query('option_id')) {
            $needle = '%"id":'.$optionId.',%';
            $query->whereHas('rating', fn ($q) => $q->where('options', 'like', $needle));
        }

        // جستجو: شماره سفارش / نام و موبایل مشتری / متن دیدگاه
        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('family', 'like', "%{$q}%")
                        ->orWhere('mobile', 'like', "%{$q}%"))
                    ->orWhereHas('rating', fn ($r) => $r->where('comment', 'like', "%{$q}%"));
            });
        }

        // بازهٔ تاریخ
        if ($from = trim((string) $request->query('from'))) {
            $query->whereHas('rating', fn ($r) => $r->whereDate('rated_at', '>=', $from));
        }
        if ($to = trim((string) $request->query('to'))) {
            $query->whereHas('rating', fn ($r) => $r->whereDate('rated_at', '<=', $to));
        }

        // مرتب‌سازی
        $sort = (string) $request->query('sort', 'newest');
        $query->when($sort === 'worst', fn ($qq) => $qq
            ->leftJoin('order_ratings as r_worst', 'r_worst.order_id', '=', 'orders.id')
            ->orderBy('r_worst.rating')
            ->select('orders.*'))
            ->when($sort === 'best', fn ($qq) => $qq
                ->leftJoin('order_ratings as r_best', 'r_best.order_id', '=', 'orders.id')
                ->orderByDesc('r_best.rating')
                ->select('orders.*'))
            ->when($sort === 'newest', fn ($qq) => $qq->orderByDesc('orders.id'));

        $rows = $query->orderByDesc('orders.id')->distinct()->paginate(20)->withQueryString();

        $rows->through(fn (Order $order) => $this->rowPayload($order, $base));

        return response()->json($rows);
    }

    /** GET {base}/stats — چیپ‌های آماری + توزیع + برترین دلایل */
    public function stats(Request $request): JsonResponse
    {
        [$coffeenetId] = $this->resolveContext($request);

        $base = Order::query()
            ->whereHas('rating')
            ->whereNotNull('coffeenet_id');

        if ($coffeenetId) {
            $base->where('coffeenet_id', $coffeenetId);
        }

        $total = (clone $base)->count();
        $avg = (clone $base)->join('order_ratings as rs', 'rs.order_id', '=', 'orders.id')->avg('rs.rating');
        $avgOperator = (clone $base)->join('order_ratings as ro', 'ro.order_id', '=', 'orders.id')
            ->whereNotNull('ro.operator_rating')->avg('ro.operator_rating');
        $low = (clone $base)->join('order_ratings as rl', 'rl.order_id', '=', 'orders.id')
            ->where('rl.rating', '<=', 2)->count();

        $distribution = (clone $base)->join('order_ratings as rd', 'rd.order_id', '=', 'orders.id')
            ->groupBy('rd.rating')
            ->selectRaw('rd.rating as r, count(*) as c')
            ->pluck('c', 'r')
            ->map(fn ($c) => (int) $c)
            ->all();

        // برترین دلایل (از اسنپ‌شات JSON — نتیجه مستقل از حذف گزینه‌ها)
        $reasons = [];
        (clone $base)->join('order_ratings as rr', 'rr.order_id', '=', 'orders.id')
            ->whereNotNull('rr.options')
            ->select('rr.options')
            ->chunk(500, function ($chunk) use (&$reasons) {
                foreach ($chunk as $row) {
                    foreach (json_decode((string) $row->options, true) ?: [] as $opt) {
                        $title = trim((string) ($opt['title'] ?? ''));
                        if ($title !== '') {
                            $reasons[$title] = ($reasons[$title] ?? 0) + 1;
                        }
                    }
                }
            });
        arsort($reasons);

        $dist = [];
        for ($i = 1; $i <= 5; $i++) {
            $dist[$i] = (int) ($distribution[$i] ?? 0);
        }

        return response()->json([
            'total' => $total,
            'avg' => $avg !== null ? round((float) $avg, 1) : null,
            'avg_operator' => $avgOperator !== null ? round((float) $avgOperator, 1) : null,
            'low' => $low,
            'distribution' => $dist,
            'top_reasons' => array_slice($reasons, 0, 6, true),
        ]);
    }

    /* ================== مدیریت گزینه‌ها (فقط مدیر کل) ================== */

    /** GET /admin/ratings/options — لیست گزینه‌ها */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => RatingOption::query()->ordered()->get()->map(fn (RatingOption $o) => [
                'id' => $o->id,
                'title' => $o->title,
                'type' => $o->type,
                'type_label' => $o->type === 'neg' ? 'نقطه ضعف' : 'نقطه قوت',
                'is_active' => (bool) $o->is_active,
                'sort_order' => (int) $o->sort_order,
            ]),
        ]);
    }

    /** POST /admin/ratings/options — افزودن گزینه */
    public function storeOption(Request $request): JsonResponse
    {
        $this->assertOptionsManager($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['required', 'in:pos,neg'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'title.required' => 'متن گزینه الزامی است.',
            'title.min' => 'متن گزینه باید حداقل ۲ حرف باشد.',
            'title.max' => 'متن گزینه حداکثر ۱۰۰ حرف است.',
            'type.in' => 'نوع گزینه باید نقطه قوت یا نقطه ضعف باشد.',
        ]);

        $maxSort = (int) RatingOption::query()->where('type', $data['type'])->max('sort_order');

        $option = RatingOption::query()->create([
            'title' => trim($data['title']),
            'type' => $data['type'],
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? ($maxSort + 1),
        ]);

        AuditLogger::log('ratings.option_created', null, null, $option->only(['id', 'title', 'type', 'sort_order']),
            'افزودن گزینهٔ نظرسنجی «'.$option->title.'»');

        return response()->json([
            'message' => 'گزینهٔ «'.$option->title.'» اضافه شد؛ از همین لحظه در نظرسنجی اپ مشتری نمایش داده می‌شود.',
            'data' => ['id' => $option->id],
        ], 201);
    }

    /** PATCH /admin/ratings/options/{option} — ویرایش/فعال‌سازی/غیرفعال‌سازی */
    public function updateOption(Request $request, RatingOption $option): JsonResponse
    {
        $this->assertOptionsManager($request);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'min:2', 'max:100'],
            'type' => ['sometimes', 'in:pos,neg'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ], [
            'title.min' => 'متن گزینه باید حداقل ۲ حرف باشد.',
            'type.in' => 'نوع گزینه باید نقطه قوت یا نقطه ضعف باشد.',
        ]);

        $old = $option->only(['title', 'type', 'is_active', 'sort_order']);
        $option->fill($data)->save();

        AuditLogger::log('ratings.option_updated', null, $old, $option->only(['title', 'type', 'is_active', 'sort_order']),
            'ویرایش گزینهٔ نظرسنجی «'.$option->title.'»');

        return response()->json(['message' => 'گزینهٔ «'.$option->title.'» بروزرسانی شد.']);
    }

    /** DELETE /admin/ratings/options/{option} — حذف گزینه */
    public function destroyOption(Request $request, RatingOption $option): JsonResponse
    {
        $this->assertOptionsManager($request);

        $title = $option->title;
        $old = $option->only(['id', 'title', 'type', 'is_active', 'sort_order']);

        $usage = (int) \App\Models\OrderRating::query()
            ->where('options', 'like', '%"id":'.$option->id.',%')
            ->count();

        $option->delete();

        AuditLogger::log('ratings.option_deleted', null, $old, null,
            'حذف گزینهٔ نظرسنجی «'.$title.'» (استفاده در '.fa_digits((string) $usage).' نظر)');

        return response()->json([
            'message' => 'گزینهٔ «'.$title.'» حذف شد.'
                .($usage ? ' تاریخچهٔ '.fa_digits((string) $usage).' نظرِ ثبت‌شده دست‌نخورده می‌ماند (اسنپ‌شات).' : ''),
        ]);
    }

    /* ================== منطق مشترک ================== */

    protected function resolveContext(Request $request): array
    {
        $net = $request->attributes->get('current_coffeenet');

        // مدیر کافی‌net: محدود به کافی‌net جلسه — از URL می‌آید (route coffeenet)
        if ($net instanceof Coffeenet) {
            return [(int) $net->id, "/coffeenet/{$net->id}/ratings"];
        }

        return [null, '/admin/ratings'];
    }

    protected function assertSameCoffeenet(Request $request, Coffeenet $coffeenet): void
    {
        $session = $request->attributes->get('current_coffeenet');
        abort_unless($session instanceof Coffeenet && (int) $session->id === (int) $coffeenet->id, 403,
            'کافی‌net مسیر با جلسه فعلی شما مطابقت ندارد.');
    }

    /** مدیریت گزینه‌ها فقط با مدیر کل (یا مجوز تنظیمات) */
    protected function assertOptionsManager(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->hasRole('super_admin') || $user->can('access', [User::class, 'settings'])),
            403,
            'مدیریت گزینه‌های نظرسنجی فقط برای مدیر کل فعال است.'
        );
    }

    protected function rowPayload(Order $order, string $base): array
    {
        $rating = $order->rating;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'service_name' => $order->service?->name ?? '—',
            'customer_name' => trim(($order->customer?->name ?? '').' '.($order->customer?->family ?? '')) ?: '—',
            'customer_mobile' => $order->customer?->mobile,
            'coffeenet_name' => $order->coffeenet?->name,
            'operator_name' => $order->operator ? trim(($order->operator->name ?? '').' '.($order->operator->family ?? '')) : null,
            'rating' => (int) $rating->rating,
            'operator_rating' => $rating->operator_rating !== null ? (int) $rating->operator_rating : null,
            'comment' => $rating->comment,
            'options' => collect($rating->options ?? [])->map(fn ($o) => [
                'title' => (string) ($o['title'] ?? ''),
                'type' => (string) ($o['type'] ?? 'pos'),
            ])->all(),
            'rated_at_fa' => $rating->rated_at ? fa_date($rating->rated_at, 'Y/m/d H:i') : null,
            'view_url' => $base === '/admin/ratings'
                ? '/admin/orders/'.$order->id.'/view'
                : str_replace('/ratings', '/orders', $base).'/'.$order->id,
        ];
    }

    /** لیست کافی‌netها برای فیلتر (مدیر کل) — کافی‌netهایی که حداقل یک نظر دارند */
    protected function coffeenetFilterList(): \Illuminate\Support\Collection
    {
        return Coffeenet::query()
            ->whereHas('ordersWithRating')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Coffeenet $c) => ['id' => $c->id, 'name' => $c->name]);
    }

    /** اپراتورهایی که امتیاز اپراتور دارند (برای فیلتر) */
    protected function operatorFilterList(?int $coffeenetId): \Illuminate\Support\Collection
    {
        $query = User::query()
            ->whereHas('operatedOrders.rating', fn ($q) => $q->whereNotNull('operator_rating'))
            ->orderBy('name');

        if ($coffeenetId) {
            $query->whereHas('operatedOrders', fn ($q) => $q->where('coffeenet_id', $coffeenetId));
        }

        return $query->get(['id', 'name', 'family'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name]);
    }
}
