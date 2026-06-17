<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class WebAuthController extends Controller
{
    // ۱. نمایش صفحه ثبت‌نام
    public function showRegister() {
        return view('auth.register');
    }

    // ۲. پردازش اطلاعات ثبت‌نام
    public function register(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed', // confirmed یعنی فیلد تکرار رمز عبور اجباری است
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // لاگین کردن کاربر بلافاصله پس از ثبت‌نام
        Auth::login($user);

        return redirect()->route('home');
    }

    // ۳. نمایش صفحه ورود
    public function showLogin() {
        return view('auth.login');
    }

    // ۴. پردازش اطلاعات ورود
    public function login(Request $request) {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->route('home');
        }

        return back()->withErrors([
            'email' => 'ایمیل یا رمز عبور اشتباه است.',
        ]);
    }

    // ۵. خروج از حساب کاربری
    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
