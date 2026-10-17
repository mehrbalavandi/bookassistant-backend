{{-- 🌟 SITE-5: تماس با ما (از اطلاعاتِ پشتیبانیِ پنل) --}}
@extends('layouts.site')
@section('title', 'تماس با ما')

@section('content')
@php($s = array_filter($support, fn ($v) => (string) $v !== ''))
<article class="bg-white p-6 sm:p-10 rounded-2xl shadow-sm border border-slate-100">
    <h1 class="text-2xl font-bold text-slate-900 mb-6">تماس با ما</h1>
    @if(! $s)
        <p class="text-slate-500">راه‌های تماس هنوز ثبت نشده است.</p>
    @else
        <ul class="space-y-3 text-slate-700">
            @isset($s['phone'])<li>📞 تلفن: <a href="tel:{{ $s['phone'] }}" dir="ltr" class="text-blue-600">{{ $s['phone'] }}</a></li>@endisset
            @isset($s['email'])<li>✉️ ایمیل: <a href="mailto:{{ $s['email'] }}" dir="ltr" class="text-blue-600">{{ $s['email'] }}</a></li>@endisset
            @isset($s['telegram'])<li>💬 تلگرام: <a href="https://t.me/{{ ltrim($s['telegram'], '@') }}" dir="ltr" class="text-blue-600" rel="noopener" target="_blank">{{ $s['telegram'] }}</a></li>@endisset
            @isset($s['whatsapp'])<li>🟢 واتس‌اپ: <a href="https://wa.me/{{ preg_replace('/\D/', '', $s['whatsapp']) }}" dir="ltr" class="text-blue-600" rel="noopener" target="_blank">{{ $s['whatsapp'] }}</a></li>@endisset
            @isset($s['hours'])<li>🕘 ساعتِ پاسخ‌گویی: {{ $s['hours'] }}</li>@endisset
        </ul>
    @endif
</article>
@endsection
