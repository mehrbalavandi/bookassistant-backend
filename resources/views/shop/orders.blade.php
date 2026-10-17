{{-- 🌟 PAY-6: سفارش‌های من (سایت) --}}
@extends('layouts.site')
@section('title', 'سفارش‌های من')
@section('noindex', '1')

@section('content')
<div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-2xl mx-auto">
    <h1 class="text-xl font-bold text-slate-900 mb-4">سفارش‌های من</h1>
    @if($orders->isEmpty())
        <p class="text-slate-500">هنوز سفارشی ندارید.</p>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach($orders as $o)
                <li>
                    <a href="{{ route('orders.show', $o) }}" class="flex justify-between items-center gap-3 py-3 hover:bg-slate-50 px-2 rounded-lg">
                        <div>
                            <div class="font-bold text-slate-800">{{ $o->book?->title }}</div>
                            <div class="text-xs text-slate-500 mt-1">سفارشِ {{ $o->id }} · {{ $o->method === 'card' ? 'کارت‌به‌کارت' : 'پرداختِ اینترنتی' }} · {{ number_format($o->amount) }} تومان</div>
                        </div>
                        <span class="text-xs text-slate-600 whitespace-nowrap">{{ $o->status_label }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
    <p class="text-center mt-6 text-sm"><a href="{{ route('home') }}" class="text-blue-600">بازگشت به کتاب‌ها</a></p>
</div>
@endsection
