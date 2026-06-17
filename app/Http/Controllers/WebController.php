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
}