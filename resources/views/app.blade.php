<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دریافتِ اپلیکیشن | آکادمی زبان و آیلتس</title>
    <link href="{{ asset('css/persian-font.css') }}" rel="stylesheet" type="text/css" />
    <style>
        html, body { background-color: #f4f6f9; }
        body { font-family: var(--font-fa); margin: 0; padding: 20px; color: #333; }
        .header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: #fff; padding: 15px 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .header h2 { margin: 0; font-size: clamp(17px, 4.6vw, 24px); }
        .header a { color: #4f46e5; text-decoration: none; font-weight: bold; }
        .container { max-width: 760px; margin: 40px auto; }
        .release { background: #fff; border-radius: 14px; padding: 24px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .release.primary { border: 2px solid #4f46e5; }
        .release h3 { margin: 0 0 6px; font-size: 20px; }
        .meta { color: #6b7280; font-size: 14px; margin-bottom: 16px; }
        .badge { display: inline-block; background: #fee2e2; color: #991b1b; border-radius: 20px; padding: 2px 10px; font-size: 12px; font-weight: bold; margin-right: 6px; }
        .btn { display: block; text-align: center; background: #4f46e5; color: #fff; text-decoration: none; font-weight: bold; padding: 14px; border-radius: 10px; font-size: 16px; }
        .changelog { background: #f9fafb; border-radius: 10px; padding: 14px 18px; margin-top: 16px; line-height: 1.9; font-size: 14px; white-space: pre-line; }
        .changelog strong { display: block; margin-bottom: 4px; }
        .guide { background: #eef2ff; border-radius: 12px; padding: 16px 20px; line-height: 2; font-size: 14px; }
        .guide h4 { margin: 0 0 6px; }
        .guide ol { margin: 0; padding-right: 20px; }
        .empty { text-align: center; color: #6b7280; padding: 40px 0; }
        @media (max-width: 600px) {
            body { padding: 12px; }
            .header { padding: 14px 16px; }
            .container { margin: 20px auto; }
            .release { padding: 18px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>📱 دریافتِ اپلیکیشن</h2>
        <a href="{{ url('/') }}">→ بازگشت به کتاب‌ها</a>
    </div>

    <div class="container">
        @forelse($releases as $platform => $release)
            <div class="release" data-platform="{{ $platform }}">
                <h3>{{ \App\Models\AppRelease::PLATFORMS[$platform] }}</h3>
                <div class="meta">
                    نسخه‌ی {{ $release->version_name }}
                    &nbsp;·&nbsp; {{ $release->humanSize() }}
                    &nbsp;·&nbsp; <span dir="ltr">{{ $release->updated_at?->format('Y-m-d') }}</span>
                    @if($release->is_mandatory)
                        <span class="badge">به‌روزرسانیِ ضروری</span>
                    @endif
                </div>

                <a class="btn" href="{{ route('app.download', $platform) }}">⬇ دریافتِ نسخه‌ی {{ $release->version_name }}</a>

                @if($release->changelog)
                    <div class="changelog"><strong>تغییراتِ این نسخه</strong>{{ $release->changelog }}</div>
                @endif
            </div>
        @empty
            <div class="release empty">هنوز نسخه‌ای برای دریافت منتشر نشده است.</div>
        @endforelse

        @if($releases->has('android'))
            <div class="guide">
                <h4>راهنمای نصب در اندروید</h4>
                <ol>
                    <li>دکمه‌ی «دریافت» را بزنید و صبر کنید دانلود تمام شود.</li>
                    <li>فایلِ دانلودشده را از اعلانِ دانلود یا پوشه‌ی Download باز کنید.</li>
                    <li>اگر اندروید اجازه‌ی نصب نداد: «تنظیمات» ← «نصب از منابعِ ناشناخته» را برای مرورگر یا مدیرِ فایل روشن کنید.</li>
                    <li>برای به‌روزرسانی، نسخه‌ی تازه را روی همان برنامه نصب کنید؛ کتاب‌ها و پیشرفتِ شما حفظ می‌شود.</li>
                </ol>
            </div>
        @endif
    </div>

    <script>
        // سکوی همین دستگاه اول و پررنگ نشان داده شود.
        (function () {
            var ua = navigator.userAgent || '';
            var mine = /Android/i.test(ua) ? 'android' : (/Windows/i.test(ua) ? 'windows' : null);
            if (!mine) return;
            var el = document.querySelector('.release[data-platform="' + mine + '"]');
            if (!el) return;
            el.classList.add('primary');
            el.parentNode.insertBefore(el, el.parentNode.firstChild);
        })();
    </script>
</body>
</html>
