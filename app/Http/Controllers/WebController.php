<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Book; // ⚠️ اضافه شدن مدل کتاب
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
// کلاس‌های پرداخت
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Illuminate\Support\Facades\Auth;

class WebController extends Controller
{

    // ۱. نمایش صفحه فرم ثبت‌نام
    public function showRegisterForm()
    {
        if (Auth::check()) {
            return redirect('/');
        }
        return view('register');
    }

    // ۲. پردازش اطلاعات فرم، ذخیره در دیتابیس و لاگین آنی
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_admin' => false,
        ]);

        Auth::login($user);

        return redirect('/')->with('success', 'ثبت‌نام شما با موفقیت انجام شد!');
    }

    // ۱. اصلاح متد نمایش صفحه اصلی برای فرستادن فیلد تخفیف به قالب سایت
    public function homePage()
    {
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect('/admin');
        }

        // 🌟 اضافه کردن قیمت و تخفیف به کوئری
        $books = Book::select('id', 'title', 'folder_name', 'price', 'discount')->get();
        $purchasedBookIds = [];

        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $purchasedBookIds = $user->purchasedBooks()->pluck('books.id')->toArray();
        }

        $books->transform(function ($book) use ($purchasedBookIds) {
            $book->is_purchased = in_array($book->id, $purchasedBookIds);
            return $book;
        });

        if (!Auth::check()) {
            return view('welcome', compact('books'));
        }

        return view('dashboard', compact('books'));
    }

    // ۲. اصلاح متد پرداخت برای استفاده از قیمت با تخفیف
    public function checkout(Request $request, Book $book)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->purchasedBooks()->where('books.id', $book->id)->exists()) {
            return redirect()->back()->with('error', 'شما قبلاً این کتاب را خریداری کرده‌اید.');
        }

        // 🌟 استفاده از اکسسور هوشمند نهایی (قیمت بعد از کسر تخفیف)
        $amount = $book->final_price;

        // اگر قیمت نهایی صفر بود (یا کتاب کلاً رایگان بوده یا تخفیف ۱۰۰٪ داشته)
        if ($amount == 0) {
            $user->purchasedBooks()->syncWithoutDetaching([$book->id]);
            return redirect()->back()->with('success', 'این محصول برای شما فعال شد.');
        }

        $invoice = (new Invoice)->amount($amount);
        $invoice->detail(['description' => 'خرید ' . $book->title . ' برای ' . $user->name]);

        try {
            return Payment::via('zarinpal') // یا هر درگاهی که تنظیم کردید
                ->callbackUrl(route('payment.verify'))
                ->purchase($invoice, function ($driver, $transactionId) use ($book) {
                    session([
                        'transaction_id' => $transactionId,
                        'pending_book_id' => $book->id
                    ]);
                })->pay()->render();
        } catch (\Exception $exception) {
            return redirect()->route('home')->with('error', 'خطا در اتصال به درگاه: ' . $exception->getMessage());
        }
    }

    // ۳. اصلاح متد تاییدیه پرداخت
    public function verifyPayment(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $transactionId = session('transaction_id');
        $bookId = session('pending_book_id');

        if (!$transactionId || !$bookId) {
            return redirect('/')->with('error', 'اطلاعات تراکنش یافت نشد.');
        }

        $book = Book::findOrFail($bookId);
        // 🌟 تطبیق با قیمت نهایی تخفیف‌خورده در تاییدیه بانک
        $amount = $book->final_price;

        try {
            $receipt = Payment::amount($amount)->transactionId($transactionId)->verify();
            $user->purchasedBooks()->syncWithoutDetaching([$book->id]);
            session()->forget(['transaction_id', 'pending_book_id']);

            return redirect('/')->with('success', 'پرداخت موفقیت‌آمیز بود. شماره پیگیری: ' . $receipt->getReferenceId());
        } catch (InvalidPaymentException $exception) {
            return redirect('/')->with('error', 'پرداخت ناموفق بود: ' . $exception->getMessage());
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
