{{-- 🐞 مدیریتِ فایل‌های صوتی/تصویریِ کتاب.
     هر ردیف: چک‌باکسِ انتخاب + پلیرِ صوت یا بندانگشتیِ تصویر + نامِ فایل +
     دکمه‌ی حذفِ همان ردیف.
     🌟 اصلاحِ درخواستیِ کاربر: چک‌باکس‌ها دیگر یک لیستِ جدا در پایینِ صفحه
     نیستند و داخلِ همین ردیف‌ها قرار گرفته‌اند؛ نوارِ «انتخابِ همه» هم بالای
     لیست می‌چسبد (sticky) تا در فهرست‌های بلند همیشه در دسترس باشد. دکمه‌های
     «حذفِ انتخاب‌شده‌ها/انصراف» با stickyModalFooter در EditBook شناور شده‌اند.

     انتخاب‌ها با Alpine نگه‌داری و با $wire.set (به‌صورتِ deferred، بدونِ
     رفت‌وبرگشتِ شبکه‌ای در هر تیک) داخلِ همان state فرم ریخته می‌شوند که قبلاً
     CheckboxList پرش می‌کرد — پس منطقِ حذفِ گروهی در EditBook دست‌نخورده است.
     متغیرها از viewData در EditBook می‌آیند: $assets, $scope, $kind, $bookId --}}

@php
    $statePath = $getStatePath();
    $allPaths = array_column($assets, 'path');
@endphp

<div
    dir="rtl"
    x-data="{
        sel: [],
        paths: @js($allPaths),
        get allChecked() { return this.paths.length > 0 && this.sel.length === this.paths.length; },
        toggleAll(checked) { this.sel = checked ? [...this.paths] : []; },
    }"
    x-init="$watch('sel', value => $wire.set(@js($statePath), value, false))"
    class="space-y-2"
>
    @if (count($assets) > 0)
        <div class="sticky top-0 z-10 -mx-1 mb-2 flex items-center justify-between gap-3 border-b border-gray-200 bg-white px-1 py-2 dark:border-gray-700 dark:bg-gray-900">
            <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                <input type="checkbox"
                       class="h-4 w-4 rounded border-gray-300 text-primary-600"
                       :checked="allChecked"
                       @change="toggleAll($event.target.checked)">
                <span>انتخابِ همه</span>
            </label>

            <span class="text-xs text-gray-500 dark:text-gray-400"
                  x-text="sel.length + ' از ' + paths.length + ' انتخاب شده'"></span>
        </div>
    @endif

    @forelse ($assets as $asset)
        <div class="flex items-center gap-3 rounded-lg border border-gray-200 p-2 dark:border-gray-700">
            <input type="checkbox"
                   class="h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600"
                   x-model="sel"
                   value="{{ $asset['path'] }}">

            <div class="shrink-0">
                @if ($kind === 'images')
                    <img src="{{ route('admin.book-asset', [$bookId, $scope, $kind, $asset['name']]) }}"
                         alt="{{ $asset['name'] }}"
                         class="h-16 w-16 rounded object-cover" loading="lazy">
                @else
                    <audio controls preload="none" style="max-width: 240px;">
                        <source src="{{ route('admin.book-asset', [$bookId, $scope, $kind, $asset['name']]) }}">
                    </audio>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm text-gray-700 dark:text-gray-200" title="{{ $asset['name'] }}">
                    {{ $asset['name'] }}
                </p>
            </div>

            <button type="button"
                    wire:click="deleteAssetFile(@js($scope), @js($kind), @js($asset['name']))"
                    wire:confirm="فایل «{{ $asset['name'] }}» حذف شود؟"
                    class="shrink-0 rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-500">
                حذف
            </button>
        </div>
    @empty
        <p class="text-sm text-gray-500">فایلی موجود نیست.</p>
    @endforelse
</div>