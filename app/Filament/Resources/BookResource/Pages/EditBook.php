<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use App\Http\Controllers\Api\BookController;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditBook extends EditRecord
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── آپلودِ گروهیِ محتوا (خروجیِ اکسترکتور، به‌صورت ZIP) ──
            Actions\ActionGroup::make([
                $this->uploadZipAction('main',   'آپلود محتوای اصلی (ZIP)'),
                $this->uploadZipAction('sample', 'آپلود محتوای نمونه (ZIP)'),
            ])->label('آپلود محتوا')->icon('heroicon-o-arrow-up-tray')->button(),

            // ── حذفِ گروهی ──
            Actions\ActionGroup::make([
                $this->deleteGroupAction('main',   'pages',  'حذف همه صفحاتِ اصلی'),
                $this->deleteGroupAction('main',   'audio',  'حذف همه صوت‌های اصلی'),
                $this->deleteGroupAction('main',   'images', 'حذف همه تصاویرِ اصلی'),
                $this->deleteGroupAction('sample', 'pages',  'حذف همه صفحاتِ نمونه'),
                $this->deleteGroupAction('sample', 'audio',  'حذف همه صوت‌های نمونه'),
                $this->deleteGroupAction('sample', 'images', 'حذف همه تصاویرِ نمونه'),
            ])->label('حذف گروهی')->icon('heroicon-o-trash')->color('danger')->button(),

            // ── مدیریتِ تکیِ فایل‌های صوت/تصویر (مشاهده + حذفِ فایل‌های انتخابی) ──
            Actions\ActionGroup::make([
                $this->manageAssetsAction('main',   'audio'),
                $this->manageAssetsAction('main',   'images'),
                $this->manageAssetsAction('sample', 'audio'),
                $this->manageAssetsAction('sample', 'images'),
            ])->label('مدیریت فایل‌ها')->icon('heroicon-o-folder-open')->button(),

            Actions\DeleteAction::make(),
        ];
    }

    // ───────────────────────── helpers ─────────────────────────

    private function scopeRoot(string $scope): string
    {
        $folder = $this->record->folder_name;
        return $scope === 'sample' ? "books/{$folder}/sample" : "books/{$folder}";
    }

    private function dir(string $scope, string $kind): string
    {
        return $this->scopeRoot($scope) . '/' . $kind;
    }

    // برچسبِ فارسیِ یک ترکیبِ scope+kind — یک‌جا تعریف شده تا همه‌جا از همین استفاده شود
    private function assetLabel(string $scope, string $kind): string
    {
        $scopeFa = $scope === 'sample' ? 'نمونه' : 'اصلی';
        $kindFa = match ($kind) {
            'pages'  => 'صفحات',
            'audio'  => 'صوت‌ها',
            'images' => 'تصاویر',
            default  => $kind,
        };
        return "{$kindFa}ِ {$scopeFa}";
    }

    private function columnFor(string $scope, string $kind): string
    {
        return match ([$scope, $kind]) {
            ['main', 'pages']    => 'json_file',
            ['main', 'audio']    => 'audio_files',
            ['main', 'images']   => 'images',
            ['sample', 'pages']  => 'sample_file_path',
            ['sample', 'audio']  => 'sample_audio_files',
            ['sample', 'images'] => 'sample_images',
        };
    }

    private function versionColumnFor(string $scope, string $kind): string
    {
        if ($scope === 'sample') {
            return 'sample_version';
        }
        return match ($kind) {
            'pages'  => 'json_version',
            'audio'  => 'audio_version',
            'images' => 'images_version',
        };
    }

    // ───────────────────────── آپلودِ ZIP ─────────────────────────

    private function uploadZipAction(string $scope, string $label): Actions\Action
    {
        return Actions\Action::make("upload_{$scope}")
            ->label($label)
            ->icon('heroicon-o-arrow-up-tray')
            ->color($scope === 'sample' ? 'warning' : 'primary')
            ->form([
                FileUpload::make('zip')
                    ->label('فایل ZIP (شاملِ index.json و پوشه‌های pages/ , audio/ , images/)')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'])
                    ->disk('local')->directory('tmp')->preserveFilenames()
                    ->required(),
            ])
            ->action(function (array $data) use ($scope) {
                $zipRel = $data['zip'];
                $zipAbs = Storage::disk('local')->path($zipRel);
                $targetAbs = Storage::disk('local')->path($this->scopeRoot($scope));

                if (! is_dir($targetAbs)) {
                    mkdir($targetAbs, 0775, true);
                }

                $zip = new \ZipArchive();
                if ($zip->open($zipAbs) !== true) {
                    Storage::disk('local')->delete($zipRel);
                    Notification::make()->title('باز کردنِ فایل ZIP ناموفق بود.')->danger()->send();
                    return;
                }

                $zip->extractTo($targetAbs);
                $zip->close();
                // 🌟 فایلِ ZIP عمداً این‌جا پاک نمی‌شود — پایین‌تر به‌عنوانِ
                // آرشیوِ آماده‌ی دانلود به پوشه‌ی archive منتقل می‌شود.

                // 🌟 لیستِ فایل‌های صوت/تصویرِ استخراج‌شده را در DB ثبت کن — بدون این،
                // اپ فهرستِ خالی می‌بیند و هیچ صوت/تصویری دانلود نمی‌کند (بدون هیچ خطایی)
                $audioDir  = $this->scopeRoot($scope) . '/audio';
                $imagesDir = $this->scopeRoot($scope) . '/images';
                $audioFiles = Storage::disk('local')->exists($audioDir)
                    ? Storage::disk('local')->files($audioDir) : [];
                $imageFiles = Storage::disk('local')->exists($imagesDir)
                    ? Storage::disk('local')->files($imagesDir) : [];

                $this->record->update([
                    $this->columnFor($scope, 'pages')  => $this->scopeRoot($scope) . '/index.json',
                    $this->columnFor($scope, 'audio')  => $audioFiles,
                    $this->columnFor($scope, 'images') => $imageFiles,
                ]);

                if ($scope === 'sample') {
                    $this->record->increment('sample_version');
                } else {
                    $this->record->increment('json_version');
                    $this->record->increment('audio_version');
                    $this->record->increment('images_version');
                }

                // 🌟 به‌جای دور انداختنِ فایلِ ZIPِ آپلودشده، همان را به‌عنوانِ
                // آرشیوِ آماده‌ی دانلود نگه می‌داریم — پس سرور هیچ‌وقت لازم
                // نیست دوباره از رویِ فایل‌های روی دیسک آرشیو بسازد.
                // نام دقیقاً همانی است که BookController هنگام دانلود دنبالش
                // می‌گردد؛ چون بعد از increment اجرا می‌شود، شماره‌نسخه‌های
                // تازه در نام می‌آیند. refresh لازم است تا مقادیرِ increment‌شده
                // از دیتابیس خوانده شوند، نه مقادیرِ قدیمیِ داخلِ آبجکت.
                $this->record->refresh();
                $archiveRel = BookController::zipRelativePath($this->record, $scope);
                Storage::disk('local')->makeDirectory(dirname($archiveRel));
                if (Storage::disk('local')->exists($archiveRel)) {
                    Storage::disk('local')->delete($archiveRel);
                }
                Storage::disk('local')->move($zipRel, $archiveRel);
                BookController::pruneOldZips($this->record, $scope, $archiveRel);

                Notification::make()
                    ->title('محتوای «' . ($scope === 'sample' ? 'نمونه' : 'اصلی') . '» با موفقیت استخراج شد.')
                    ->success()->send();
            });
    }

    // ───────────────────────── حذفِ گروهی ─────────────────────────

    private function deleteGroupAction(string $scope, string $kind, string $label): Actions\Action
    {
        return Actions\Action::make("del_{$scope}_{$kind}")
            ->label($label)
            ->color('danger')
            ->requiresConfirmation()
            ->action(function () use ($scope, $kind) {
                Storage::disk('local')->deleteDirectory($this->dir($scope, $kind));

                $col = $this->columnFor($scope, $kind);
                if ($kind === 'pages') {
                    Storage::disk('local')->delete($this->scopeRoot($scope) . '/index.json');
                    $this->record->update([$col => null]);
                } else {
                    $this->record->update([$col => []]);
                }

                $this->record->increment($this->versionColumnFor($scope, $kind));

                Notification::make()
                    ->title('«' . $this->assetLabel($scope, $kind) . '» حذف شد.')
                    ->success()->send();
            });
    }

    // ───────────────────────── مدیریتِ تکیِ فایل‌ها ─────────────────────────

    /**
     * فهرستِ فایل‌های یک نوع را به‌صورت چک‌باکس نشان می‌دهد (مشاهده) و اجازه‌ی
     * حذفِ فایل‌های انتخاب‌شده را می‌دهد (حذفِ تکی/چندتایی). «ویرایش» = حذف + آپلودِ مجدد.
     */
    private function manageAssetsAction(string $scope, string $kind): Actions\Action
    {
        return Actions\Action::make("manage_{$scope}_{$kind}")
            ->label($this->assetLabel($scope, $kind))
            ->modalHeading('مدیریت ' . $this->assetLabel($scope, $kind))
            ->modalSubmitActionLabel('حذفِ انتخاب‌شده‌ها')
            ->form([
                CheckboxList::make('files')
                    ->label('برای حذف، فایل‌ها را انتخاب کنید')
                    ->options(function () use ($scope, $kind) {
                        $dir = $this->dir($scope, $kind);
                        $files = Storage::disk('local')->exists($dir)
                            ? Storage::disk('local')->files($dir)
                            : [];
                        // کلید = مسیرِ کامل، نمایش = فقط نامِ فایل
                        return collect($files)
                            ->mapWithKeys(fn($p) => [$p => basename($p)])
                            ->all();
                    })
                    ->bulkToggleable()
                    ->noSearchResultsMessage('فایلی موجود نیست.'),
            ])
            ->action(function (array $data) use ($scope, $kind) {
                $selected = $data['files'] ?? [];
                if (empty($selected)) {
                    return;
                }
                Storage::disk('local')->delete($selected);

                $col = $this->columnFor($scope, $kind);
                $remaining = array_values(array_diff($this->record->{$col} ?? [], $selected));
                $this->record->update([$col => $remaining]);

                $this->record->increment($this->versionColumnFor($scope, $kind));

                Notification::make()
                    ->title(count($selected) . ' فایل از «' . $this->assetLabel($scope, $kind) . '» حذف شد.')
                    ->success()->send();
            });
    }
}
