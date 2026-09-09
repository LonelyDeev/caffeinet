<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Services\Customer\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * احراز هویت مشتری — ورود با OTP موبایل.
 */
class AuthController extends Controller
{
    /** POST /api/v1/otp/request */
    public function otpRequest(Request $request, OtpService $otp): JsonResponse
    {
        $result = $otp->request(
            (string) $request->input('mobile', ''),
            (string) $request->ip()
        );

        return response()->json([
            'message' => 'کد تأیید پیامک شد.'.($result['dev_code'] ? ' (محیط توسعه)' : ''),
            'expires_in' => $result['expires_in'],
            'resend_in' => $result['resend_in'],
            'dev_code' => $result['dev_code'],
        ]);
    }

    /** POST /api/v1/otp/verify */
    public function otpVerify(Request $request, OtpService $otp): JsonResponse
    {
        $result = $otp->verify(
            (string) $request->input('mobile', ''),
            (string) $request->input('code', '')
        );

        return response()->json([
            'message' => 'خوش آمدید!',
            'token' => $result['token'],
            'profile_completed' => $result['user']->profile_completed,
            'user' => UserResource::make($result['user']->load(['province', 'city'])),
        ]);
    }

    /** POST /api/v1/logout — ابطال توکن جاری */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->currentAccessToken()?->delete();

        // تاریخچهٔ خروج مشتری (پروفایل پنل مدیریت — درخواست بازخوردی)
        if ($user) {
            \App\Services\Auth\LoginLogger::log($user, 'logout', 'customer');
        }

        return response()->json(['message' => 'با موفقیت خارج شدید.']);
    }
}
