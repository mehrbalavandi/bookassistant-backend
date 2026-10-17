<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeviceService;
use App\Support\AuthMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // قانونِ رمز در App\Support\PasswordRules (مشترک با ثبت‌نامِ سایت).

    // 🌟 DEV-1: ورود هم مثلِ ورودِ پیامکی دستگاه را ثبت می‌کند و به سقفِ دستگاه پایبند است.
    public function __construct(private readonly DeviceService $devices) {}

    // ۱. ثبت‌نامِ کاربرِ تازه با ایمیل از اپ.
    // 🌟 SITE-2: فقط اگر ورودِ ایمیلی و ثبت‌نامِ تازه هر دو در پنل روشن باشند.
    // first_name و last_name (اپِ تازه) یا name (نسخه‌های قدیمی).
    public function register(Request $request)
    {
        if (! AuthMethods::email()) {
            return AuthMethods::disabledResponse(AuthMethods::EMAIL);
        }
        if (! AuthMethods::registration()) {
            return AuthMethods::registrationClosedResponse();
        }
        if ($legacy = $this->devices->legacyRejection($request)) {
            return $legacy;
        }
        $data = $request->validate([
            'first_name' => 'required_without:name|nullable|string|max:100',
            'last_name' => 'required_without:name|nullable|string|max:100',
            'name' => 'required_without_all:first_name,last_name|nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|max:200',
        ], \App\Support\PasswordRules::messages(), \App\Support\PasswordRules::attributes());
        if ($problem = \App\Support\PasswordRules::problem($data['password'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['password' => $problem]);
        }

        $first = trim((string) ($data['first_name'] ?? ''));
        $last = trim((string) ($data['last_name'] ?? ''));
        $user = User::create([
            'name' => $first !== '' || $last !== '' ? trim($first.' '.$last) : trim((string) $data['name']),
            'first_name' => $first !== '' ? $first : null,
            'last_name' => $last !== '' ? $last : null,
            'email' => mb_strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'is_admin' => false, // کاربران اپلیکیشن به صورت پیش‌فرض ادمین نیستند
        ]);

        // توکنِ وابسته به همین دستگاه (DEV-1)
        return $this->devices->signIn($user, $this->devices->deviceInfo($request), 'ثبت‌نام با موفقیت انجام شد.', 201);
    }

    // ۲. ورود با ایمیل و رمز. 🌟 SITE-2: اگر ایمیل در پنل خاموش است، فقط ادمین‌ها.
    public function login(Request $request)
    {
        if ($legacy = $this->devices->legacyRejection($request)) {
            return $legacy;
        }
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], \App\Support\PasswordRules::messages(), \App\Support\PasswordRules::attributes());

        $user = User::where('email', mb_strtolower(trim($request->email)))->first()
            ?? User::where('email', $request->email)->first();

        // بررسی وجود کاربر و صحت رمز عبور
        if (! $user || ! $user->password || ! Hash::check($request->password, $user->password)) {
            if (! AuthMethods::email()) {
                return AuthMethods::disabledResponse(AuthMethods::EMAIL);
            }

            return response()->json([
                'success' => false,
                'message' => 'ایمیل یا رمز عبور اشتباه است.'
            ], 401);
        }
        if (! AuthMethods::emailAllowedFor($user)) {
            return AuthMethods::disabledResponse(AuthMethods::EMAIL);
        }

        // توکنِ وابسته به همین دستگاه + مجوزِ آفلاین، یا ۴۰۹ِ سقفِ دستگاه (DEV-1..4)
        return $this->devices->signIn($user, $this->devices->deviceInfo($request), 'ورود با موفقیت انجام شد.');
    }

    // ۳. متد خروج (Logout) و باطل کردن توکن
    public function logout(Request $request)
    {
        // حذف توکنی که کاربر با آن به API متصل شده است
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'خروج با موفقیت انجام شد و توکن باطل گردید.'
        ], 200);
    }
}
