<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ثبت/حذف توکن نوتیف دستگاه (Web Push / FCM) برای کاربران پنل‌ها — v25.
 * روت در هر ۴ گروه پنل (admin/org/coffeenet/operator) به همین کنترلر می‌رسد.
 */
class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['nullable', 'string', 'in:web,android,ios,windows,other'],
        ], [
            'token.required' => 'توکن دستگاه الزامی است.',
            'token.max' => 'توکن دستگاه نامعتبر است.',
        ]);

        PushToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => (int) $request->user()->id,
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
