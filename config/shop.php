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
    ],

    // اعتبارِ لینکِ شروعِ پرداخت که به اپ داده می‌شود (دقیقه).
    'pay_link_minutes' => (int) env('PAY_LINK_MINUTES', 30),
];
