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

    // ۲. شبیه‌سازی انتقال به درگاه پرداخت
    public function checkout(Request $request) {
        // در دنیای واقعی اینجا کاربر باید لاگین باشد، برای تست فرض میکنیم کاربر شناسه ۱ است
        // $userId = auth()->id(); 
        $userId = 1; // یک کاربر نمونه برای تست محلی

        // در اینجا به درگاه متصل می‌شوید و یک شماره تراکنش (Authority) می‌گیرید
        // سپس کاربر رو به درگاه بانک هدایت می‌کنید.
        // فعلاً برای تست، کاربر را مستقیماً به صفحه تایید پرداخت می‌فرستیم:
        
        return redirect()->route('payment.verify', [
            'status' => 'OK',
            'user_id' => $userId
        ]);
    }

    // ۳. شبیه‌سازی بازگشت از بانک و فعال‌سازی اشتراک
    public function verifyPayment(Request $request) {
        $status = $request->query('status');
        $userId = $request->query('user_id');

        if ($status === 'OK') {
            // پیدا کردن کاربر و تغییر وضعیت اشتراک به Premium
            $user = User::find($userId);
            if ($user) {
                $user->is_premium = true;
                $user->save();
            }

            // نمایش صفحه موفقیت‌آمیز بودن پرداخت
            return "پرداخت شما با موفقیت انجام شد! اشتراک شما فعال شد و اکنون می‌توانید از طریق اپلیکیشن فلاتر فایل‌ها را دانلود کنید.";
        }

        return "پرداخت ناموفق بود یا توسط کاربر لغو شد.";
    }
}