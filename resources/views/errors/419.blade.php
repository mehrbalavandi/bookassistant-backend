{{-- 🌟 SITE-9: مهلتِ صفحه تمام شد (توکنِ CSRF کهنه یا نشستِ تمام‌شده) — به‌جای «419 | PAGE EXPIRED» --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>مهلتِ صفحه تمام شد | {{ config('site.name') }}</title>
    <link href="{{ asset('css/persian-font.css') }}" rel="stylesheet" type="text/css" />
    <style>body{font-family:var(--font-fa);background:#f8fafc;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;color:#334155} .box{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;max-width:440px;text-align:center;line-height:2} a{display:inline-block;margin-top:12px;background:#4f46e5;color:#fff;text-decoration:none;padding:8px 20px;border-radius:10px}</style>
</head>
<body>
    <div class="box">
        <img src="{{ asset('images/logo-192.png') }}" alt="" width="64" height="64" style="border-radius:12px">
        <h1 style="font-size:20px;margin:12px 0">مهلتِ این صفحه تمام شد</h1>
        <p>صفحه مدتِ زیادی باز مانده بود یا از حساب خارج شده‌اید. صفحه را دوباره باز کنید و فرم را از نو بفرستید.</p>
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}">بازگشت</a>
    </div>
</body>
</html>
