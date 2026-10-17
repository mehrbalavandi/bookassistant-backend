{{-- 🌟 SITE-5: پایینِ صفحه — پیوندِ صفحه‌های ثابت، پشتیبانی و متنِ دلخواه. استایلِ درون‌خطی تا در همه‌ی صفحه‌ها کار کند. --}}
@php($support = array_filter((array) config('site.support', []), fn ($v) => (string) $v !== ''))
<footer style="background:#fff;border-top:1px solid #e5e7eb;margin-top:40px;padding:24px 16px;font-size:13px;color:#4b5563;line-height:2">
    <div style="max-width:1000px;margin:0 auto;display:flex;flex-wrap:wrap;gap:16px 32px;justify-content:space-between">
        <div>
            <div style="font-weight:700;color:#111827">{{ config('site.name') }}</div>
            @if(config('site.tagline'))<div>{{ config('site.tagline') }}</div>@endif
            @if(config('site.footer_text'))<div style="white-space:pre-line">{{ config('site.footer_text') }}</div>@endif
        </div>
        <nav style="display:flex;flex-wrap:wrap;gap:4px 16px;align-items:flex-start">
            <a href="{{ route('page', 'about') }}" style="color:#2563eb;text-decoration:none">درباره‌ی ما</a>
            <a href="{{ route('contact') }}" style="color:#2563eb;text-decoration:none">تماس با ما</a>
            <a href="{{ route('page', 'terms') }}" style="color:#2563eb;text-decoration:none">قوانین و مقررات</a>
            <a href="{{ route('page', 'privacy') }}" style="color:#2563eb;text-decoration:none">حریمِ خصوصی</a>
            <a href="{{ route('app.page') }}" style="color:#2563eb;text-decoration:none">دریافتِ اپ</a>
        </nav>
        @if($support)
            <div>
                @isset($support['phone'])<div>تلفن: <a href="tel:{{ $support['phone'] }}" dir="ltr" style="color:inherit">{{ $support['phone'] }}</a></div>@endisset
                @isset($support['email'])<div>ایمیل: <a href="mailto:{{ $support['email'] }}" dir="ltr" style="color:inherit">{{ $support['email'] }}</a></div>@endisset
                @isset($support['hours'])<div>{{ $support['hours'] }}</div>@endisset
            </div>
        @endif
    </div>
    <div style="text-align:center;margin-top:12px;color:#9ca3af">© {{ now()->year }} {{ config('site.name') }}</div>
</footer>
