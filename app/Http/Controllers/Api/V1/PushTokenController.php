<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PushTokenSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ثبت/حذف اشتراک نوتیف دستگاه — اپ مشتری (v25/v26).
 * POST   /api/v1/push/token  { token, provider?, p256dh?, auth?, platform? }
 * DELETE /api/v1/push/token  { token }
 *
 * provider: firebase (توکن FCM) | webpush (endpoint + کلیدهای اشتراک) |
 *           pusher (شناسهٔ دستگاه Beams)
 *
 * v26.1 — ثبت از طریق PushTokenSync: upsert امن + هره‌سازی توکن‌های
 * مرده (رفع باگ «یک رکورد در هر رفرش» در موبایل).
 */
class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'provider' => ['nullable', 'string', 'in:firebase,webpush,pusher'],
            'p256dh' => ['nullable', 'string', 'max:190'],
            'auth' => ['nullable', 'string', 'max:190'],
            'platform' => ['nullable', 'string', 'in:web,android,ios,windows,other'],
        ], [
            'token.required' => 'توکن دستگاه الزامی است.',
            'token.max' => 'توکن دستگاه نامعتبر است.',
            'provider.in' => 'سرویس نوتیف دستگاه نامعتبر است.',
        ]);

        $provider = $data['provider'] ?? 'firebase';

        // اشتراک وب‌پوش بدون کلیدها بی‌فایده است
        if ($provider === 'webpush' && (empty($data['p256dh']) || empty($data['auth']))) {
            return response()->json([
                'ok' => false,
                'message' => 'برای اشتراک وب‌پوش، کلیدهای p256dh و auth الزامی است.',
            ], 422);
        }

        PushTokenSync::register($request->user(), $data, $request->userAgent());

        return response()->json([
            'ok' => true,
            'message' => 'دستگاه برای دریافت نوتیف‌ها ثبت شد.',
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $deleted = PushToken::query()
            ->where('token', $data['token'])
            ->where('user_id', (int) $request->user()->id)
            ->delete();

        return response()->json([
            'ok' => true,
            'message' => $deleted ? 'دستگاه حذف شد.' : 'توکنی برای حذف یافت نشد.',
        ]);
    }
}
