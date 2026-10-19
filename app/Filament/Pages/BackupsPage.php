<?php

namespace App\Filament\Pages;

use App\Services\SiteBackupService;
use App\Support\SiteSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * 🌟 BKP-1: پشتیبان‌گیری از داده‌های سایت — کاربران، خریدها و سفارش‌ها، «مرورِ من»ِ کاربران،
 * «مرورِ آماده»، دستگاه‌ها، کتاب‌ها و دسته‌ها، تنظیمات، پیام‌های کاربران — و فایل‌های رسید و تصویرها.
 * ساختن، دانلود، آپلود (از سرورِ دیگر)، حذف و بازگردانی؛ پشتیبانِ خودکارِ روزانه.
 */
class BackupsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'پشتیبان‌گیری';

    protected static ?string $title = 'پشتیبان‌گیری';

    protected static ?string $slug = 'backups';

    protected static ?int $navigationSort = 91;

    protected static string $view = 'filament.pages.backups';

    private function service(): SiteBackupService
    {
        return app(SiteBackupService::class);
    }

    /** @return list<array> برای view */
    public function getBackups(): array
    {
        return $this->service()->list();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('ساختنِ پشتیبانِ تازه')
                ->icon('heroicon-o-plus-circle')
                ->action(function () {
                    @set_time_limit(0);
                    $path = $this->service()->create();
                    $this->service()->prune();
                    Notification::make()->title('پشتیبان ساخته شد')->body(basename($path))->success()->send();
                }),

            Action::make('upload')
                ->label('آپلودِ پشتیبان')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalDescription('پشتیبانی که قبلاً دانلود کرده‌اید (مثلاً از سرورِ قبلی) به فهرست اضافه می‌شود؛ بعد می‌توانید بازگردانی‌اش کنید.')
                ->form([
                    FileUpload::make('file')->label('فایلِ ZIPِ پشتیبان')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])
                        ->disk('local')->directory('tmp')->preserveFilenames()
                        ->maxSize(\App\Support\UploadLimits::effectiveMaxKb())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $tmp = $data['file'];
                    try {
                        $rel = $this->service()->import(Storage::disk('local')->path($tmp), basename($tmp));
                        Notification::make()->title('پشتیبان اضافه شد')->body(basename($rel))->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('آپلود ناموفق')->body($e->getMessage())->danger()->send();
                    } finally {
                        Storage::disk('local')->delete($tmp);
                    }
                }),

            Action::make('settings')
                ->label('پشتیبانِ خودکار')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->fillForm(fn () => ['daily' => (bool) config('backup.daily'), 'keep' => (int) config('backup.keep')])
                ->form([
                    Toggle::make('daily')->label('هر شب ساعتِ ۳:۳۰ یک پشتیبان ساخته شود')
                        ->helperText('روی سرور باید cron برای `php artisan schedule:run` (هر دقیقه) تنظیم باشد؛ همان که لغوِ سفارش‌های رهاشده هم لازم دارد.'),
                    TextInput::make('keep')->label('تعدادِ پشتیبان‌هایی که نگه داشته می‌شوند')
                        ->numeric()->minValue(1)->maxValue(365)->required()
                        ->helperText('با هر پشتیبانِ تازه، قدیمی‌ترها پاک می‌شوند.'),
                ])
                ->action(function (array $data) {
                    SiteSettings::save(['backup.daily' => (bool) $data['daily'], 'backup.keep' => (int) $data['keep']], Auth::id());
                    Notification::make()->title('ذخیره شد')->success()->send();
                }),
        ];
    }

    public function downloadAction(): Action
    {
        return Action::make('download')->label('دانلود')->icon('heroicon-o-arrow-down-tray')->link()
            ->action(fn (array $arguments) => response()->download(
                Storage::disk('local')->path($this->service()->pathOf($arguments['name'])), basename($arguments['name'])));
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')->label('حذف')->icon('heroicon-o-trash')->color('danger')->link()
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'حذفِ '.$arguments['name'])
            ->action(function (array $arguments) {
                $this->service()->delete($arguments['name']);
                Notification::make()->title('حذف شد')->success()->send();
            });
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')->label('بازگردانی')->icon('heroicon-o-arrow-uturn-left')->color('warning')->link()
            ->modalHeading(fn (array $arguments) => 'بازگردانیِ '.$arguments['name'])
            ->modalDescription(new HtmlString('<div style="line-height:2">همه‌ی کاربران، خریدها، سفارش‌ها، «مرورِ من»، «مرورِ آماده»، کتاب‌ها، دسته‌ها، تنظیمات و پیام‌های فعلی <b>پاک</b> و با محتوای این پشتیبان جایگزین می‌شوند. پیش از آن خودکار از وضعیتِ فعلی پشتیبان گرفته می‌شود. همه‌ی کاربران (از جمله شما، اگر حسابتان در پشتیبان نباشد) دوباره باید وارد شوند.</div>'))
            ->form([
                TextInput::make('confirm')->label('برای تأیید بنویسید: بازگردانی')
                    ->required()->in(['بازگردانی'])->validationMessages(['in' => 'دقیقاً «بازگردانی» را بنویسید.']),
            ])
            ->action(function (array $arguments) {
                @set_time_limit(0);
                try {
                    $r = $this->service()->restore($arguments['name']);
                    Notification::make()->title('بازگردانی انجام شد')
                        ->body('پشتیبانِ وضعیتِ قبلی: '.$r['safety'].' — '.array_sum($r['tables']).' ردیف، '.$r['files'].' فایل.')
                        ->success()->persistent()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('بازگردانی ناموفق؛ چیزی عوض نشد')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }
}
