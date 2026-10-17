<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک سفارشِ خرید (PAY-1). */
class Order extends Model
{
    public const METHOD_GATEWAY = 'gateway';
    public const METHOD_CARD = 'card';

    public const PENDING = 'pending';
    public const AWAITING_REVIEW = 'awaiting_review';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const REJECTED = 'rejected';
    public const CANCELED = 'canceled';

    /** برچسبِ فارسیِ وضعیت‌ها (پنل و اپ). */
    public const STATUS_LABELS = [
        self::PENDING => 'در انتظارِ پرداخت',
        self::AWAITING_REVIEW => 'در انتظارِ بررسیِ رسید',
        self::PAID => 'پرداخت‌شده',
        self::FAILED => 'ناموفق',
        self::REJECTED => 'ردشده',
        self::CANCELED => 'لغوشده',
    ];

    protected $fillable = [
        'user_id', 'book_id', 'amount', 'amount_suffix', 'method', 'gateway', 'status',
        'transaction_id', 'reference_id', 'card_tracking_code', 'card_last4',
        'card_paid_at', 'receipt_path', 'admin_note', 'reviewed_by', 'reviewed_at',
        'paid_at', 'ip', 'source',
    ];

    protected $casts = [
        'amount' => 'integer',
        'amount_suffix' => 'integer',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * 🌟 PAY-3/7: برای این سفارش می‌شود رسیدِ کارت‌به‌کارت فرستاد؟ لغوشده هم (مثلاً لغوِ خودکارِ
     * مهلت، وقتی کاربر پول را واریز کرده ولی رسید را دیر فرستاده) — ادمین بررسی می‌کند.
     */
    public function canSubmitReceipt(): bool
    {
        if ($this->method !== self::METHOD_CARD) {
            return false;
        }

        return in_array($this->status, [self::PENDING, self::AWAITING_REVIEW, self::REJECTED], true)
            || ($this->status === self::CANCELED && $this->isAutoCanceled());
    }

    /** 🌟 PAY-7: یادداشتِ سفارشی که مهلتش تمام شده (OrderService::expireStale). */
    public const AUTO_CANCEL_NOTE = 'لغوِ خودکار: پرداخت در مهلت انجام نشد.';

    public function isAutoCanceled(): bool
    {
        return $this->status === self::CANCELED && $this->admin_note === self::AUTO_CANCEL_NOTE;
    }

    public function toAppArray(): array
    {
        return [
            'id' => $this->id,
            'book_id' => $this->book_id,
            'book_title' => $this->book?->title,
            'amount' => $this->amount,
            // 🌟 PAY-5: چند تومانِ آخرِ مبلغ که واریز را به این سفارش وصل می‌کند (فقط کارت‌به‌کارت).
            'amount_suffix' => $this->amount_suffix,
            'method' => $this->method,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'reference_id' => $this->reference_id,
            'card_tracking_code' => $this->card_tracking_code,
            'admin_note' => $this->admin_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
