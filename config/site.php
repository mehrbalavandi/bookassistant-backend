<?php

// 🌟 SITE-1: تنظیماتِ سطحِ سایت. این‌ها پیش‌فرض‌اند؛ ادمین در پنل («تنظیماتِ سایت») عوضشان
// می‌کند و مقدارِ پنل روی همین کلیدها نوشته می‌شود (App\Support\SiteSettings).
return [
    'name' => env('SITE_NAME', 'آکادمی زبان و آیلتس'),
    'tagline' => env('SITE_TAGLINE', 'کتاب‌های آیلتس با ترجمه، فایل صوتی و تمرینِ تعاملی'),
    'seo_description' => env('SITE_SEO_DESCRIPTION', 'کتاب‌های آموزشیِ آیلتس با ترجمه‌ی فارسی و عربی، فایل‌های صوتیِ هم‌زمان با متن و مرورِ واژگان.'),
    'footer_text' => env('SITE_FOOTER_TEXT', ''),

    'support' => [
        'phone' => env('SUPPORT_PHONE', ''),
        'email' => env('SUPPORT_EMAIL', ''),
        'telegram' => env('SUPPORT_TELEGRAM', ''),   // مثلاً @my_support
        'whatsapp' => env('SUPPORT_WHATSAPP', ''),   // مثلاً 989121234567
        'hours' => env('SUPPORT_HOURS', ''),         // مثلاً «شنبه تا چهارشنبه، ۹ تا ۱۷»
    ],

    // 🌟 SITE-2: روش‌های ورود و ثبت‌نام — هم سایت و هم اپ از همین پیروی می‌کنند.
    'auth' => [
        'mobile_enabled' => (bool) env('AUTH_MOBILE_ENABLED', true),
        'email_enabled' => (bool) env('AUTH_EMAIL_ENABLED', true),
        // ساختنِ حسابِ تازه (با هر روش). خاموش = فقط کاربرانِ موجود وارد می‌شوند.
        'registration_enabled' => (bool) env('AUTH_REGISTRATION_ENABLED', true),
        // strong: ۸+ با حروفِ بزرگ/کوچک، عدد و نماد | medium: ۸+ با حرف و عدد | lax: هر رمزی (فقط تست)
        'password_mode' => env('PASSWORD_MODE', 'strong'),
        'mobile_disabled_message' => 'ورود و ثبت‌نام با شماره‌ی موبایل فعلاً فعال نیست.',
        'email_disabled_message' => 'ورود و ثبت‌نام با ایمیل فعلاً فعال نیست.',
        'registration_disabled_message' => 'ثبت‌نامِ حسابِ تازه فعلاً بسته است.',
        // 🌟 SITE-12: «مرا به خاطر بسپار». روشن: بعد از پایانِ نشست (session.lifetime) کاربر با کوکیِ
        // ماندگار خودکار دوباره وارد می‌شود. خاموش: بعد از پایانِ نشست صفحه‌ی ورود می‌آید.
        'remember' => (bool) env('AUTH_REMEMBER', true),
    ],

    // 🌟 SITE-3: نوارِ اطلاعیه بالای سایت و اپ.
    'announcement' => [
        'enabled' => false,
        'text' => '',
        'level' => 'info', // info | warning | success
    ],

    // 🌟 SITE-4: حالتِ تعمیر. سایت و API برای کاربرانِ عادی پیامِ «در حالِ به‌روزرسانی» می‌دهند؛
    // پنلِ ادمین، ورود و برگشت از درگاهِ پرداخت باز می‌مانند.
    'maintenance' => [
        'enabled' => false,
        'message' => 'سایت در حالِ به‌روزرسانی است و به‌زودی برمی‌گردد.',
    ],

    // 🌟 SITE-5: صفحه‌های ثابت (HTML از ویرایشگرِ پنل).
    'pages' => [
        'about' => '',
        'terms' => '',
        'privacy' => '',
    ],
];
