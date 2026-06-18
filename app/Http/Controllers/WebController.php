<?php

namespace App\Http\Controllers;

use App\Models\User; // ⚠️ حتماً در بالای فایل باشد
use Illuminate\Support\Facades\Hash; // ⚠️ حتماً در بالای فایل باشد
use Illuminate\Http\Request;
// این سه خط آدرس کلاس‌های پرداخت را به لاراول معرفی می‌کنند
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
//
use Illuminate\Support\Facades\Auth;

class WebController extends Controller
{
    // ۱. نمایش صفحه اصلی
    public function homePage()
    {
        // ۱. اگر کاربر اصلاً لاگین نکرده است -> صفحه معرفی (welcome) را نشان بده
        if (!Auth::check()) {
            return view('welcome');
        }

        // ۲. اگر لاگین کرده و ادمین است -> بفرستش به پنل فیلامنت
        if (Auth::user()->is_admin) {
            return redirect('/admin');
        }

        // ۳. اگر لاگین کرده ولی ادمین نیست (کاربر عادی) -> داشبورد کاربران عادی را نشان بده
        return view('dashboard'); 
    }

    // ۱. نمایش صفحه فرم ثبت‌نام
    public function showRegisterForm()
    {
        // اگر کاربر از قبل لاگین هست، نیازی نیست دوباره ثبت نام کند
        if (Auth::check()) {
            return redirect('/');
        }
        return view('register');
    }

    // ۲. پردازش اطلاعات فرم، ذخیره در دیتابیس و لاگین آنی
    public function register(Request $request)
    {
        // اعتبار سنجی داده‌های ورودی از سایت
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', // فیلد تکرار رمز عبور اجباری است
        ]);

        // ایجاد کاربر جدید در دیتابیس (با وضعیت کاربر عادی)
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_admin' => false, 
        ]);

        // 🔥 خواسته دوم شما: لاگین کردن آنی کاربر بلافاصله پس از ثبت‌نام موفق
        Auth::login($user);

        // هدایت کاربر به صفحه اصلی (که حالا چون لاگین شده، داشبورد جدید را می‌بیند)
        return redirect('/')->with('success', 'ثبت‌نام شما با موفقیت انجام شد!');
    }

// ۱. ارسال کاربر به درگاه پرداخت واقعی
    public function checkout(Request $request) {
        $user = auth()->user();
        $amount = 149000; // مبلغ اشتراک به تومان

        // ایجاد یک فاکتور جدید
        $invoice = (new Invoice)->amount($amount);
        $invoice->detail(['description' => 'خرید اشتراک ویژه آکادمی زبان برای ' . $user->name]);

        try {
            return Payment::callbackUrl(route('payment.verify'))
                ->purchase($invoice, function($driver, $transactionId) use ($user) {
                    // در اینجا باید $transactionId را در دیتابیس ذخیره کنید
                    // تا وقتی کاربر از بانک برگشت، بدانید این پرداخت برای چه کسی بوده است.
                    // برای سادگی کار در این مرحله، ما آن را در Session ذخیره می‌کنیم:
                    session(['transaction_id' => $transactionId]);
                })->pay()->render();
                
        } catch (\Exception $exception) {
            // اگر ارتباط با بانک برقرار نشد
            return redirect()->route('home')->with('error', 'خطا در اتصال به درگاه بانکی: ' . $exception->getMessage());
        }
    }

    // ۲. بررسی بازگشت کاربر از بانک و تایید تراکنش
    public function verifyPayment(Request $request) {
        $user = auth()->user();
        $amount = 149000; // همان مبلغی که در مرحله قبل ارسال کردیم
        $transactionId = session('transaction_id');

        try {
            // ارسال درخواست به بانک برای تایید نهایی پرداخت
            $receipt = Payment::amount($amount)->transactionId($transactionId)->verify();

            // اگر به این خط برسیم، یعنی پرداخت ۱۰۰٪ موفق و واقعی بوده است
            // شماره پیگیری بانکی: $receipt->getReferenceId()

            // فعال‌سازی اشتراک کاربر
            $user->is_premium = true;
            $user->save();

            // پاک کردن توکن از سشن
            session()->forget('transaction_id');

            return redirect()->route('home')->with('success', 'پرداخت با موفقیت انجام شد. شماره پیگیری: ' . $receipt->getReferenceId());

        } catch (InvalidPaymentException $exception) {
            // اگر کاربر انصراف داده باشد یا کارت موجودی نداشته باشد
            return redirect()->route('home')->with('error', 'پرداخت ناموفق بود: ' . $exception->getMessage());
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();

        // پاک کردن جلسات قبلی برای امنیت بیشتر
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // هدایت کاربر به صفحه اصلی سایت
        return redirect('/');
    }    
}