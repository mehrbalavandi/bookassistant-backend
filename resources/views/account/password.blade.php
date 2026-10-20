{{-- 🌟 SITE-13: تغییرِ رمزِ عبور. فیلدِ پنهانِ username + current/new-password تا مرورگر «به‌روزرسانیِ رمز» را بپرسد. --}}
@extends('layouts.site')
@section('title', 'تغییرِ رمزِ عبور')
@section('noindex', '1')

@section('content')
@php($input = 'w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none')
<div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-md mx-auto">
    <h1 class="text-2xl font-bold text-center text-slate-900 mb-6">{{ $hasPassword ? 'تغییرِ رمزِ عبور' : 'ساختنِ رمزِ عبور' }}</h1>

    @if($errors->any())
        <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">
            <ul class="space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 p-3 rounded-lg text-sm mb-4">{{ session('success') }}</div>
    @endif

    @if(blank($user->email))
        <div class="bg-slate-50 text-slate-700 p-4 rounded-lg text-sm leading-7">
            حسابِ شما با شماره‌ی موبایل و کدِ پیامکی وارد می‌شود و رمزِ عبور لازم ندارد.
        </div>
    @else
        @unless($hasPassword)
            <p class="text-sm text-slate-500 mb-4 leading-7">حسابِ شما هنوز رمز ندارد. با ساختنِ رمز، با ایمیل و همین رمز هم می‌توانید وارد شوید.</p>
        @endunless
        <form action="{{ route('account.password.update') }}" method="POST" class="space-y-4">
            @csrf
            {{-- برای مدیرِ رمزِ مرورگر: رمز مالِ کدام حساب است --}}
            <input type="text" name="username" value="{{ $user->email }}" autocomplete="username" readonly tabindex="-1" aria-hidden="true" style="display:none">
            @if($hasPassword)
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">رمزِ فعلی</label>
                    <input type="password" name="current_password" dir="ltr" autocomplete="current-password" required class="{{ $input }}">
                </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">رمزِ تازه</label>
                <input type="password" name="password" dir="ltr" autocomplete="new-password" required class="{{ $input }}">
                @if($passwordHint)<p class="text-xs text-slate-500 mt-1">{{ $passwordHint }}</p>@endif
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">تکرارِ رمزِ تازه</label>
                <input type="password" name="password_confirmation" dir="ltr" autocomplete="new-password" required class="{{ $input }}">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">ذخیره‌ی رمز</button>
        </form>
        <p class="text-xs text-slate-500 mt-4 leading-6">بعد از ذخیره، مرورگر می‌پرسد رمزِ تازه را ذخیره کند یا نه. «مرا به خاطر بسپار» در مرورگرهای دیگر باطل می‌شود.</p>
    @endif
</div>
@endsection
