<?php

// 🌟 PAY-1..PAY-4: فروش. درگاه از طریقِ shetabit/payment (config/payment.php)؛ این‌جا
// فقط انتخابِ درگاه و مشخصاتِ کارت‌به‌کارت.
return [
    // نامِ درایور در config/payment.php: zarinpal، zibal، idpay، payir، sep، behpardakht، …
    'gateway' => env('PAYMENT_GATEWAY', 'zarinpal'),
    'gateway_enabled' => (bool) env('PAYMENT_GATEWAY_ENABLED', true),

    // کارت‌به‌کارت با بررسیِ دستیِ رسید در پنل (PAY-3).
    'card' => [
        'enabled' => (bool) env('CARD_TRANSFER_ENABLED', false),
        'number' => env('CARD_TRANSFER_NUMBER'),       // ۱۶ رقم
        'holder' => env('CARD_TRANSFER_HOLDER'),       // نامِ صاحبِ کارت
        'bank' => env('CARD_TRANSFER_BANK'),
        'sheba' => env('CARD_TRANSFER_SHEBA'),          // اختیاری
        // پیامی که بعد از ثبتِ رسید به کاربر نشان داده می‌شود.
        'review_hint' => env('CARD_TRANSFER_HINT', 'رسیدِ شما معمولاً تا چند ساعتِ کاری بررسی و کتاب فعال می‌شود.'),
        // 🌟 PAY-5: «تومانِ شناسه»: به مبلغِ هر سفارشِ کارت‌به‌کارت عددی تصادفی از ۱ تا این مقدار
        // (تومان) اضافه می‌شود؛ میانِ سفارش‌های کارت‌به‌کارتِ باز تکراری نیست. 0 = خاموش.
        'unique_max' => (int) env('CARD_UNIQUE_MAX', 999),
    ],

    // 🌟 PAY-7: سفارشِ «در انتظارِ پرداخت» بعد از این چند ساعت خودکار لغو می‌شود (0 = هرگز).
    'pending_expire_hours' => (int) env('ORDER_PENDING_EXPIRE_HOURS', 48),

    // 🌟 PAY-8: رسیدِ کارت‌به‌کارتِ تازه به این ایمیل هم خبر داده می‌شود (خالی = فقط اعلانِ پنل).
    'notify_email' => env('ORDER_NOTIFY_EMAIL', ''),

    // اعتبارِ لینکِ شروعِ پرداخت که به اپ داده می‌شود (دقیقه).
    'pay_link_minutes' => (int) env('PAY_LINK_MINUTES', 30),
];
