{{-- 🌟 PAY-6: یک سفارش؛ برای کارت‌به‌کارت مشخصاتِ کارت، «مبلغِ دقیق» (PAY-5) و فرمِ رسید --}}
@extends('layouts.site')
@section('title', 'سفارشِ '.$order->id)
@section('noindex', '1')

@section('content')
@php
    $input = 'w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none';
    $badge = match ($order->status) {
        'paid' => 'bg-emerald-100 text-emerald-800',
        'awaiting_review' => 'bg-blue-100 text-blue-800',
        'rejected', 'failed' => 'bg-red-100 text-red-800',
        'canceled' => 'bg-slate-200 text-slate-700',
        default => 'bg-amber-100 text-amber-800',
    };
@endphp
<div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-lg mx-auto">
    <div class="flex justify-between items-start gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">سفارشِ {{ $order->id }}</h1>
            <p class="text-slate-600 text-sm mt-1">«{{ $order->book?->title }}»</p>
        </div>
        <span class="text-xs font-bold px-3 py-1 rounded-full {{ $badge }}">{{ $order->status_label }}</span>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 p-3 rounded-lg text-sm mb-4 leading-7">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4"><ul class="space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if($order->status === 'rejected' && $order->admin_note)
        <div class="bg-red-50 text-red-700 p-3 rounded-lg text-sm mb-4 leading-7">دلیلِ رد: {{ $order->admin_note }}<br>می‌توانید رسیدِ درست را دوباره بفرستید.</div>
    @endif

    @if($order->status === 'paid')
        <div class="bg-emerald-50 text-emerald-800 p-4 rounded-lg leading-7">✅ کتاب برای شما فعال است؛ در برنامه با همین حساب وارد شوید.</div>
    @elseif($order->method === 'card')
        @if(! $card)
            <div class="bg-amber-50 text-amber-800 p-4 rounded-lg text-sm leading-7">کارت‌به‌کارت فعلاً فعال نیست. اگر پیش‌تر واریز کرده‌اید، با پشتیبانی تماس بگیرید.</div>
        @else
            <div class="bg-slate-50 rounded-xl p-4 space-y-3 mb-5">
                <div>
                    <div class="text-xs text-slate-500">مبلغِ دقیقِ واریز</div>
                    <div class="flex items-center gap-2">
                        <strong class="text-2xl text-emerald-700" dir="ltr">{{ number_format($order->amount) }}</strong> <span>تومان</span>
                        <button type="button" data-copy="{{ $order->amount }}" class="copy text-xs border border-slate-300 rounded px-2 py-1">کپی</button>
                    </div>
                    @if($order->amount_suffix)
                        <p class="text-xs text-amber-700 mt-1 leading-6">دقیقاً همین مبلغ را واریز کنید، نه عددِ رُند؛ {{ $order->amount_suffix }} تومانِ آخر شناسه‌ی سفارشِ شماست و واریزتان را سریع‌تر پیدا می‌کنیم.</p>
                    @endif
                </div>
                <div>
                    <div class="text-xs text-slate-500">شماره‌ی کارت</div>
                    <div class="flex items-center gap-2">
                        <strong class="text-lg tracking-wider" dir="ltr">{{ trim(chunk_split($card['number'], 4, ' ')) }}</strong>
                        <button type="button" data-copy="{{ $card['number'] }}" class="copy text-xs border border-slate-300 rounded px-2 py-1">کپی</button>
                    </div>
                </div>
                @if($card['holder'])<div class="text-sm">به نامِ <strong>{{ $card['holder'] }}</strong>@if($card['bank']) — {{ $card['bank'] }}@endif</div>@endif
                @if($card['sheba'])<div class="text-sm">شبا: <span dir="ltr">{{ $card['sheba'] }}</span></div>@endif
            </div>

            @if($order->canSubmitReceipt())
                <h2 class="font-bold text-slate-900 mb-1">{{ $order->status === 'awaiting_review' ? 'ویرایشِ رسید' : 'ثبتِ رسیدِ واریز' }}</h2>
                @if($order->status === 'awaiting_review')
                    <p class="text-sm text-blue-700 mb-3 leading-7">رسیدِ شما رسیده و در حالِ بررسی است. اگر اشتباه نوشته‌اید، اصلاحش کنید.</p>
                @endif
                <form action="{{ route('orders.receipt', $order) }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">کدِ پیگیری / شماره‌ی مرجع</label>
                        <input type="text" name="tracking_code" value="{{ old('tracking_code', $order->card_tracking_code) }}" dir="ltr" autocomplete="off" required class="{{ $input }}">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">۴ رقمِ آخرِ کارتِ شما</label>
                            <input type="text" name="card_last4" value="{{ old('card_last4', $order->card_last4) }}" dir="ltr" autocomplete="on" inputmode="numeric" maxlength="4" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">زمانِ واریز</label>
                            <input type="text" name="paid_at" value="{{ old('paid_at', $order->card_paid_at) }}" autocomplete="off" placeholder="۱۴۰۵/۰۷/۲۵ ۱۰:۳۰" class="{{ $input }}">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">تصویرِ رسید (اختیاری)</label>
                        <input type="file" name="receipt" accept="image/*" class="block w-full text-sm">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">ثبتِ رسید</button>
                </form>
            @endif
        @endif
    @elseif($order->status === 'pending')
        <form action="{{ route('checkout.store', $order->book_id) }}" method="POST">
            @csrf
            <input type="hidden" name="method" value="gateway">
            <button class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">پرداختِ اینترنتی</button>
        </form>
    @endif

    <p class="text-center mt-6 text-sm"><a href="{{ route('orders.index') }}" class="text-blue-600">همه‌ی سفارش‌های من</a></p>
</div>
<script>
    document.querySelectorAll('.copy').forEach(b => b.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(b.dataset.copy); b.textContent = 'کپی شد ✓'; setTimeout(() => b.textContent = 'کپی', 1500); } catch (e) {}
    }));
</script>
@endsection
