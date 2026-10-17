<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد کاربری</title>
    <link href="{{ asset('css/persian-font.css') }}" rel="stylesheet" type="text/css" />
    <style>
        body { font-family: var(--font-fa); background-color: #f4f6f9; margin: 0; padding: 20px; color: #333; }
        .header { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn { padding: 8px 16px; border-radius: 5px; text-decoration: none; font-size: 14px; font-weight: bold; display: inline-block; }
        .btn-primary { background-color: #4f46e5; color: white; border: none; cursor: pointer; width: 100%; box-sizing: border-box; text-align: center;}
        .btn-success { background-color: #10b981; color: white; width: 100%; text-align: center; box-sizing: border-box;}
        .btn-danger { background-color: #ef4444; color: white; border: none; cursor: pointer; font-family: inherit; }
        .container { max-width: 1200px; margin: 40px auto; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; border: 1px solid #e5e7eb; }
        .price { font-size: 16px; color: #059669; font-weight: bold; margin: 15px 0; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
        .alert-success { background-color: #d1fae5; color: #065f46; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; }

        /* 📱 موبایل: سربرگ دوطبقه (عنوان، بعد دکمه‌ها تمام‌عرض)، فاصله‌های کمتر، کارت‌ها یک‌ستونه */
        html { background-color: #f4f6f9; }
        .header { flex-wrap: wrap; gap: 12px; }
        .header h2 { margin: 0; font-size: clamp(17px, 4.6vw, 24px); line-height: 1.6; }
        .header .btn { white-space: nowrap; }
        .header > div { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .header > div > a { margin: 0 !important; }
        @media (max-width: 600px) {
            body { padding: 12px; }
            .header { padding: 14px 16px; }
            .header > div { display: flex; gap: 8px; width: 100%; }
            .header > div > a, .header > div > form { flex: 1; margin: 0 !important; }
            .header > div .btn { display: block; width: 100%; box-sizing: border-box; text-align: center; padding: 10px 12px; }
            .container { margin: 20px auto; }
            .container > h2 { font-size: 18px; }
            .grid { grid-template-columns: 1fr; gap: 14px; }
            .card { padding: 16px; }
            .card:hover { transform: none; }
        }
    </style>
</head>
<body>
    @include('partials.announcement')

    <div class="header">
        <h2>👋 خوش آمدید، {{ Auth::user()->name }} عزیز</h2>
        <div>
            <a href="{{ route('app.page') }}" class="btn btn-success" style="width: auto;">📱 دریافتِ اپ</a>
            <form action="{{ url('/logout') }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-danger">خروج از حساب</button>
            </form>
        </div>
    </div>

    <div class="container">
        
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <h2 style="margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">فروشگاه کتاب‌های شما</h2>
        <p style="color: #6b7280; margin-bottom: 30px;">📌 کتاب‌های خریداری شده به صورت خودکار در اپلیکیشن موبایل شما نیز آزاد و قابل دانلود خواهند بود.</p>

        {{-- 🌟 فهرستِ دسته‌بندی‌شده (دسته‌ها ← کتاب‌های هر دسته) --}}

        @include('partials.catalog', ['canBuy' => true])
    </div>

    @include('partials.footer')
</body>
</html>