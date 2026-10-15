<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Book; // ⚠️ اضافه شدن مدل کتاب
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\OrderService;
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
            'password' => ['required', 'string', 'confirmed', \App\Support\PasswordRules::forUsers()], // ADM-2: همان قانونِ اپ
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
        $books = Book::select('id', 'title', 'folder_name', 'price', 'discount', 'category_id', 'sort_order')
            ->with('category:id,title,description,sort_order')
            ->get();
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

        // 🌟 فهرستِ دسته‌بندی‌شده: اول دسته‌ها (به ترتیبِ شماره‌ی ردیفِ دسته)، با
        // انتخابِ هر دسته فهرستِ کتاب‌هایش (به ترتیبِ شماره‌ی ردیفِ کتاب). اگر فقط
        // یک دسته هست، همان مستقیم باز می‌شود (یک کلیکِ بی‌فایده کمتر).
        $books = Book::sortForCatalog($books);
        $groups = Book::groupForCatalog($books);
        $selectedKey = request()->query('category');
        $selected = $selectedKey !== null ? $groups->firstWhere('key', (string) $selectedKey) : null;
        if (! $selected && $groups->count() === 1) {
            $selected = $groups->first();
        }

        $data = compact('books', 'groups', 'selected');

        if (!Auth::check()) {
            return view('welcome', $data);
        }

        return view('dashboard', $data);
    }

    // ۲. خرید از سایت (PAY-2): سفارش ساخته می‌شود و کاربر به لینکِ امضاشده‌ی پرداخت
    // می‌رود — همان مسیری که اپ هم استفاده می‌کند (PaymentController).
    public function checkout(Request $request, Book $book, OrderService $orders)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->purchasedBooks()->where('books.id', $book->id)->exists()) {
            return redirect()->back()->with('error', 'شما قبلاً این کتاب را خریداری کرده‌اید.');
        }

        $order = $orders->create($user, $book, Order::METHOD_GATEWAY, 'web', $request->ip());
        if ($order->status === Order::PAID) {
            return redirect()->back()->with('success', 'این محصول برای شما فعال شد.');
        }

        return redirect()->to($orders->payUrl($order));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
