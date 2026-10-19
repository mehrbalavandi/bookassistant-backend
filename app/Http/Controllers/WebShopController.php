<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 🌟 PAY-6: خرید در سایت با همان روش‌های اپ: درگاهِ اینترنتی و کارت‌به‌کارت (هر کدام که در
 * پنل ← تنظیماتِ سایت ← فروش روشن است).
 *   /checkout/{book}          انتخابِ روش ← درگاه (لینکِ امضاشده‌ی پرداخت) یا صفحه‌ی سفارشِ کارت
 *   /orders, /orders/{order}  سفارش‌های من؛ برای کارت‌به‌کارت مشخصاتِ کارت، مبلغِ دقیق و فرمِ رسید
 */
class WebShopController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function checkout(Book $book)
    {
        $user = Auth::user();
        if ($user->purchasedBooks()->where('books.id', $book->id)->exists()) {
            return redirect()->route('home')->with('error', 'شما قبلاً این کتاب را خریداری کرده‌اید.');
        }
        if (! $book->hasContent()) { // 🌟 SF-4
            return redirect()->route('home')->with('error', 'این کتاب هنوز در حالِ آماده‌سازی است.');
        }

        return view('shop.checkout', [
            'book' => $book,
            'price' => (int) round($book->final_price),
            'methods' => $this->orders->methods(),
            'openCardOrder' => Order::where('user_id', $user->id)->where('book_id', $book->id)
                ->where('method', Order::METHOD_CARD)->whereIn('status', [Order::PENDING, Order::AWAITING_REVIEW])
                ->latest('id')->first(),
        ]);
    }

    public function placeOrder(Request $request, Book $book)
    {
        $data = $request->validate(['method' => 'required|in:gateway,card']);
        $user = Auth::user();
        if ($user->purchasedBooks()->where('books.id', $book->id)->exists()) {
            return redirect()->route('home')->with('error', 'شما قبلاً این کتاب را خریداری کرده‌اید.');
        }
        if (! $book->hasContent()) { // 🌟 SF-4
            return redirect()->route('home')->with('error', 'این کتاب هنوز در حالِ آماده‌سازی است.');
        }
        if (! ($this->orders->methods()[$data['method']] ?? false)) {
            return back()->with('error', $data['method'] === Order::METHOD_CARD
                ? 'کارت‌به‌کارت فعلاً فعال نیست.' : 'پرداختِ اینترنتی فعلاً فعال نیست.');
        }

        $order = $this->orders->create($user, $book, $data['method'], 'web', $request->ip());
        if ($order->status === Order::PAID) {
            return redirect()->route('home')->with('success', 'این کتاب برای شما فعال شد.');
        }
        if ($order->method === Order::METHOD_GATEWAY) {
            return redirect()->to($this->orders->payUrl($order));
        }

        return redirect()->route('orders.show', $order);
    }

    public function index()
    {
        return view('shop.orders', [
            'orders' => Auth::user()->visibleOrders()->with('book:id,title')->latest('id')->limit(50)->get(),
        ]);
    }

    public function show(Order $order)
    {
        abort_unless($order->user_id === Auth::id(), 404);
        $order->load('book:id,title');

        return view('shop.order', [
            'order' => $order,
            'card' => $order->method === Order::METHOD_CARD ? $this->orders->cardInfo() : null,
        ]);
    }

    public function receipt(Request $request, Order $order)
    {
        abort_unless($order->user_id === Auth::id(), 404);
        if (! $order->canSubmitReceipt()) {
            return back()->with('error', 'برای این سفارش نمی‌شود رسید فرستاد.');
        }
        $data = $request->validate([
            'tracking_code' => 'required|string|max:64',
            'card_last4' => 'nullable|digits:4',
            'paid_at' => 'nullable|string|max:32',
            'receipt' => 'nullable|image|max:5120',
        ], [
            'tracking_code.required' => 'کدِ پیگیری یا شماره‌ی مرجعِ واریز را بنویسید.',
            'card_last4.digits' => 'چهار رقمِ آخرِ کارت باید ۴ رقم باشد.',
            'receipt.image' => 'تصویرِ رسید باید عکس باشد (jpg یا png).',
            'receipt.max' => 'حجمِ تصویرِ رسید حداکثر ۵ مگابایت.',
        ]);
        $data['tracking_code'] = strtr(trim($data['tracking_code']), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);

        $path = $request->hasFile('receipt')
            ? $request->file('receipt')->store('receipts/'.$order->user_id, 'local')
            : null;
        $this->orders->submitCardReceipt($order, $data, $path);

        return redirect()->route('orders.show', $order)->with('success', (string) config('shop.card.review_hint'));
    }
}
