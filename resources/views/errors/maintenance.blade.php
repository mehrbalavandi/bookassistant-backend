{{-- 🌟 SITE-4: حالتِ تعمیر (پنل ← تنظیماتِ سایت ← اطلاعیه و تعمیر) --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>در حالِ به‌روزرسانی | {{ config('site.name') }}</title>
    <link href="{{ asset('css/persian-font.css') }}" rel="stylesheet" type="text/css" />
    <style>body{font-family:var(--font-fa);background:#f8fafc;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;color:#334155} .box{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;max-width:440px;text-align:center;line-height:2}</style>
</head>
<body>
    <div class="box">
        <img src="{{ asset('images/logo-192.png') }}" alt="" width="64" height="64" style="border-radius:12px">
        <h1 style="font-size:20px;margin:12px 0">{{ config('site.name') }}</h1>
        <p>{{ $message }}</p>
    </div>
</body>
</html>
