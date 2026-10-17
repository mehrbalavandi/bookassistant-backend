{{-- 🌟 SITE-6: ساختنِ رمزِ تازه از لینکِ ایمیل --}}
@extends('layouts.site')
@section('title', 'رمزِ تازه')
@section('noindex', '1')

@section('content')
@php($input = 'w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none')
<div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-md mx-auto">
    <h1 class="text-2xl font-bold text-center text-slate-900 mb-6">ساختنِ رمزِ تازه</h1>

    @if($errors->any())
        <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">
            <ul class="space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('password.update') }}" method="POST" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">ایمیل</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" dir="ltr" autocomplete="email" required class="{{ $input }}">
        </div>
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
</div>
@endsection
