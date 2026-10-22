{{-- 🌟 AUTH-1: مرحله‌ی کدِ ورودِ پیامکی (مرحله‌ی شماره در صفحه‌ی ورود هم هست). --}}
@extends('layouts.site')
@section('title', 'ورود با موبایل')
@section('noindex', '1')

@section('content')
    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-md mx-auto">
        <h2 class="text-2xl font-bold text-center text-slate-900 mb-2">ورود یا ثبت‌نام</h2>
        <p class="text-center text-sm text-slate-500 mb-6">با شماره‌ی موبایل؛ همان حسابی که در برنامه دارید.</p>

        @if($errors->any())
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">{{ $errors->first() }}</div>
        @endif
        @if(session('success'))
            <div class="bg-emerald-50 text-emerald-700 p-3 rounded-lg text-sm mb-4">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-amber-50 text-amber-700 p-3 rounded-lg text-sm mb-4">{{ session('error') }}</div>
        @endif

        @if(! $mobile)
            <form action="{{ route('login.otp.request') }}" method="POST" class="space-y-4">
                @csrf
                @if($__intended = \App\Support\LoginRedirect::intendedPath(request()))<input type="hidden" name="intended" value="{{ $__intended }}">@endif
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">شماره‌ی موبایل</label>
                    <input type="tel" name="mobile" value="{{ old('mobile') }}" placeholder="09121234567" dir="ltr" inputmode="numeric" autocomplete="tel" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none text-left">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">دریافتِ کد</button>
            </form>
        @else
            <form action="{{ route('login.otp.verify') }}" method="POST" class="space-y-4">
                @csrf
                @if($__intended = \App\Support\LoginRedirect::intendedPath(request()))<input type="hidden" name="intended" value="{{ $__intended }}">@endif
                <p class="text-sm text-slate-600">کد به <strong dir="ltr">{{ $mobile }}</strong> پیامک شد.</p>
                @if($debugCode)
                    <p class="text-xs text-amber-700 bg-amber-50 rounded p-2">حالتِ توسعه — کد: <strong dir="ltr">{{ $debugCode }}</strong></p>
                @endif
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">کدِ تأیید</label>
                    <input type="text" name="code" dir="ltr" inputmode="numeric" autocomplete="one-time-code" required autofocus class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none text-center tracking-widest text-lg">
                </div>
                @if(! $registered)
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">نام</label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">نام خانوادگی</label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        </div>
                    </div>
                @endif
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">{{ $registered ? 'ورود' : 'ساختِ حساب و ورود' }}</button>
            </form>
            <form action="{{ route('login.otp.request') }}" method="POST" class="mt-3 text-center">
                @csrf
                @if($__intended = \App\Support\LoginRedirect::intendedPath(request()))<input type="hidden" name="intended" value="{{ $__intended }}">@endif
                <input type="hidden" name="mobile" value="{{ $mobile }}">
                <button type="submit" class="text-sm text-blue-600">ارسالِ دوباره‌ی کد</button>
            </form>
        @endif

        <p class="text-center text-sm text-slate-500 mt-6">
            <a href="{{ route('login') }}" class="text-blue-600 font-bold">بازگشت به صفحه‌ی ورود</a>
        </p>
    </div>

@endsection
