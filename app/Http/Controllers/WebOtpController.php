<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OtpService;
use App\Support\AuthMethods;
use App\Support\Mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 🌟 AUTH-1: ورود/ثبت‌نام در سایت با موبایل و کدِ پیامکی — همان حسابی که اپ دارد.
 * دو مرحله در یک صفحه: شماره ← کد (و برای کاربرِ تازه نام و نام خانوادگی).
 */
class WebOtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function show(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }
        // 🌟 SITE-2: موبایل خاموش ← صفحه‌ی ورود (که پیامِ «فعلاً فعال نیست» را نشان می‌دهد)
        if (! AuthMethods::mobile()) {
            return redirect()->route('login');
        }
        $mobile = session('otp_mobile');

        return view('auth.login-otp', [
            'mobile' => $mobile,
            'registered' => $mobile ? User::where('mobile', $mobile)->exists() : null,
            'debugCode' => session('otp_debug_code'),
        ]);
    }

    public function request(Request $request)
    {
        if (! AuthMethods::mobile()) {
            return redirect()->route('login')->withErrors(['mobile' => AuthMethods::disabledMessage(AuthMethods::MOBILE)]);
        }
        $mobile = Mobile::normalize($request->input('mobile'));
        if (! $mobile) {
            return back()->withErrors(['mobile' => 'شماره‌ی موبایل معتبر نیست (مثلاً 09121234567).'])->withInput();
        }
        if (User::onlyTrashed()->where('mobile', $mobile)->exists()) {
            return back()->withErrors(['mobile' => 'این حساب غیرفعال شده است؛ با پشتیبانی تماس بگیرید.'])->withInput();
        }
        if (! AuthMethods::registration() && ! User::where('mobile', $mobile)->exists()) {
            return back()->withErrors(['mobile' => AuthMethods::registrationClosedMessage()])->withInput();
        }
        try {
            $r = $this->otp->request($mobile, $request->ip());
        } catch (\Throwable) {
            return back()->withErrors(['mobile' => 'ارسالِ پیامک ممکن نشد؛ چند دقیقه‌ی دیگر امتحان کنید.'])->withInput();
        }
        session(['otp_mobile' => $mobile]);
        if (isset($r['code'])) {
            session()->flash('otp_debug_code', $r['code']);
        }

        return redirect()->route('login.otp')->with(
            $r['sent'] ? 'success' : 'error',
            $r['sent'] ? 'کدِ ورود پیامک شد.' : 'کدِ قبلی هنوز معتبر است؛ '.$r['retry_after'].' ثانیه‌ی دیگر دوباره امتحان کنید.',
        );
    }

    public function verify(Request $request)
    {
        if (! AuthMethods::mobile()) {
            return redirect()->route('login')->withErrors(['mobile' => AuthMethods::disabledMessage(AuthMethods::MOBILE)]);
        }
        $mobile = session('otp_mobile');
        if (! $mobile) {
            return redirect()->route('login.otp');
        }
        $data = $request->validate([
            'code' => 'required|string|max:10',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
        ]);
        $user = User::where('mobile', $mobile)->first();
        if (! $user && ! AuthMethods::registration()) {
            return back()->withErrors(['code' => AuthMethods::registrationClosedMessage()]);
        }
        $first = trim((string) ($data['first_name'] ?? ''));
        $last = trim((string) ($data['last_name'] ?? ''));
        if (! $user && ($first === '' || $last === '')) {
            return back()->withErrors(['first_name' => 'نام و نام خانوادگی را وارد کنید.'])->withInput();
        }

        $result = $this->otp->verify($mobile, $data['code']);
        if ($result !== OtpService::OK) {
            return back()->withErrors(['code' => OtpService::message($result)])->withInput();
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
        }
        $user->forceFill(['last_seen_at' => now(), 'mobile_verified_at' => $user->mobile_verified_at ?? now()])->save();

        session()->forget('otp_mobile');
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
