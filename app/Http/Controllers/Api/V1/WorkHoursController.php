<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Settings\WorkingHoursService;
use Illuminate\Http\JsonResponse;

/**
 * فاز ۱۵ — وضعیت ساعت کاری برای اپ مشتری (عمومی).
 *
 * اپ قبل از ثبت سفارش این اندپوینت را صدا می‌زند تا در صورت بسته بودن،
 * مودال زیبا نمایش دهد (چک سخت‌گیرانه در POST /orders هم انجام می‌شود).
 */
class WorkHoursController extends Controller
{
    /** GET /api/v1/work-hours */
    public function status(WorkingHoursService $workHours): JsonResponse
    {
        $status = $workHours->status();

        return response()->json([
            'data' => [
                'enabled' => $status['enabled'],
                'open' => $status['open'],
                'start' => fa_digits($status['start']),
                'end' => fa_digits($status['end']),
                'days' => $status['days'],
                'today' => fa_day_name($status['day_of_week']),
                'today_open' => $status['day_today_open'],
                'message' => $status['message'],
            ],
        ]);
    }
}
