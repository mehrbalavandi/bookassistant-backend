{{-- 🌟 SITE-3: نوارِ اطلاعیه (پنل ← تنظیماتِ سایت ← اطلاعیه و تعمیر). استایلِ درون‌خطی تا در همه‌ی صفحه‌ها کار کند. --}}
@if(config('site.announcement.enabled') && trim((string) config('site.announcement.text')) !== '')
    @php
        $c = match (config('site.announcement.level')) {
            'warning' => ['#fffbeb', '#92400e', '#fde68a'],
            'success' => ['#ecfdf5', '#065f46', '#a7f3d0'],
            default => ['#eff6ff', '#1e40af', '#bfdbfe'],
        };
    @endphp
    <div role="status" style="background:{{ $c[0] }};color:{{ $c[1] }};border-bottom:1px solid {{ $c[2] }};padding:10px 16px;text-align:center;font-size:14px;line-height:1.8">
        {{ config('site.announcement.text') }}
    </div>
@endif
