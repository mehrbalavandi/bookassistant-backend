{{-- 🌟 SITE-6: درخواستِ لینکِ بازیابیِ رمز --}}
@extends('layouts.site')
@section('title', 'بازیابیِ رمز')
@section('noindex', '1')

@section('content')
<div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-md mx-auto">
    <h1 class="text-2xl font-bold text-center text-slate-900 mb-2">بازیابیِ رمز</h1>
    <p class="text-center text-sm text-slate-500 mb-6 leading-7">ایمیلِ حسابتان را بنویسید تا لینکِ ساختنِ رمزِ تازه برایتان فرستاده شود.</p>

    @if($errors->any())
        <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">{{ $errors->first() }}</div>
    @endif
    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 p-3 rounded-lg text-sm mb-4 leading-7">{{ session('success') }}</div>
    @endif

    <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">ایمیل</label>
            <input type="email" name="email" value="{{ old('email') }}" dir="ltr" autocomplete="email" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">فرستادنِ لینک</button>
    </form>
    @if($methods['mobile'])
        <p class="text-center text-sm text-slate-500 mt-6 leading-7">حسابتان با موبایل است؟ رمز لازم ندارید؛ <a href="{{ route('login') }}" class="text-blue-600 font-bold">با کدِ پیامکی وارد شوید</a>.</p>
    @endif
</div>
@endsection
