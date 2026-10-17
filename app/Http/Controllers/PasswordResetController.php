<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuthMethods;
use App\Support\PasswordRules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * 🌟 SITE-6: بازیابیِ رمزِ حساب‌های ایمیلی.
 * - سایت: ‎/forgot-password‎ (ایمیل) ← لینکِ ایمیل‌شده ← ‎/reset-password/{token}‎ (رمزِ تازه).
 * - اپ: POST /api/password/forgot {email} همان لینک را می‌فرستد؛ رمزِ تازه در سایت ساخته می‌شود.
 * پاسخ همیشه یکسان است، چه ایمیل ثبت شده باشد چه نه (کسی نتواند با آن حساب‌ها را پیدا کند).
 * ایمیل با تنظیماتِ «ایمیل» در پنل فرستاده می‌شود؛ اگر روی log باشد فقط در لاگ نوشته می‌شود.
 */
class PasswordResetController extends Controller
{
    private const SENT = 'اگر این ایمیل در سایت ثبت شده باشد، لینکِ ساختنِ رمزِ تازه به آن فرستاده شد. پوشه‌ی Spam را هم ببینید.';

    public function showForgot()
    {
        return view('auth.forgot-password', ['methods' => AuthMethods::toArray()]);
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => 'required|email'], PasswordRules::messages(), PasswordRules::attributes());
        $this->send((string) $request->input('email'));

        return back()->with('success', self::SENT);
    }

    /** POST /api/password/forgot */
    public function apiSendLink(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email'], PasswordRules::messages(), PasswordRules::attributes());
        $this->send((string) $request->input('email'));

        return response()->json(['success' => true, 'message' => self::SENT]);
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'passwordHint' => PasswordRules::hint(),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|confirmed|max:200',
        ], PasswordRules::messages(), PasswordRules::attributes());
        if ($problem = PasswordRules::problem($request->input('password'))) {
            return back()->withInput($request->only('email'))->withErrors(['password' => $problem]);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    // ایمیل با کلیک روی لینک تأیید شد
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login', ['tab' => 'email'])
                ->with('success', 'رمزِ تازه ذخیره شد؛ حالا با آن وارد شوید.');
        }

        return back()->withInput($request->only('email'))->withErrors([
            'email' => $status === Password::INVALID_TOKEN
                ? 'لینک منقضی شده یا قبلاً استفاده شده است؛ دوباره درخواست کنید.'
                : 'ساختنِ رمزِ تازه ممکن نشد؛ دوباره امتحان کنید.',
        ]);
    }

    private function send(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $user = User::where('email', $email)->first();
        // روشِ ایمیل خاموش است ← فقط ادمین‌ها (همان قاعده‌ی ورود)
        if (! $user || ! AuthMethods::emailAllowedFor($user)) {
            return;
        }
        try {
            Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            report($e); // تنظیماتِ SMTP درست نیست؛ کاربر همان پیامِ یکسان را می‌بیند
        }
    }
}
