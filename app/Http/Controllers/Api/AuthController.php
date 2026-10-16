<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // قانونِ رمز در App\Support\PasswordRules (مشترک با ثبت‌نامِ سایت).

    // 🌟 DEV-1: ورود هم مثلِ ورودِ پیامکی دستگاه را ثبت می‌کند و به سقفِ دستگاه پایبند است.
    public function __construct(private readonly DeviceService $devices) {}

    // ۱. متد ثبت‌نام کاربر جدید از طریق اپلیکیشن فلاتر
    public function register(Request $request)
    {
        if ($legacy = $this->devices->legacyRejection($request)) {
            return $legacy;
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users|max:255',
            'password' => ['required', 'string', \App\Support\PasswordRules::forUsers()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_admin' => false, // کاربران اپلیکیشن به صورت پیش‌فرض ادمین نیستند
        ]);

        // توکنِ وابسته به همین دستگاه (DEV-1)
        return $this->devices->signIn($user, $this->devices->deviceInfo($request), 'ثبت‌نام با موفقیت انجام شد.', 201);
    }

    // ۲. متد ورود (Login) کاربران قدیمی
    public function login(Request $request)
    {
        if ($legacy = $this->devices->legacyRejection($request)) {
            return $legacy;
        }
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // بررسی وجود کاربر و صحت رمز عبور
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'مشخصات وارد شده با اطلاعات ما مطابقت ندارد.'
            ], 401);
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
