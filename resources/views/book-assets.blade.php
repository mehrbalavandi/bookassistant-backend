{{-- 🐞 مدیریتِ تکیِ فایل‌های صوت/تصویرِ کتاب.
     کنارِ هر فایلِ صوتی یک پلیرِ مرورگر و کنارِ هر تصویر یک بندانگشتی نشان
     می‌دهد، و هر ردیف دکمه‌ی حذفِ خودش را دارد (بدونِ نیاز به تیک‌زدن و
     ثبتِ فرم). حذف از طریقِ متدِ deleteAssetFile روی همین صفحه‌ی Livewire
     انجام می‌شود و فهرست بلافاصله تازه می‌شود.
     متغیرها از viewData در EditBook می‌آیند: $files, $scope, $kind, $bookId --}}

<div class="space-y-2">
    @forelse ($files as $name)
        <div class="flex items-center gap-3 rounded-lg border border-gray-200 p-2 dark:border-gray-700">
            <div class="shrink-0">
                @if ($kind === 'images')
                    <img src="{{ route('admin.book-asset', [$bookId, $scope, $kind, $name]) }}"
                         alt="{{ $name }}"
                         class="h-16 w-16 rounded object-cover" loading="lazy">
                @else
                    <audio controls preload="none" style="max-width: 260px;">
                        <source src="{{ route('admin.book-asset', [$bookId, $scope, $kind, $name]) }}">
                    </audio>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm text-gray-700 dark:text-gray-200" title="{{ $name }}">
                    {{ $name }}
                </p>
            </div>

            <button type="button"
                    wire:click="deleteAssetFile(@js($scope), @js($kind), @js($name))"
                    wire:confirm="فایل «{{ $name }}» حذف شود؟"
                    class="shrink-0 rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-500">
                حذف
            </button>
        </div>
    @empty
        <p class="text-sm text-gray-500">فایلی موجود نیست.</p>
    @endforelse
</div>