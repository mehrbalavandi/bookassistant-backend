<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuthMethods;
use App\Support\PasswordRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * ورود و ثبت‌نامِ سایت.
 * 🌟 SITE-2: صفحه‌ی /login هر دو روش (موبایل و ایمیل) را نشان می‌دهد و /register ثبت‌نامِ ایمیلی را؛
 * روشی که در پنل خاموش است دیده می‌شود ولی به‌جای فرم پیامِ «فعلاً فعال نیست» دارد، و سرور هم
 * درخواستش را رد می‌کند. ادمین‌ها همیشه می‌توانند با ایمیل وارد شوند (قفل نشوند).
 */
class WebAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.login', [
            'methods' => AuthMethods::toArray(),
            'tab' => $request->query('tab') === 'email' || session('tab') === 'email' ? 'email' : 'mobile',
            // 🌟 SITE-14: ورودِ ادمین وقتی ورودِ ایمیلی برای کاربران خاموش است
            'adminOnly' => ! AuthMethods::email() && ($request->query('for') === 'admin' || session('for') === 'admin'),
            // 🌟 SITE-10: نشست بی‌خروج تمام شده و کاربر از یک صفحه‌ی محافظت‌شده به این‌جا آمده
            'sessionEnded' => \App\Support\LoginRedirect::sessionEnded($request),
        ]);
    }

    public function login(Request $request)
    {
        \App\Support\LoginRedirect::restoreIntended($request); // 🌟 SITE-16
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ], PasswordRules::messages(), PasswordRules::attributes());
        $email = mb_strtolower(trim($credentials['email']));
        $user = User::where('email', $email)->first() ?? User::where('email', $credentials['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            return back()->with('tab', 'email')->with('for', $request->input('for'))->withInput($request->only('email'))->withErrors([
                'email' => AuthMethods::email() ? 'ایمیل یا رمز عبور اشتباه است.' : AuthMethods::disabledMessage(AuthMethods::EMAIL),
            ]);
        }
        if (! AuthMethods::emailAllowedFor($user)) {
            return back()->with('tab', 'email')->withErrors(['email' => AuthMethods::disabledMessage(AuthMethods::EMAIL)]);
        }

        // 🌟 SITE-12: «مرا به خاطر بسپار» فقط اگر در تنظیماتِ سایت روشن باشد
        Auth::login($user, \App\Support\AuthMethods::remember() && $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_seen_at' => now()])->save();

        // 🌟 SITE-10: نشست در یک صفحه تمام شده بود (redirect()->guest آن را در session('url.intended')
        // گذاشته): همان کاربر ← همان صفحه؛ کاربرِ دیگر ← داشبورد.
        return \App\Support\LoginRedirect::to($request, $user);
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.register', ['methods' => AuthMethods::toArray()]);
    }

    public function register(Request $request)
    {
        if (! AuthMethods::email()) {
            return back()->withErrors(['email' => AuthMethods::disabledMessage(AuthMethods::EMAIL)]);
        }
        if (! AuthMethods::registration()) {
            return back()->withErrors(['email' => AuthMethods::registrationClosedMessage()]);
        }
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|confirmed|max:200',
        ], PasswordRules::messages(), PasswordRules::attributes());
        // ADM-2: همان قانونِ رمزِ اپ، با پیامِ فارسی
        if ($problem = PasswordRules::problem($data['password'])) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['password' => $problem]);
        }

        $user = User::create([
            'name' => trim($data['first_name']).' '.trim($data['last_name']),
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => mb_strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'is_admin' => false,
        ]);

        Auth::login($user, \App\Support\AuthMethods::remember());
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'ثبت‌نام شما با موفقیت انجام شد!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
