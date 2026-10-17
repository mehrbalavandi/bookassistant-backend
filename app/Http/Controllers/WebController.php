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

    // ثبت‌نام و ورود در WebAuthController و WebOtpController است (SITE-2).

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

    // خرید از سایت در WebShopController است (PAY-6).

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
