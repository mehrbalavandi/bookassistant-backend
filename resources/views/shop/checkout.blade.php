{{-- 🌟 PAY-6: انتخابِ روشِ خرید (فقط روش‌هایی که در پنل روشن‌اند) --}}
@extends('layouts.site')
@section('title', 'خریدِ '.$book->title)
@section('noindex', '1')

@section('content')
<div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-lg mx-auto">
    <h1 class="text-xl font-bold text-slate-900 mb-1">خریدِ «{{ $book->title }}»</h1>
    <p class="text-slate-600 mb-6">مبلغ: <strong class="text-emerald-700">{{ number_format($price) }} تومان</strong></p>

    @if(session('error'))
        <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">{{ session('error') }}</div>
    @endif

    @if($openCardOrder)
        <div class="bg-blue-50 text-blue-800 p-4 rounded-lg text-sm mb-5 leading-7">
            برای این کتاب یک سفارشِ کارت‌به‌کارتِ باز دارید ({{ $openCardOrder->status_label }}).
            <a href="{{ route('orders.show', $openCardOrder) }}" class="font-bold underline">دیدنِ همان سفارش</a>
        </div>
    @endif

    @if(! $methods['gateway'] && ! $methods['card'])
        <div class="bg-amber-50 text-amber-800 p-4 rounded-lg text-sm leading-7">خرید فعلاً ممکن نیست. لطفاً بعداً دوباره سر بزنید یا با پشتیبانی تماس بگیرید.</div>
    @else
        <p class="text-sm text-slate-500 mb-3">روشِ پرداخت را انتخاب کنید:</p>
        <div class="space-y-3">
            @if($methods['gateway'])
                <form action="{{ route('checkout.store', $book) }}" method="POST">
                    @csrf
                    <input type="hidden" name="method" value="gateway">
                    <button class="w-full text-right border-2 border-blue-600 rounded-xl p-4 hover:bg-blue-50 transition">
                        <div class="font-bold text-slate-900">💳 پرداختِ اینترنتی</div>
                        <div class="text-sm text-slate-500 mt-1">با همه‌ی کارت‌های شتاب؛ کتاب بلافاصله فعال می‌شود.</div>
                    </button>
                </form>
            @endif
            @if($methods['card'])
                <form action="{{ route('checkout.store', $book) }}" method="POST">
                    @csrf
                    <input type="hidden" name="method" value="card">
                    <button class="w-full text-right border-2 border-slate-200 rounded-xl p-4 hover:bg-slate-50 transition">
                        <div class="font-bold text-slate-900">🏦 کارت‌به‌کارت</div>
                        <div class="text-sm text-slate-500 mt-1">مبلغ را به کارتِ ما واریز و رسید را ثبت کنید؛ بعد از بررسی فعال می‌شود.</div>
                    </button>
                </form>
            @endif
        </div>
    @endif
    <p class="text-center mt-6"><a href="{{ route('home') }}" class="text-sm text-blue-600">بازگشت به کتاب‌ها</a></p>
</div>
@endsection
