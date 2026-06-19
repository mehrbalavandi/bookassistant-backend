<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ۱. متد ثبت‌نام کاربر جدید از طریق اپلیکیشن فلاتر
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users|max:255',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_admin' => false, // کاربران اپلیکیشن به صورت پیش‌فرض ادمین نیستند
        ]);

        // ایجاد توکن برای کاربر تازه‌وارد
        $token = $user->createToken('flutter-app-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'ثبت‌نام با موفقیت انجام شد.',
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ]
        ], 201);
    }

    // ۲. متد ورود (Login) کاربران قدیمی
    public function login(Request $request)
    {
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

        // 💡 ایده برای آینده (بررسی وضعیت اشتراک):
        // اگر فیلدی مثل has_premium در دیتابیس دارید، می‌توانید اینجا چک کنید.

        // ایجاد توکن جدید برای این نشست (Session)
        $token = $user->createToken('flutter-app-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'ورود با موفقیت انجام شد.',
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin, // فلاتر متوجه شود این کاربر ادمین است یا خیر
            ]
        ]);
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