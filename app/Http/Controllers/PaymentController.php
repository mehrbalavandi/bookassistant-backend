<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

/**
 * 🌟 PAY-2: صفحه‌های وبِ پرداختِ درگاه.
 *   GET /pay/{order}?signature=…          ← لینکِ امضاشده (از اپ یا checkoutِ سایت) → درگاه
 *   GET|POST /payment/callback/{order}    ← برگشت از درگاه → تأیید → صفحه‌ی نتیجه
 * هیچ‌کدام نشستِ وب نمی‌خواهند؛ امضای لینک و شناسه‌ی تراکنشِ ذخیره‌شده کافی است.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function start(Order $order)
    {
        if ($order->status === Order::PAID) {
            return $this->result($order, true, 'این سفارش قبلاً پرداخت شده است.');
        }
        if ($order->status !== Order::PENDING || $order->method !== Order::METHOD_GATEWAY) {
            return $this->result($order, false, 'این لینکِ پرداخت دیگر معتبر نیست؛ از برنامه دوباره «خرید» را بزنید.');
        }

        try {
            return $this->orders->startGateway($order);
        } catch (\Throwable $e) {
            report($e);

            return $this->result($order, false, 'اتصال به درگاه ممکن نشد. چند دقیقه‌ی دیگر دوباره امتحان کنید.');
        }
    }

    public function callback(Request $request, Order $order)
    {
        // اگر شناسه‌ی تراکنشِ برگشتی (Authority در زرین‌پال) با سفارش جور نیست، کسی
        // آدرس را دستکاری کرده.
        $authority = $request->input('Authority') ?? $request->input('trackId') ?? $request->input('transId');
        if ($authority && $order->transaction_id && (string) $authority !== (string) $order->transaction_id) {
            return $this->result($order, false, 'اطلاعاتِ تراکنش با سفارش جور نیست.');
        }

        $r = $this->orders->verifyGateway($order);

        return $this->result($order, $r['ok'], $r['message']);
    }

    private function result(Order $order, bool $ok, string $message)
    {
        return response()->view('payment.result', [
            'order' => $order->loadMissing('book:id,title'),
            'ok' => $ok,
            'message' => $message,
        ]);
    }
}
