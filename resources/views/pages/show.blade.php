{{-- 🌟 SITE-5: صفحه‌ی ثابت (متن از پنل) --}}
@extends('layouts.site')
@section('title', $title)

@section('content')
<article class="bg-white p-6 sm:p-10 rounded-2xl shadow-sm border border-slate-100">
    <h1 class="text-2xl font-bold text-slate-900 mb-4">{{ $title }}</h1>
    @if(trim(strip_tags($html)) !== '')
        <div class="prose-fa">{!! strip_tags($html, '<p><br><strong><b><em><i><u><s><h2><h3><h4><ul><ol><li><a><blockquote><hr>') !!}</div>
    @else
        <p class="text-slate-500">متنِ این صفحه هنوز نوشته نشده است.</p>
    @endif
</article>
@endsection
