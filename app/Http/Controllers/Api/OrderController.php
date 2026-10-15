<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 🌟 PAY-2، PAY-3: خرید از داخلِ اپ.
 *   GET  /api/shop/options                 ← کدام روش‌ها فعال‌اند (+ مشخصاتِ کارت)
 *   POST /api/orders {book_id, method}     ← سفارش؛ درگاه: pay_url، کارت: مشخصاتِ کارت
 *   GET  /api/orders                       ← سفارش‌های من
 *   POST /api/orders/{order}/receipt       ← رسیدِ کارت‌به‌کارت (multipart)
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'gateway' => (bool) config('shop.gateway_enabled'),
            'card' => $this->cardInfo(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()->with('book:id,title')->latest('id')->limit(30)->get();

        return response()->json(['success' => true, 'orders' => $orders->map->toAppArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => 'required|integer|exists:books,id',
            'method' => 'required|in:gateway,card',
        ]);
        $user = $request->user();
        $book = Book::findOrFail($data['book_id']);

        if ($user->purchasedBooks()->where('books.id', $book->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'این کتاب را قبلاً خریده‌اید.'], 409);
        }
        if ($data['method'] === Order::METHOD_GATEWAY && ! config('shop.gateway_enabled')) {
            return response()->json(['success' => false, 'message' => 'پرداختِ اینترنتی فعلاً فعال نیست.'], 422);
        }
        if ($data['method'] === Order::METHOD_CARD && ! $this->cardInfo()) {
            return response()->json(['success' => false, 'message' => 'کارت‌به‌کارت فعلاً فعال نیست.'], 422);
        }

        $order = $this->orders->create($user, $book, $data['method'], 'app', $request->ip());
        $order->load('book:id,title');

        $body = ['success' => true, 'order' => $order->toAppArray()];
        if ($order->status === Order::PAID) {
            $body['message'] = 'این کتاب برای شما فعال شد.';
        } elseif ($order->method === Order::METHOD_GATEWAY) {
            $body['pay_url'] = $this->orders->payUrl($order);
        } else {
            $body['card'] = $this->cardInfo();
        }

        return response()->json($body, 201);
    }

    public function receipt(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        if ($order->method !== Order::METHOD_CARD
            || ! in_array($order->status, [Order::PENDING, Order::AWAITING_REVIEW, Order::REJECTED], true)) {
            return response()->json(['success' => false, 'message' => 'برای این سفارش نمی‌شود رسید فرستاد.'], 422);
        }
        $data = $request->validate([
            'tracking_code' => 'required|string|max:64',
            'card_last4' => 'nullable|digits:4',
            'paid_at' => 'nullable|string|max:32',
            'receipt' => 'nullable|image|max:5120',
        ]);

        $path = $request->hasFile('receipt')
            ? $request->file('receipt')->store('receipts/'.$order->user_id, 'local')
            : null;
        $order = $this->orders->submitCardReceipt($order, $data, $path);
        $order->load('book:id,title');

        return response()->json([
            'success' => true,
            'message' => config('shop.card.review_hint'),
            'order' => $order->toAppArray(),
        ]);
    }

    /** null اگر کارت‌به‌کارت خاموش است یا شماره‌ی کارت تنظیم نشده. */
    private function cardInfo(): ?array
    {
        $c = config('shop.card');
        if (! $c['enabled'] || ! $c['number']) {
            return null;
        }

        return [
            'number' => preg_replace('/\D/', '', (string) $c['number']),
            'holder' => $c['holder'],
            'bank' => $c['bank'],
            'sheba' => $c['sheba'],
            'hint' => $c['review_hint'],
        ];
    }
}
