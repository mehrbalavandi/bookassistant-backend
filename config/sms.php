<?php

// 🌟 AUTH-3: سامانه‌ی پیامک. driver=log فقط کد را در لاگ می‌نویسد (توسعه/تست)؛ روی
// سرورِ واقعی kavenegar یا smsir. هر دو با «قالبِ تأیید» (lookup / verify) کار می‌کنند
// که برای کدِ ورود سریع‌تر و ارزان‌تر از پیامکِ عادی است و نیازی به خطِ اختصاصی ندارد.
return [
    'driver' => env('SMS_DRIVER', 'log'),

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY'),
        // نامِ قالب‌ها در پنلِ کاوه‌نگار. قالبِ ورود باید %token% داشته باشد.
        'templates' => [
            'otp' => env('KAVENEGAR_OTP_TEMPLATE', 'login-otp'),
            'order_paid' => env('KAVENEGAR_ORDER_PAID_TEMPLATE'),
            'order_rejected' => env('KAVENEGAR_ORDER_REJECTED_TEMPLATE'),
        ],
    ],

    'smsir' => [
        'api_key' => env('SMSIR_API_KEY'),
        // شناسه‌ی عددیِ قالب در پنلِ sms.ir؛ پارامترِ قالبِ ورود: CODE
        'templates' => [
            'otp' => env('SMSIR_OTP_TEMPLATE_ID'),
            'order_paid' => env('SMSIR_ORDER_PAID_TEMPLATE_ID'),
            'order_rejected' => env('SMSIR_ORDER_REJECTED_TEMPLATE_ID'),
        ],
    ],

    'otp' => [
        'length' => (int) env('OTP_LENGTH', 5),
        'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 180),
        'resend_seconds' => (int) env('OTP_RESEND_SECONDS', 120),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        // فقط با driver=log و APP_DEBUG=true: کد در پاسخِ API هم برگردانده می‌شود تا
        // بدونِ پیامک بشود اپ را تست کرد. روی سرورِ واقعی هرگز.
        'expose_in_debug' => (bool) env('OTP_EXPOSE_IN_DEBUG', true),
    ],
];
