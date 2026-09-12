<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ثبت/حذف اشتراک نوتیف دستگاه برای کاربران پنل‌ها — v25/v26.
 * روت در هر ۴ گروه پنل (admin/org/coffeenet/operator) به همین کنترلر می‌رسد.
 *
 * provider: firebase (توکن FCM) | webpush (endpoint + کلیدهای اشتراک) |
 *           pusher (شناسهٔ دستگاه Beams)
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

        if ($provider === 'webpush' && (empty($data['p256dh']) || empty($data['auth']))) {
            return response()->json([
                'ok' => false,
                'message' => 'برای اشتراک وب‌پوش، کلیدهای p256dh و auth الزامی است.',
            ], 422);
        }

        PushToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => (int) $request->user()->id,
                'provider' => $provider,
                'p256dh' => $provider === 'webpush' ? ($data['p256dh'] ?? null) : null,
                'auth' => $provider === 'webpush' ? ($data['auth'] ?? null) : null,
                'platform' => $data['platform'] ?? 'web',
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => 'دستگاه برای دریافت نوتیف‌ها ثبت شد.',
        ]);
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
