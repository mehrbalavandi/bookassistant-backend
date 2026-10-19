{{-- 🌟 BKP-1 --}}
<x-filament-panels::page>
    @php
        $backups = $this->getBackups();
        $labels = \App\Services\SiteBackupService::TABLE_LABELS;
        $fmt = fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : number_format(max(1, round($b / 1024))).' KB';
    @endphp

    <x-filament::section>
        <div class="text-sm leading-7 text-gray-600 dark:text-gray-300">
            هر پشتیبان یک فایلِ ZIP است با: کاربران، خریدها و سفارش‌ها، دستگاه‌ها، «مرورِ من»ِ کاربران و تنظیماتِ اپشان،
            «مرورِ آماده»، کتاب‌ها و دسته‌ها (اطلاعات، نه فایل‌های محتوا)، نسخه‌های اپ، تنظیماتِ سایت و انتقادات و پیشنهادات —
            به‌علاوه‌ی رسیدهای کارت‌به‌کارت، تصویرهای پیام‌ها و جلدِ کتاب‌ها.
            محتوای کتاب‌ها و فایل‌های نصبیِ اپ در پشتیبان نیستند (حجیم‌اند و دوباره آپلود می‌شوند).
            فایل‌ها روی سرور در <code dir="ltr">storage/app/private/backups</code> هستند؛ برای امنیتِ بیشتر هر از گاهی یکی را دانلود و جای دیگری نگه دارید.
            <br>پشتیبانِ خودکار: <b>{{ config('backup.daily') ? 'روشن (هر شب ۳:۳۰)' : 'خاموش' }}</b> — نگه‌داشتنِ {{ (int) config('backup.keep') }} پشتیبانِ آخر.
        </div>
    </x-filament::section>

    <x-filament::section>
        @if (empty($backups))
            <div class="text-sm text-gray-500">هنوز پشتیبانی نیست. «ساختنِ پشتیبانِ تازه» را بزنید.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-right text-gray-500 border-b border-gray-200 dark:border-white/10">
                            <th class="py-2 px-2">فایل</th>
                            <th class="py-2 px-2">زمان</th>
                            <th class="py-2 px-2">حجم</th>
                            <th class="py-2 px-2">محتوا</th>
                            <th class="py-2 px-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $b)
                            <tr class="border-b border-gray-100 dark:border-white/5 align-top">
                                <td class="py-2 px-2 font-mono text-xs" dir="ltr">{{ $b['name'] }}</td>
                                <td class="py-2 px-2 whitespace-nowrap" dir="ltr">{{ $b['time']->format('Y-m-d H:i') }}</td>
                                <td class="py-2 px-2 whitespace-nowrap" dir="ltr">{{ $fmt($b['size']) }}</td>
                                <td class="py-2 px-2 text-xs leading-6">
                                    @if ($b['manifest'])
                                        @foreach (['users', 'orders', 'book_user', 'study_items', 'shared_study_items', 'feedback'] as $t)
                                            @isset($b['manifest']['tables'][$t])
                                                <span class="inline-block me-2">{{ $labels[$t] }}: {{ number_format($b['manifest']['tables'][$t]) }}</span>
                                            @endisset
                                        @endforeach
                                        <span class="inline-block">فایل: {{ $b['manifest']['files'] ?? 0 }}</span>
                                    @else
                                        <span class="text-danger-600">خراب یا ناشناخته</span>
                                    @endif
                                </td>
                                <td class="py-2 px-2 whitespace-nowrap">
                                    <div class="flex gap-3">
                                        {{ ($this->downloadAction)(['name' => $b['name']]) }}
                                        @if ($b['manifest'])
                                            {{ ($this->restoreAction)(['name' => $b['name']]) }}
                                        @endif
                                        {{ ($this->deleteAction)(['name' => $b['name']]) }}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

</x-filament-panels::page>
