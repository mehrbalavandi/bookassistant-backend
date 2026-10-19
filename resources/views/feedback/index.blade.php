{{-- 🌟 FB-1: انتقادات و پیشنهادات / گزارشِ ایراد (سایت) --}}
@extends('layouts.site')
@section('title', 'انتقادات و پیشنهادات')
@section('noindex', '1')

@section('content')
@php
    $input = 'w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none';
    $badge = fn ($s) => match ($s) {
        'resolved' => 'bg-emerald-100 text-emerald-800',
        'in_progress' => 'bg-blue-100 text-blue-800',
        'closed' => 'bg-slate-200 text-slate-700',
        default => 'bg-amber-100 text-amber-800',
    };
@endphp
<div class="w-full max-w-2xl mx-auto space-y-6">
    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100">
        <h1 class="text-xl font-bold text-slate-900 mb-1">انتقادات و پیشنهادات</h1>
        <p class="text-slate-500 text-sm mb-5 leading-7">ایرادی در اپ یا سایت دیدید، یا پیشنهادی دارید؟ بنویسید؛ اگر می‌توانید تصویرِ صفحه (اسکرین‌شات) را هم بفرستید. پاسخ را همین‌جا و در بخشِ «انتقادات و پیشنهادات»ِ اپ می‌بینید.</p>

        @if(session('success'))
            <div class="bg-emerald-50 text-emerald-700 p-3 rounded-lg text-sm mb-4 leading-7">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4"><ul class="space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <form action="{{ route('feedback.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">نوع</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($kinds as $k => $label)
                        <label class="flex items-center gap-1 border border-slate-200 rounded-lg px-3 py-2 cursor-pointer has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                            <input type="radio" name="kind" value="{{ $k }}" @checked(old('kind', 'bug') === $k)> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1" for="subject">موضوع</label>
                <input id="subject" name="subject" value="{{ old('subject') }}" maxlength="150" required class="{{ $input }}" placeholder="مثلاً: پخشِ صوت در صفحه‌ی ۲۴ قطع می‌شود">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1" for="book_title">کتاب (اختیاری)</label>
                <input id="book_title" name="book_title" value="{{ old('book_title') }}" maxlength="150" class="{{ $input }}" placeholder="مثلاً: Mindset 01، صفحه‌ی ۲۴">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1" for="message">شرح</label>
                <textarea id="message" name="message" rows="6" maxlength="5000" required class="{{ $input }}" placeholder="چه کردید، چه انتظار داشتید و چه شد؟">{{ old('message') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1" for="images">تصویرها (اختیاری، حداکثر {{ \App\Models\Feedback::MAX_IMAGES }} تصویر، هرکدام تا {{ \App\Models\Feedback::MAX_IMAGE_KB / 1024 }} مگابایت)</label>
                <input id="images" type="file" name="images[]" accept="image/*" multiple class="block w-full text-sm">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700">ارسال</button>
        </form>
    </div>

    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100">
        <h2 class="text-lg font-bold text-slate-900 mb-4">پیام‌های من</h2>
        @if($items->isEmpty())
            <p class="text-slate-500 text-sm">هنوز پیامی نفرستاده‌اید.</p>
        @else
            <ul class="space-y-4">
                @foreach($items as $f)
                    <li class="border border-slate-100 rounded-xl p-4">
                        <div class="flex justify-between items-start gap-3">
                            <div>
                                <div class="font-bold text-slate-800">{{ $f->subject }}</div>
                                <div class="text-xs text-slate-500 mt-1">{{ $f->kind_label }} · {{ $f->created_at?->format('Y-m-d H:i') }}@if($f->book_title) · {{ $f->book_title }}@endif</div>
                            </div>
                            <span class="text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap {{ $badge($f->status) }}">{{ $f->status_label }}</span>
                        </div>
                        <p class="text-sm text-slate-700 mt-3 leading-7 whitespace-pre-line">{{ $f->message }}</p>
                        @if($f->attachments->isNotEmpty())
                            <div class="flex flex-wrap gap-2 mt-3">
                                @foreach($f->attachments as $a)
                                    <a href="{{ route('feedback.attachment', $a) }}" target="_blank"><img src="{{ route('feedback.attachment', $a) }}" alt="" class="w-20 h-20 object-cover rounded-lg border border-slate-200"></a>
                                @endforeach
                            </div>
                        @endif
                        @if($f->admin_reply)
                            <div class="bg-blue-50 text-blue-900 rounded-lg p-3 mt-3 text-sm leading-7">
                                <div class="text-xs font-bold mb-1">پاسخِ پشتیبانی · {{ $f->replied_at?->format('Y-m-d H:i') }}</div>
                                <div class="whitespace-pre-line">{{ $f->admin_reply }}</div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
