{{--
    🌟 فهرستِ دسته‌بندی‌شده‌ی کتاب‌ها (مشترکِ welcome و dashboard).
    - $groups: دسته‌ها به ترتیبِ شماره‌ی ردیفِ دسته (Book::groupForCatalog)
    - $selected: دسته‌ی باز (یا null = نمایشِ کارت‌های دسته‌ها)
    - $canBuy: true در داشبورد (دکمه‌ی خرید/خریداری‌شده)، false برای مهمان (ورود و خرید)
--}}
<style>
    .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr)); gap: 20px; }
    .cat-card { display: block; background: white; border-radius: 12px; padding: 22px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-decoration: none; color: inherit; transition: transform 0.2s, border-color 0.2s; }
    .cat-card:hover { transform: translateY(-4px); border-color: #4f46e5; }
    .cat-card h3 { margin: 0 0 8px; color: #111827; font-size: 18px; }
    .cat-card p { margin: 0 0 14px; color: #6b7280; font-size: 14px; line-height: 1.7; }
    .cat-meta { display: flex; justify-content: space-between; align-items: center; color: #4f46e5; font-weight: bold; font-size: 14px; }
    .cat-header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
    .cat-back { color: #4f46e5; text-decoration: none; font-weight: bold; font-size: 14px; }
    .cat-desc { color: #6b7280; margin: 0 0 25px; line-height: 1.8; }
    /* 📱 موبایل: کارت‌ها تمام‌عرض و فشرده‌تر؛ کلِ کارت لمس‌پذیر است */
    @media (max-width: 600px) {
        .cat-grid { gap: 12px; }
        .cat-card { padding: 16px; }
        .cat-card:hover { transform: none; }
        .cat-card h3 { font-size: 16px; }
        .cat-desc { margin-bottom: 16px; font-size: 14px; }
    }
</style>

@if($groups->isEmpty())
    <p style="color: #6b7280;">هنوز کتابی منتشر نشده است.</p>

@elseif(! $selected)
    {{-- کارت‌های دسته‌ها --}}
    <div class="cat-grid">
        @foreach($groups as $group)
            <a class="cat-card" href="{{ url('/') }}?category={{ $group->key }}">
                <h3>📚 {{ $group->title }}</h3>
                @if($group->description)
                    <p>{{ $group->description }}</p>
                @endif
                <div class="cat-meta">
                    <span>{{ $group->books->count() }} کتاب</span>
                    <span>مشاهده ←</span>
                </div>
            </a>
        @endforeach
    </div>

@else
    {{-- کتاب‌های یک دسته، به ترتیبِ شماره‌ی ردیف --}}
    <div class="cat-header">
        @if($groups->count() > 1)
            <a class="cat-back" href="{{ url('/') }}">→ همه‌ی دسته‌ها</a>
        @endif
    </div>
    <h3 style="margin: 0 0 8px;">{{ $selected->title }}</h3>
    @if($selected->description)
        <p class="cat-desc">{{ $selected->description }}</p>
    @else
        <div style="height: 17px;"></div>
    @endif

    <div class="grid">
        @foreach($selected->books as $book)
            <div class="card">
                <h3>{{ $book->title }}</h3>

                <div class="price">
                    @if($book->price == 0)
                        <span style="color: blue;">رایگان</span>
                    @elseif($book->discount > 0)
                        <span style="text-decoration: line-through; color: #9ca3af; font-size: 14px;">
                            {{ number_format($book->price) }}
                        </span>
                        <span style="background-color: #ef4444; color: white; padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-right: 5px;">
                            {{ $book->discount }}%
                        </span>
                        <br>
                        {{ number_format($book->final_price) }} تومان
                    @else
                        {{ number_format($book->price) }} تومان
                    @endif
                </div>

                @if(! $book->is_purchased && ! $book->has_content)
                    {{-- 🌟 SF-4: کتابِ بی‌محتوا خریدنی نیست --}}
                    <div class="btn" style="display: block; margin-top: 15px; background:#f1f5f9; color:#64748b; cursor:default;">
                        ⏳ در حالِ آماده‌سازی
                    </div>
                @elseif(! $canBuy)
                    <a href="{{ url('/login') }}" class="btn btn-primary" style="display: block; margin-top: 15px;">
                        ورود و خرید کتاب
                    </a>
                @elseif($book->is_purchased)
                    <div class="btn btn-success">
                        ✓ خریداری شده (فعال در اپ)
                    </div>
                @else
                    <a href="{{ route('checkout', $book->id) }}" class="btn btn-primary">
                        خرید و فعال‌سازی
                    </a>
                @endif
            </div>
        @endforeach
    </div>
@endif
