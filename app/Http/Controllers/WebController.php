<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class WebController extends Controller
{
    // ۱. نمایش صفحه اصلی
    public function homePage() {
        return view('home'); // این به فایل home.blade.php اشاره میکنه
    }

// شبیه‌سازی انتقال به درگاه پرداخت
    public function checkout(Request $request) {
        $user = auth()->user(); // گرفتن اطلاعات کاربری که لاگین کرده است

        // یک شماره پیگیری تصادفی برای شبیه‌سازی می‌سازیم
        $transactionId = rand(100000, 999999);

        // کاربر را به صفحه درگاه پرداخت شبیه‌ساز خودمان می‌فرستیم
        return view('payment.gateway', [
            'user' => $user,
            'amount' => '۱۴۹,۰۰۰',
            'transactionId' => $transactionId
        ]);
    }

    // بررسی بازگشت از بانک و فعال‌سازی اشتراک
    public function verifyPayment(Request $request) {
        $status = $request->query('status');
        $user = auth()->user();

        if ($status === 'success') {
            // آپدیت دیتابیس: فعال کردن اشتراک کاربر
            $user->is_premium = true;
            $user->save();

            // بازگشت به صفحه اصلی با پیام موفقیت
            return redirect()->route('home')->with('success', 'پرداخت با موفقیت انجام شد. اشتراک ویژه شما فعال است!');
        }

        // بازگشت به صفحه اصلی با پیام خطا
        return redirect()->route('home')->with('error', 'پرداخت لغو شد یا ناموفق بود.');
    }
}