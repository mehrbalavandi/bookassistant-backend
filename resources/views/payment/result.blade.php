<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $ok ? 'پرداخت موفق' : 'پرداخت ناموفق' }} | آکادمی زبان و آیلتس</title>
    <link href="{{ asset('css/persian-font.css') }}" rel="stylesheet" type="text/css" />
    <style>
        html, body { background-color: #f4f6f9; }
        body { font-family: var(--font-fa); margin: 0; padding: 20px; color: #333; }
        .card { max-width: 480px; margin: 60px auto; background: #fff; border-radius: 16px; padding: 28px 24px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; }
        .icon { font-size: 52px; line-height: 1; }
        h2 { margin: 12px 0 6px; font-size: 22px; }
        .ok h2 { color: #047857; }
        .fail h2 { color: #b91c1c; }
        .msg { color: #4b5563; line-height: 1.9; margin: 8px 0 18px; }
        .meta { background: #f9fafb; border-radius: 10px; padding: 12px 14px; font-size: 14px; line-height: 2; text-align: right; }
        .hint { margin-top: 18px; font-size: 14px; color: #6b7280; line-height: 1.9; }
        .btn { display: inline-block; margin-top: 14px; background: #4f46e5; color: #fff; text-decoration: none; font-weight: bold; padding: 12px 22px; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="card {{ $ok ? 'ok' : 'fail' }}">
        <div class="icon">{{ $ok ? '✅' : '⚠️' }}</div>
        <h2>{{ $ok ? 'پرداخت موفق بود' : 'پرداخت انجام نشد' }}</h2>
        <p class="msg">{{ $message }}</p>
        <div class="meta">
            <div>کتاب: <strong>{{ $order->book?->title }}</strong></div>
            <div>مبلغ: <strong>{{ number_format($order->amount) }} تومان</strong></div>
            <div>شماره‌ی سفارش: <strong>{{ $order->id }}</strong></div>
            @if($order->reference_id)
                <div>کدِ پیگیری: <strong dir="ltr">{{ $order->reference_id }}</strong></div>
            @endif
        </div>
        <p class="hint">
            @if($order->source === 'app')
                حالا به برنامه برگردید؛ فهرستِ کتاب‌ها خودش به‌روز می‌شود.
            @else
                <a class="btn" href="{{ url('/') }}">بازگشت به کتاب‌ها</a>
            @endif
        </p>
    </div>
</body>
</html>
