<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Support\Mobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 🌟 AUTH-1، AUTH-2: ورود/ثبت‌نام با شماره‌ی موبایل و کدِ پیامکی.
 *
 * ۱) POST /api/auth/otp/request {mobile}
 *    ← {registered, retry_after, expires_in} — registered=false یعنی کاربرِ تازه، پس اپ
 *      نام و نام خانوادگی را هم می‌پرسد.
 * ۲) POST /api/auth/otp/verify {mobile, code, first_name?, last_name?, device_name?}
 *    ← {token, user}. کاربرِ تازه بدونِ نام ← 422 با needs_profile=true.
 */
class OtpAuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function request(Request $request): JsonResponse
    {
        $mobile = Mobile::normalize($request->input('mobile'));
        if (! $mobile) {
            return $this->invalidMobile();
        }
        if (User::onlyTrashed()->where('mobile', $mobile)->exists()) {
            return $this->deactivated();
        }

        try {
            $result = $this->otp->request($mobile, $request->ip());
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'ارسالِ پیامک ممکن نشد. چند دقیقه‌ی دیگر دوباره امتحان کنید.',
            ], 503);
        }

        $body = [
            'success' => $result['sent'],
            'mobile' => $mobile,
            'registered' => User::where('mobile', $mobile)->exists(),
            'retry_after' => $result['retry_after'],
            'expires_in' => (int) config('sms.otp.ttl_seconds'),
            'code_length' => (int) config('sms.otp.length'),
            'message' => $result['sent']
                ? 'کدِ ورود به '.$mobile.' پیامک شد.'
                : 'کدِ قبلی هنوز معتبر است؛ '.$result['retry_after'].' ثانیه‌ی دیگر می‌توانید دوباره درخواست کنید.',
        ];
        if (isset($result['code'])) {
            $body['debug_code'] = $result['code']; // فقط SMS_DRIVER=log و APP_DEBUG
        }

        return response()->json($body, $result['sent'] ? 200 : 429);
    }

    public function verify(Request $request): JsonResponse
    {
        $mobile = Mobile::normalize($request->input('mobile'));
        if (! $mobile) {
            return $this->invalidMobile();
        }
        $data = $request->validate([
            'code' => 'required|string|max:10',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'device_name' => 'nullable|string|max:100',
        ]);

        if (User::onlyTrashed()->where('mobile', $mobile)->exists()) {
            return $this->deactivated();
        }
        $user = User::where('mobile', $mobile)->first();
        $first = trim((string) ($data['first_name'] ?? ''));
        $last = trim((string) ($data['last_name'] ?? ''));
        // نام را قبل از مصرفِ کد چک می‌کنیم تا کاربرِ تازه برای واردکردنِ نام کدِ تازه نخواهد.
        if (! $user && ($first === '' || $last === '')) {
            return response()->json([
                'success' => false,
                'needs_profile' => true,
                'message' => 'برای ساختنِ حساب، نام و نام خانوادگی را وارد کنید.',
            ], 422);
        }

        $result = $this->otp->verify($mobile, $data['code']);
        if ($result !== OtpService::OK) {
            return response()->json([
                'success' => false,
                'reason' => $result,
                'message' => OtpService::message($result),
            ], 422);
        }

        if (! $user) {
            $user = User::create([
                'name' => $first.' '.$last,
                'first_name' => $first,
                'last_name' => $last,
                'mobile' => $mobile,
                'mobile_verified_at' => now(),
                'is_admin' => false,
            ]);
        } elseif (! $user->mobile_verified_at) {
            $user->forceFill(['mobile_verified_at' => now()])->save();
        }

        $user->forceFill(['last_seen_at' => now()])->save();
        $token = $user->createToken(mb_substr($data['device_name'] ?? 'flutter-app-token', 0, 100))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'خوش آمدید، '.$user->first_name.'!',
            'token' => $token,
            'user' => $user->toAppArray(),
        ]);
    }

    /** GET /api/me */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'user' => $request->user()->toAppArray()]);
    }

    /** PATCH /api/me {first_name, last_name} */
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
        ]);
        $user = $request->user();
        $user->forceFill([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'name' => trim($data['first_name']).' '.trim($data['last_name']),
        ])->save();

        return response()->json(['success' => true, 'user' => $user->toAppArray()]);
    }

    /**
     * POST /api/me/mobile {mobile, code} — حسابِ قدیمیِ ایمیلی شماره‌ی موبایل می‌گیرد
     * (کدش را قبلاً با /auth/otp/request گرفته). از آن به بعد با همان شماره وارد می‌شود
     * و خریدها و داده‌هایش سرِ جایشان می‌مانند.
     */
    public function linkMobile(Request $request): JsonResponse
    {
        $mobile = Mobile::normalize($request->input('mobile'));
        if (! $mobile) {
            return $this->invalidMobile();
        }
        $request->validate(['code' => 'required|string|max:10']);
        $user = $request->user();
        if (User::withTrashed()->where('mobile', $mobile)->where('id', '!=', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'این شماره به حسابِ دیگری وصل است.',
            ], 409);
        }
        $result = $this->otp->verify($mobile, (string) $request->input('code'));
        if ($result !== OtpService::OK) {
            return response()->json(['success' => false, 'reason' => $result, 'message' => OtpService::message($result)], 422);
        }
        $user->forceFill(['mobile' => $mobile, 'mobile_verified_at' => now()])->save();

        return response()->json(['success' => true, 'user' => $user->toAppArray()]);
    }

    /** ADM-3: حسابی که ادمین غیرفعال کرده (داده‌هایش نگه داشته شده). */
    private function deactivated(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'این حساب غیرفعال شده است؛ برای فعال‌سازیِ دوباره با پشتیبانی تماس بگیرید.',
        ], 403);
    }

    private function invalidMobile(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'شماره‌ی موبایل معتبر نیست (مثلاً 09121234567).',
        ], 422);
    }
}
