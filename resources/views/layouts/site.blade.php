{{-- 🌟 SITE-5: قالبِ مشترکِ صفحه‌های تازه‌ی سایت (ورود، ثبت‌نام، بازیابیِ رمز، صفحه‌های ثابت). --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | {{ config('site.name') }}</title>
    <meta name="description" content="{{ trim($__env->yieldContent('description')) ?: config('site.seo_description') }}">
    <meta property="og:site_name" content="{{ config('site.name') }}">
    <meta property="og:title" content="@yield('title') | {{ config('site.name') }}">
    <meta property="og:description" content="{{ config('site.seo_description') }}">
    <meta property="og:image" content="{{ asset('images/logo-192.png') }}">
    @hasSection('noindex')<meta name="robots" content="noindex">@endif
    <script src="{{ asset('js/tailwind-browser-4.3.3.js') }}"></script>
    <link href="{{ asset('css/persian-font.css') }}" rel="stylesheet" type="text/css" />
    <style>body { font-family: var(--font-fa); } .prose-fa h2{font-size:1.25rem;font-weight:700;margin:1.2rem 0 .5rem} .prose-fa h3{font-weight:700;margin:1rem 0 .4rem} .prose-fa p{margin:.5rem 0;line-height:2} .prose-fa ul{list-style:disc;padding-right:1.5rem} .prose-fa ol{list-style:decimal;padding-right:1.5rem} .prose-fa a{color:#2563eb}</style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col text-slate-800">
    @include('partials.announcement')

    <header class="bg-white border-b border-slate-100">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-slate-900">
                <img src="{{ asset('images/logo-192.png') }}" alt="" class="w-9 h-9 rounded-lg">
                <span>{{ config('site.name') }}</span>
            </a>
            <nav class="flex items-center gap-2 text-sm">
                <a href="{{ route('app.page') }}" class="px-3 py-2 rounded-lg hover:bg-slate-100">دریافتِ اپ</a>
                @auth
                    <a href="{{ route('home') }}" class="px-3 py-2 rounded-lg hover:bg-slate-100">کتاب‌های من</a>
                    <a href="{{ route('orders.index') }}" class="px-3 py-2 rounded-lg hover:bg-slate-100">سفارش‌ها</a>
                    <a href="{{ route('feedback.index') }}" class="px-3 py-2 rounded-lg hover:bg-slate-100">انتقادات و پیشنهادات</a>
                    @if(filled(auth()->user()->email))
                        <a href="{{ route('account.password') }}" class="px-3 py-2 rounded-lg hover:bg-slate-100">تغییرِ رمز</a>
                    @endif
                    <form action="{{ url('/logout') }}" method="POST">@csrf<button class="px-3 py-2 rounded-lg hover:bg-slate-100">خروج</button></form>
                @else
                    <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-bold hover:bg-blue-700">ورود / ثبت‌نام</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1 w-full max-w-5xl mx-auto px-4 py-8">
        {{-- 🌟 SITE-15: پیامِ کوتاهِ بعد از یک کار (مثلاً «رمزِ عبور عوض شد») در هر صفحه‌ی سایت --}}
        @if(session('flash'))
            <div class="bg-emerald-50 text-emerald-700 p-3 rounded-lg text-sm mb-6 max-w-2xl mx-auto text-center">{{ session('flash') }}</div>
        @endif
        @yield('content')
    </main>

    @include('partials.footer')
</body>
</html>
