<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>آکادمی زبان و آیلتس</title>
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #333; }
        .header { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn { padding: 8px 16px; border-radius: 5px; text-decoration: none; font-size: 14px; font-weight: bold; }
        .btn-primary { background-color: #4f46e5; color: white; }
        .btn-success { background-color: #10b981; color: white; }
        .btn-secondary { background-color: #6b7280; color: white; }
        .container { max-width: 1200px; margin: 40px auto; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; border: 1px solid #e5e7eb; transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); }
        .card h3 { margin-top: 0; color: #111827; font-size: 18px; }
        .price { font-size: 16px; color: #059669; font-weight: bold; margin: 15px 0; }
        .free { color: #2563eb; }
    </style>
</head>
<body>

    <div class="header">
        <h2>📚 آکادمی آموزش زبان و آیلتس</h2>
        <div>
            <a href="{{ url('/register') }}" class="btn btn-secondary" style="margin-left: 10px;">ثبت نام</a>
            <a href="{{ url('/login') }}" class="btn btn-primary">ورود به حساب</a>
        </div>
    </div>

    <div class="container">
        <h2 style="margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">کتاب‌های آموزشی دسترس</h2>
        
        <div class="grid">
            @foreach($books as $book)
                <div class="card">
                    <h3>{{ $book->title }}</h3>
                    
                    <div class="price">
                        @if($book->price == 0)
                            <span style="color: blue;">رایگان</span>
                        @elseif($book->discount > 0)
                            <span style="text-decoration: line-through; color: #9ca3af; font-size: 14px;">
                                {{ number_format($book->price) }}
                            </span>
                            <span style="background-color: #ef4444; color: white; padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-right: 5px;">
                                {{ $book->discount }}%
                            </span>
                            <br>
                            {{ number_format($book->final_price) }} تومان
                        @else
                            {{ number_format($book->price) }} تومان
                        @endif
                    </div>

                    <a href="{{ url('/login') }}" class="btn btn-primary" style="display: block; margin-top: 15px;">
                        ورود و خرید کتاب
                    </a>
                </div>
            @endforeach
        </div>
    </div>

</body>
</html>