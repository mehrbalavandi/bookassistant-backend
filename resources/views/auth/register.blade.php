{{-- 🌟 SITE-2: ثبت‌نام با ایمیل (اگر ایمیل و ثبت‌نامِ تازه در پنل روشن باشند). ثبت‌نام با موبایل در همان صفحه‌ی ورود است. --}}
@extends('layouts.site')
@section('title', 'ثبت‌نام')
@section('noindex', '1')

@section('content')
@php($input = 'w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none')
<div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-md mx-auto">
    <h1 class="text-2xl font-bold text-center text-slate-900 mb-2">ساختِ حساب با ایمیل</h1>
    <p class="text-center text-sm text-slate-500 mb-6">با همین حساب در برنامه هم وارد می‌شوید.</p>

    @if($errors->any())
        <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">
            <ul class="space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if(! $methods['email'] || ! $methods['registration'])
        <div class="bg-amber-50 text-amber-800 p-4 rounded-lg text-sm leading-7">
            {{ ! $methods['email'] ? $methods['messages']['email'] : $methods['messages']['registration'] }}
        </div>
        @if($methods['mobile'] && $methods['registration'])
            <a href="{{ route('login') }}" class="block text-center w-full mt-4 border border-blue-600 text-blue-700 font-bold py-3 rounded-xl hover:bg-blue-50 transition">ثبت‌نام با شماره‌ی موبایل</a>
        @endif
    @else
        <form action="{{ route('register.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">نام</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required class="{{ $input }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">نام خانوادگی</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required class="{{ $input }}">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">ایمیل</label>
                <input type="email" name="email" value="{{ old('email') }}" dir="ltr" autocomplete="username" required class="{{ $input }}">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">رمز عبور</label>
                <input type="password" name="password" dir="ltr" autocomplete="new-password" required class="{{ $input }}">
                @if($methods['password_hint'])<p class="text-xs text-slate-500 mt-1">{{ $methods['password_hint'] }}</p>@endif
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">تکرارِ رمز عبور</label>
                <input type="password" name="password_confirmation" dir="ltr" autocomplete="new-password" required class="{{ $input }}">
            </div>
            <p class="text-xs text-slate-500 leading-6">با ثبت‌نام، <a href="{{ route('page', 'terms') }}" class="text-blue-600">قوانین</a> و <a href="{{ route('page', 'privacy') }}" class="text-blue-600">حریمِ خصوصی</a> را می‌پذیرید.</p>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">ساختِ حساب</button>
        </form>
        @if($methods['mobile'])
            <a href="{{ route('login') }}" class="block text-center w-full mt-4 border border-blue-600 text-blue-700 font-bold py-3 rounded-xl hover:bg-blue-50 transition">یا ثبت‌نام با شماره‌ی موبایل</a>
        @endif
    @endif

    <p class="text-center text-sm text-slate-500 mt-6">حساب دارید؟ <a href="{{ route('login', ['tab' => 'email']) }}" class="text-blue-600 font-bold">وارد شوید</a></p>
</div>
@endsection
