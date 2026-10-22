{{-- 🌟 SITE-2: ورود با موبایل یا ایمیل. روشی که در پنل خاموش است دیده می‌شود ولی به‌جای فرم پیامِ «فعلاً فعال نیست» دارد. --}}
@extends('layouts.site')
@section('title', 'ورود یا ثبت‌نام')
@section('noindex', '1')

@section('content')
@php
    $tab = ! $methods['mobile'] && $methods['email'] ? 'email' : $tab;
    // 🌟 SITE-14: ورودِ ادمین (‎?for=admin‎) وقتی ورودِ ایمیلی برای کاربران خاموش است: فقط فرمِ ایمیل.
    $adminOnly = $adminOnly ?? false;
    if ($adminOnly) { $tab = 'email'; $methods['email'] = true; }
    $input = 'w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none';
@endphp
<div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-md mx-auto">
    <h1 class="text-2xl font-bold text-center text-slate-900 mb-6">{{ $adminOnly ? 'ورودِ مدیران' : 'ورود یا ثبت‌نام' }}</h1>

    @if(! empty($sessionEnded) && ! $errors->any())
        {{-- 🌟 SITE-10 --}}
        <div class="bg-amber-50 text-amber-800 p-3 rounded-lg text-sm mb-4 leading-7">نشستِ شما تمام شده است. دوباره وارد شوید تا از همان صفحه‌ای که بودید ادامه دهید.</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">{{ $errors->first() }}</div>
    @endif
    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 p-3 rounded-lg text-sm mb-4">{{ session('success') }}</div>
    @endif

    @unless($adminOnly)
    <div class="grid grid-cols-2 gap-2 bg-slate-100 p-1 rounded-xl mb-6" role="tablist">
        <button type="button" data-tab="mobile" class="tab-btn py-2 rounded-lg text-sm font-bold">📱 موبایل</button>
        <button type="button" data-tab="email" class="tab-btn py-2 rounded-lg text-sm font-bold">✉️ ایمیل</button>
    </div>
    @endunless

    {{-- موبایل --}}
    <section data-panel="mobile" class="tab-panel">
        @if($methods['mobile'])
            <p class="text-sm text-slate-500 mb-4 leading-7">کدِ ورود برایتان پیامک می‌شود. اگر حساب ندارید، همین‌جا ساخته می‌شود؛ همان حسابی که در برنامه دارید.</p>
            <form action="{{ route('login.otp.request') }}" method="POST" class="space-y-4">
                @csrf
                @if($__intended = \App\Support\LoginRedirect::intendedPath(request()))<input type="hidden" name="intended" value="{{ $__intended }}">@endif
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">شماره‌ی موبایل</label>
                    <input type="tel" name="mobile" value="{{ old('mobile') }}" placeholder="09121234567" dir="ltr" inputmode="numeric" autocomplete="tel" required class="{{ $input }} text-left">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">دریافتِ کد</button>
            </form>
        @else
            <div class="bg-amber-50 text-amber-800 p-4 rounded-lg text-sm leading-7">{{ $methods['messages']['mobile'] }}
                @if($methods['email']) <button type="button" data-tab="email" class="tab-btn-link text-blue-700 font-bold">ورود با ایمیل</button>@endif
            </div>
        @endif
    </section>

    {{-- ایمیل --}}
    <section data-panel="email" class="tab-panel">
        @if($methods['email'])
            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf
                @if($__intended = \App\Support\LoginRedirect::intendedPath(request()))<input type="hidden" name="intended" value="{{ $__intended }}">@endif
                @if($adminOnly)<input type="hidden" name="for" value="admin">@endif
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">ایمیل</label>
                    <input type="email" name="email" value="{{ old('email') }}" dir="ltr" autocomplete="username" required class="{{ $input }}">
                </div>
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-sm font-medium text-slate-700">رمز عبور</label>
                        <a href="{{ route('password.request') }}" class="text-xs text-blue-600">رمز را فراموش کرده‌اید؟</a>
                    </div>
                    <input type="password" name="password" dir="ltr" autocomplete="current-password" required class="{{ $input }}">
                </div>
                @if(\App\Support\AuthMethods::remember())
                    <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" checked> مرا به خاطر بسپار</label>
                @endif
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">ورود</button>
            </form>
            @unless($adminOnly)
            <p class="text-center text-sm text-slate-500 mt-5">
                حساب ندارید؟
                @if($methods['registration'])
                    <a href="{{ route('register') }}" class="text-blue-600 font-bold">ثبت‌نام با ایمیل</a>
                @else
                    <span>{{ $methods['messages']['registration'] }}</span>
                @endif
            </p>
            @endunless
        @else
            <div class="bg-amber-50 text-amber-800 p-4 rounded-lg text-sm leading-7">{{ $methods['messages']['email'] }}
                @if($methods['mobile']) <button type="button" data-tab="mobile" class="tab-btn-link text-blue-700 font-bold">ورود با موبایل</button>@endif
            </div>
            <p class="text-center text-xs text-slate-400 mt-4"><a href="{{ route('login', ['for' => 'admin']) }}">ورودِ مدیران</a></p>
        @endif
    </section>
</div>

<script>
    (function () {
        const set = (t) => {
            document.querySelectorAll('.tab-panel').forEach(p => p.hidden = p.dataset.panel !== t);
            document.querySelectorAll('.tab-btn').forEach(b => {
                const on = b.dataset.tab === t;
                b.className = 'tab-btn py-2 rounded-lg text-sm font-bold ' + (on ? 'bg-white shadow text-slate-900' : 'text-slate-500');
                b.setAttribute('aria-selected', on);
            });
        };
        document.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('click', () => set(b.dataset.tab)));
        set(@json($tab));
    })();
</script>
@endsection
