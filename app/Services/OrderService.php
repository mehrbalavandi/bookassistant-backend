<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;

/**
 * 🌟 PAY-1..PAY-4: چرخه‌ی سفارش — ساختن، شروعِ درگاه، تأییدِ برگشت از درگاه،
 * رسیدِ کارت‌به‌کارت، تأیید/ردِ ادمین. اهدای کتاب فقط از grant() می‌گذرد.
 */
class OrderService
{
    public function __construct(private readonly SmsSender $sms) {}

    /**
     * سفارشِ تازه. کتابِ رایگان (یا تخفیفِ ۱۰۰٪) همین‌جا اهدا می‌شود. سفارش‌های بازِ
     * قبلیِ همین کاربر/کتاب/روش لغو می‌شوند تا فقط یکی باز بماند.
     */
    public function create(User $user, Book $book, string $method, string $source, ?string $ip): Order
    {
        $amount = (int) round($book->final_price);

        Order::where('user_id', $user->id)->where('book_id', $book->id)
            ->where('method', $method)->where('status', Order::PENDING)
            ->update(['status' => Order::CANCELED]);

        // 🌟 PAY-5: کارت‌به‌کارت ← مبلغ + «تومانِ شناسه»ی یکتا میانِ سفارش‌های باز.
        $suffix = $method === Order::METHOD_CARD && $amount > 0 ? $this->uniqueSuffix($amount) : null;

        $order = Order::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'amount' => $amount + ($suffix ?? 0),
            'amount_suffix' => $suffix,
            'method' => $method,
            'gateway' => $method === Order::METHOD_GATEWAY ? config('shop.gateway') : null,
            'status' => Order::PENDING,
            'ip' => $ip,
            'source' => $source,
        ]);

        if ($amount <= 0) {
            $this->grant($order, null);
        }

        return $order->fresh();
    }

    /**
     * 🌟 PAY-5: عددی تصادفی از ۱ تا shop.card.unique_max که مبلغِ نهایی‌اش (قیمت + عدد) با هیچ
     * سفارشِ کارت‌به‌کارتِ بازِ دیگری (در انتظارِ پرداخت یا بررسی) یکی نباشد. این‌طور هر واریز در
     * صورت‌حسابِ بانک فقط به یک سفارش می‌خورد. null = خاموش.
     */
    public function uniqueSuffix(int $base): ?int
    {
        $max = (int) config('shop.card.unique_max', 999);
        if ($max <= 0) {
            return null;
        }
        $taken = Order::where('method', Order::METHOD_CARD)
            ->whereIn('status', [Order::PENDING, Order::AWAITING_REVIEW])
            ->whereBetween('amount', [$base + 1, $base + $max])
            ->pluck('amount')
            ->map(fn ($a) => (int) $a - $base)
            ->flip();

        if ($taken->count() < $max) {
            for ($i = 0; $i < 30; $i++) {
                $s = random_int(1, $max);
                if (! $taken->has($s)) {
                    return $s;
                }
            }
            // شانس نیاورد: اولین عددِ آزاد.
            for ($s = 1; $s <= $max; $s++) {
                if (! $taken->has($s)) {
                    return $s;
                }
            }
        }

        // همه‌ی اعداد گرفته‌اند (بیش از unique_max سفارشِ بازِ هم‌قیمت): تصادفی، تکراری.
        return random_int(1, $max);
    }

    /** لینکِ امضاشده‌ی شروعِ پرداخت (برای اپ: در مرورگر باز می‌شود، بدونِ نیاز به ورود). */
    public function payUrl(Order $order): string
    {
        return URL::temporarySignedRoute(
            'payment.start',
            now()->addMinutes(config('shop.pay_link_minutes')),
            ['order' => $order->id],
        );
    }

    /** فرمِ انتقال به درگاه (HTML). */
    public function startGateway(Order $order)
    {
        $invoice = (new Invoice)->amount($order->amount);
        $invoice->detail([
            'description' => 'خرید '.$order->book->title.' — سفارش '.$order->id,
            'mobile' => $order->user->mobile,
        ]);

        return Payment::via($order->gateway ?: config('shop.gateway'))
            ->callbackUrl(route('payment.callback', ['order' => $order->id]))
            ->purchase($invoice, function ($driver, $transactionId) use ($order) {
                $order->forceFill(['transaction_id' => (string) $transactionId])->save();
            })
            ->pay()
            ->render();
    }

    /**
     * برگشت از درگاه. true اگر پرداخت تأیید شد (یا قبلاً تأیید شده بود).
     *
     * @return array{ok: bool, message: string}
     */
    public function verifyGateway(Order $order): array
    {
        if ($order->status === Order::PAID) {
            return ['ok' => true, 'message' => 'این سفارش قبلاً پرداخت شده است.'];
        }
        if ($order->status !== Order::PENDING || ! $order->transaction_id) {
            return ['ok' => false, 'message' => 'این سفارش قابلِ پرداخت نیست.'];
        }

        try {
            $receipt = Payment::via($order->gateway ?: config('shop.gateway'))
                ->amount($order->amount)
                ->transactionId($order->transaction_id)
                ->verify();
        } catch (InvalidPaymentException $e) {
            $order->forceFill(['status' => Order::FAILED, 'admin_note' => mb_substr($e->getMessage(), 0, 500)])->save();

            return ['ok' => false, 'message' => 'پرداخت ناموفق بود: '.$e->getMessage()];
        }

        $order->forceFill(['reference_id' => (string) $receipt->getReferenceId()])->save();
        $this->grant($order, null);

        return ['ok' => true, 'message' => 'پرداخت موفق بود. کدِ پیگیری: '.$receipt->getReferenceId()];
    }

    /** رسیدِ کارت‌به‌کارت (PAY-3). */
    public function submitCardReceipt(Order $order, array $data, ?string $receiptPath): Order
    {
        $order->forceFill([
            'status' => Order::AWAITING_REVIEW,
            'card_tracking_code' => $data['tracking_code'],
            'card_last4' => $data['card_last4'] ?? null,
            'card_paid_at' => $data['paid_at'] ?? null,
            'receipt_path' => $receiptPath ?? $order->receipt_path,
        ])->save();

        return $order;
    }

    /** اهدای کتاب + پرداخت‌شده. ایدمپوتنت (دوبار صدا زدن ضرری ندارد). */
    public function grant(Order $order, ?User $reviewer): void
    {
        DB::transaction(function () use ($order, $reviewer) {
            $order->forceFill([
                'status' => Order::PAID,
                'paid_at' => $order->paid_at ?? now(),
                'reviewed_by' => $reviewer?->id ?? $order->reviewed_by,
                'reviewed_at' => $reviewer ? now() : $order->reviewed_at,
            ])->save();
            $order->user->purchasedBooks()->syncWithoutDetaching([$order->book_id]);
        });
    }

    /** تأییدِ ادمین برای کارت‌به‌کارت؛ پیامکِ اطلاع (اگر قالبش تنظیم شده). */
    public function approve(Order $order, User $admin): void
    {
        $this->grant($order, $admin);
        $this->notify($order, 'order_paid', [$order->book->title]);
    }

    public function reject(Order $order, User $admin, string $reason): void
    {
        $order->forceFill([
            'status' => Order::REJECTED,
            'admin_note' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();
        $this->notify($order, 'order_rejected', [(string) $order->id]);
    }

    // ───────── 🌟 ADM-4: ویرایش و حذفِ سفارش توسطِ ادمین ─────────

    /**
     * ویرایشِ سفارش در پنل. تغییرِ وضعیت به «پرداخت‌شده» کتاب را فعال می‌کند (grant)؛ برگرداندن
     * از «پرداخت‌شده» فقط وقتی کتاب را پس می‌گیرد که $revoke=true باشد و سفارشِ پرداخت‌شده‌ی
     * دیگری برای همان کتاب نمانده باشد.
     */
    public function adminUpdate(Order $order, array $data, User $admin, bool $revoke = false, bool $notify = false): void
    {
        $wasPaid = $order->status === Order::PAID;
        $order->fill(collect($data)->only([
            'amount', 'status', 'method', 'card_tracking_code', 'card_last4', 'reference_id', 'admin_note',
        ])->all());
        $order->forceFill(['reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();

        if (! $wasPaid && $order->status === Order::PAID) {
            $this->grant($order, $admin);
            if ($notify) {
                $this->notify($order, 'order_paid', [$order->book->title]);
            }
        } elseif ($wasPaid && $order->status !== Order::PAID && $revoke) {
            $this->revokeBook($order);
        }
    }

    /** حذفِ سفارش (و فایلِ رسیدش). با $revoke کتاب هم پس گرفته می‌شود (همان شرطِ adminUpdate). */
    public function adminDelete(Order $order, bool $revoke = false): void
    {
        DB::transaction(function () use ($order, $revoke) {
            if ($revoke && $order->status === Order::PAID) {
                $this->revokeBook($order);
            }
            if ($order->receipt_path) {
                Storage::disk('local')->delete($order->receipt_path);
            }
            $order->delete();
        });
    }

    /**
     * کتابِ این سفارش را از کاربر می‌گیرد، مگر سفارشِ پرداخت‌شده‌ی دیگری برای همان کتاب داشته
     * باشد. false یعنی کتاب ماند.
     */
    public function revokeBook(Order $order): bool
    {
        $otherPaid = Order::where('user_id', $order->user_id)->where('book_id', $order->book_id)
            ->where('status', Order::PAID)->whereKeyNot($order->id)->exists();
        if ($otherPaid || ! $order->user) {
            return false;
        }
        $order->user->purchasedBooks()->detach($order->book_id);

        return true;
    }

    private function notify(Order $order, string $template, array $params): void
    {
        $mobile = $order->user->mobile;
        if (! $mobile) {
            return;
        }
        try {
            $this->sms->sendTemplate($mobile, $template, $params);
        } catch (\Throwable $e) {
            Log::warning("Order {$order->id} SMS failed: ".$e->getMessage());
        }
    }
}
