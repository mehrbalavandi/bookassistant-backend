<?php

namespace App\Http\Controllers;

use App\Support\AuthMethods;
use App\Support\PasswordRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 🌟 SITE-13: تغییرِ رمزِ عبور در سایت (کاربرانِ ایمیلی؛ کاربرِ موبایلیِ بی‌ایمیل رمز لازم ندارد).
 *
 * فرم طوری است که مدیرِ رمزِ مرورگر بعد از ذخیره «به‌روزرسانیِ رمز؟» را بپرسد: فیلدِ پنهانِ
 * username (ایمیل)، رمزِ فعلی با autocomplete=current-password و رمزِ تازه با new-password، و
 * ارسالِ واقعیِ فرم که با یک redirect تمام می‌شود.
 */
class AccountController extends Controller
{
    public function editPassword(Request $request)
    {
        $user = $request->user();

        return view('account.password', [
            'user' => $user,
            'hasPassword' => filled($user->password),
            'passwordHint' => AuthMethods::toArray()['password_hint'] ?? null,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        if (blank($user->email)) {
            return back()->withErrors(['password' => 'حسابِ شما با موبایل و کدِ پیامکی است و رمز لازم ندارد.']);
        }
        $hasPassword = filled($user->password);
        $data = $request->validate([
            'current_password' => $hasPassword ? 'required|string' : 'nullable',
            'password' => 'required|string|confirmed|max:200',
        ], PasswordRules::messages(), PasswordRules::attributes() + [
            'current_password' => 'رمزِ فعلی', 'password' => 'رمزِ تازه',
        ]);

        if ($hasPassword && ! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'رمزِ فعلی درست نیست.']);
        }
        if ($problem = PasswordRules::problem($data['password'])) {
            return back()->withErrors(['password' => $problem]);
        }

        // کوکی‌های «مرا به خاطر بسپار»ِ مرورگرهای دیگر باطل شوند؛ همین مرورگر (اگر داشت) کوکیِ تازه می‌گیرد.
        $remembered = $request->cookies->has(Auth::guard('web')->getRecallerName());
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();
        Auth::guard('web')->login($user, $remembered && AuthMethods::remember());
        // پنلِ ادمین (AuthenticateSession) هَشِ رمز را در نشست نگه می‌دارد؛ بی‌این، ادمین بیرون انداخته می‌شد.
        $request->session()->put('password_hash_web', $user->getAuthPassword());

        return redirect()->route('account.password')
            ->with('success', $hasPassword ? 'رمزِ عبور عوض شد.' : 'رمزِ عبور ساخته شد؛ از این پس با ایمیل و همین رمز هم وارد می‌شوید.');
    }
}
