<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Models\Coffeenet;
use App\Models\CommissionPayout;
use App\Models\Organization;
use App\Models\Order;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * گزارش تحلیلی — فاز ۹.
 *
 * index  → صفحهٔ گزارش با فیلتر بازه (پریست ۷/۳۰/۹۰ روز، ماه شمسی، سفارشی)
 * data   → سری‌های نمودار/جدول برای بازهٔ انتخابی (AJAX)
 * export → خروجی CSV در ۵ اسکوپ (سفارش‌ها/خدمات/اپراتورها/مشتریان/کافی‌نت‌ها)
 */
class AnalyticsController extends Controller
{
    private function service(): AnalyticsService
    {
        return app(AnalyticsService::class)->forScope([]);
    }

    /**
     * @return array{\Illuminate\Support\Carbon,\Illuminate\Support\Carbon,string}
     */
    private function range(Request $request): array
    {
        return AnalyticsService::resolveRange(
            $request->query('preset'),
            $request->query('from'),
            $request->query('to')
        );
    }

    public function index(Request $request): View
    {
        [$from, $to, $preset] = $this->range($request);

        return view('back.admin.analytics.index', [
            'preset' => $preset,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rangeLabel' => jdate($from)->format('Y/m/d').' تا '.jdate($to)->format('Y/m/d'),
            'summary' => $this->service()->summary($from, $to),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$from, $to, $preset] = $this->range($request);
        $service = $this->service();

        return response()->json([
            'range' => [
                'preset' => $preset,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => jdate($from)->format('Y/m/d').' تا '.jdate($to)->format('Y/m/d'),
            ],
            'summary' => $service->summary($from, $to),
            'daily' => $service->daily($from, $to),
            'status' => $service->statusBreakdown($from, $to),
            'services' => $service->topServices($from, $to),
            'operators' => $service->topOperators($from, $to),
            'coffeenets' => $service->coffeenetPerformance($from, $to),
            'customers' => $service->topCustomers($from, $to),
        ]);
    }

    /**
     * خروجی CSV — اسکوپ: orders | services | operators | customers | coffeenets
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $scope = (string) $request->query('scope', 'orders');
        $service = $this->service();
        $stamp = now()->format('Ymd-His');

        return match ($scope) {
            'services' => Csv::download("analytics-services-{$stamp}.csv", [
                'شناسه', 'خدمت', 'تعداد سفارش', 'حجم پرداخت‌شده (تومان)',
            ], $this->servicesRows($service, $from, $to)),

            'operators' => Csv::download("analytics-operators-{$stamp}.csv", [
                'شناسه', 'اپراتور', 'سفارش', 'تحویل‌شده', 'درآمد تسویه‌شده (تومان)',
            ], $this->operatorsRows($service, $from, $to)),

            'customers' => Csv::download("analytics-customers-{$stamp}.csv", [
                'شناسه', 'مشتری', 'تعداد سفارش', 'مبلغ پرداخت‌شده (تومان)',
            ], $this->customersRows($service, $from, $to)),

            'coffeenets' => Csv::download("analytics-coffeenets-{$stamp}.csv", [
                'شناسه', 'کافی‌نت', 'سفارش', 'حجم پرداخت‌شده (تومان)', 'کمیسیون تسویه‌شده (تومان)',
            ], $this->coffeenetsRows($service, $from, $to)),

            default => Csv::download("analytics-orders-{$stamp}.csv", [
                'شماره سفارش', 'خدمت', 'مشتری', 'کافی‌نت', 'اپراتور', 'وضعیت', 'مبلغ (تومان)',
                'هزینه‌ها (تومان)', 'مشمول کمیسیون (تومان)', 'پرداخت', 'ثبت (شمسی)',
            ], $this->ordersRows($service, $from, $to)),
        };
    }

    /* ---------- مولدهای ردیف CSV (تنبل — استریم) ---------- */

    private function ordersRows(AnalyticsService $service, $from, $to): \Generator
    {
        $rows = $service->rangeWhere($service->ordersQuery(), 'orders.created_at', $from, $to)
            ->join('services', 'services.id', '=', 'orders.service_id')
            ->join('users as customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('coffeenets', 'coffeenets.id', '=', 'orders.coffeenet_id')
            ->leftJoin('users as operators', 'operators.id', '=', 'orders.operator_id')
            ->orderByDesc('orders.id')
            ->get(['orders.*', 'services.name as service_name',
                'customers.name as customer_name', 'customers.family as customer_family',
                'coffeenets.name as coffeenet_name',
                'operators.name as operator_name', 'operators.family as operator_family']);

        foreach ($rows as $order) {
            // status روی مدل Order به enum کست می‌شود — هر دو حالت پشتیبانی شود
            $statusEnum = $order->status instanceof OrderStatus
                ? $order->status
                : OrderStatus::tryFrom((string) $order->status);
            $status = $statusEnum?->label() ?? (string) $order->status;

            yield [
                $order->order_number,
                $order->service_name,
                trim($order->customer_name.' '.$order->customer_family),
                $order->coffeenet_name ?? '—',
                $order->operator_name ? trim($order->operator_name.' '.$order->operator_family) : '—',
                $status,
                (float) $order->price,
                (float) $order->expenses,
                (float) $order->commissionable_amount,
                $order->paid_at ? jdate($order->paid_at)->format('Y/m/d H:i') : 'پرداخت‌نشده',
                jdate($order->created_at)->format('Y/m/d H:i'),
            ];
        }
    }

    private function servicesRows(AnalyticsService $service, $from, $to): \Generator
    {
        foreach ($service->topServices($from, $to, 500) as $row) {
            yield [$row['id'], $row['name'], $row['count'], $row['volume']];
        }
    }

    private function operatorsRows(AnalyticsService $service, $from, $to): \Generator
    {
        foreach ($service->topOperators($from, $to, 500) as $row) {
            yield [$row['id'], $row['name'], $row['count'], $row['delivered'], $row['earnings']];
        }
    }

    private function customersRows(AnalyticsService $service, $from, $to): \Generator
    {
        foreach ($service->topCustomers($from, $to, 1000) as $row) {
            yield [$row['id'], $row['name'], $row['count'], $row['spent']];
        }
    }

    private function coffeenetsRows(AnalyticsService $service, $from, $to): \Generator
    {
        foreach ($service->coffeenetPerformance($from, $to, 500) as $row) {
            yield [$row['id'], $row['name'], $row['count'], $row['volume'], $row['commission']];
        }
    }
}
