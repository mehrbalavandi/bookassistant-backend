<?php

/*
|--------------------------------------------------------------------------
| تنظیماتِ Livewire — فقط بخشِ آپلودِ موقت
|--------------------------------------------------------------------------
|
| 🐞 علتِ گیرکردنِ آپلودِ ZIP روی «Uploading 100%»:
| FileUploadِ فیلامنت فایل را اول به endpointِ آپلودِ موقتِ Livewire
| می‌فرستد. پیش‌فرضِ Livewire برای این endpoint قانونِ 'max:12288' است، یعنی
| فقط ۱۲ مگابایت. فایلِ بزرگ‌تر کاملاً ارسال می‌شود (نوارِ پیشرفت ۱۰۰٪ می‌شود)
| ولی سرور با خطای اعتبارسنجی ردش می‌کند و FilePond همان‌جا بی‌صدا می‌ماند.
|
| این فایل فقط کلیدِ temporary_file_upload را بازنویسی می‌کند؛ Livewire بقیه‌ی
| کلیدها را از پیش‌فرضِ خودش می‌گیرد (mergeConfigFrom در سطحِ اول ادغام
| می‌کند، پس زیرآرایه‌ی زیر باید *کامل* باشد — هیچ کلیدی را حذف نکنید).
|
| مقدارها از .env قابلِ تغییرند:
|   ARCHIVE_UPLOAD_MAX_MB=1024        (سقفِ حجمِ فایل به مگابایت)
|   ARCHIVE_UPLOAD_MAX_MINUTES=30     (مهلتِ لینکِ امضاشده‌ی آپلود به دقیقه)
|
| ⚠️ بعد از هر تغییر: php artisan config:clear
| ⚠️ سقفِ واقعی = کمترینِ این عدد و upload_max_filesize و post_max_size در
|    php.ini. پنلِ آپلود سقفِ واقعی و گلوگاهش را نشان می‌دهد
|    (App\Support\UploadLimits).
|
*/

return [

    'temporary_file_upload' => [
        'disk' => null,
        'rules' => [
            'required',
            'file',
            'max:' . ((int) env('ARCHIVE_UPLOAD_MAX_MB', 1024) * 1024), // کیلوبایت
        ],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png',
            'gif',
            'bmp',
            'svg',
            'wav',
            'mp4',
            'mov',
            'avi',
            'wmv',
            'mp3',
            'm4a',
            'jpg',
            'jpeg',
            'mpga',
            'webp',
            'wma',
        ],
        // آپلودِ فایل‌های چندصد مگابایتی روی اینترنتِ کند ممکن است از ۵ دقیقه‌ی
        // پیش‌فرض بیشتر طول بکشد و لینکِ امضاشده منقضی شود.
        'max_upload_time' => (int) env('ARCHIVE_UPLOAD_MAX_MINUTES', 30),
        'cleanup' => true,
    ],

];
