<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
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
                $this->manageAssetsAction('main',   'audio',  'صوت‌های اصلی'),
                $this->manageAssetsAction('main',   'images', 'تصاویرِ اصلی'),
                $this->manageAssetsAction('sample', 'audio',  'صوت‌های نمونه'),
                $this->manageAssetsAction('sample', 'images', 'تصاویرِ نمونه'),
            ])->label('مدیریت فایل‌ها')->icon('heroicon-o-folder-open')->button(),

            Actions\DeleteAction::make(),
        ];
    }

    /**
     * فهرستِ فایل‌های یک نوع را به‌صورت چک‌باکس نشان می‌دهد (مشاهده) و اجازه‌ی
     * حذفِ فایل‌های انتخاب‌شده را می‌دهد (حذفِ تکی/چندتایی). «ویرایش» = حذف + آپلودِ مجدد.
     */
    private function manageAssetsAction(string $scope, string $kind, string $label): Actions\Action
    {
        return Actions\Action::make("manage_{$scope}_{$kind}")
            ->label($label)
            ->modalHeading("مدیریت $label")
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
            ->action(function (array $data) use ($scope, $kind, $label) {
                $selected = $data['files'] ?? [];
                if (empty($selected)) {
                    return;
                }
                Storage::disk('local')->delete($selected);

                // از آرایه‌ی متناظر در DB هم حذف کن
                $col = match ([$scope, $kind]) {
                    ['main', 'audio']    => 'audio_files',
                    ['main', 'images']   => 'images',
                    ['sample', 'audio']  => 'sample_audio_files',
                    ['sample', 'images'] => 'sample_images',
                };
                $remaining = array_values(array_diff($this->record->{$col} ?? [], $selected));
                $this->record->update([$col => $remaining]);

                // بامپِ نسخه
                $verCol = $scope === 'sample'
                    ? 'sample_version'
                    : ($kind === 'audio' ? 'audio_version' : 'images_version');
                $this->record->increment($verCol);

                Notification::make()
                    ->title(count($selected) . " فایل از «$label» حذف شد.")
                    ->success()->send();
            });
    }

    private function dir(string $scope, string $kind): string
    {
        return $this->scopeRoot($scope) . '/' . $kind;
    }

    private function scopeRoot(string $scope): string
    {
        $folder = $this->record->folder_name;
        return $scope === 'sample' ? "books/{$folder}/sample" : "books/{$folder}";
    }

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
                Storage::disk('local')->delete($zipRel);

                // 🌟 لیستِ فایل‌های صوت/تصویرِ استخراج‌شده را در DB ثبت کن — بدون این،
                // اپ فهرستِ خالی می‌بیند و هیچ صوت/تصویری دانلود نمی‌کند (بدون هیچ خطایی)
                $audioDir  = $this->scopeRoot($scope) . '/audio';
                $imagesDir = $this->scopeRoot($scope) . '/images';
                $audioFiles = Storage::disk('local')->exists($audioDir)
                    ? Storage::disk('local')->files($audioDir) : [];
                $imageFiles = Storage::disk('local')->exists($imagesDir)
                    ? Storage::disk('local')->files($imagesDir) : [];

                // ثبتِ مسیرِ index.json + فهرستِ صوت/تصویر + بامپِ نسخه‌ها (پرچمِ «محتوا عوض شد»)
                $col       = $scope === 'sample' ? 'sample_file_path'   : 'json_file';
                $audioCol  = $scope === 'sample' ? 'sample_audio_files' : 'audio_files';
                $imagesCol = $scope === 'sample' ? 'sample_images'      : 'images';
                $this->record->update([
                    $col       => $this->scopeRoot($scope) . '/index.json',
                    $audioCol  => $audioFiles,
                    $imagesCol => $imageFiles,
                ]);

                if ($scope === 'sample') {
                    $this->record->increment('sample_version');
                } else {
                    $this->record->increment('json_version');
                    $this->record->increment('audio_version');
                    $this->record->increment('images_version');
                }

                Notification::make()
                    ->title("محتوای «" . ($scope === 'sample' ? 'نمونه' : 'اصلی') . "» با موفقیت استخراج شد.")
                    ->success()->send();
            });
    }

    private function deleteGroupAction(string $scope, string $kind, string $label): Actions\Action
    {
        return Actions\Action::make("del_{$scope}_{$kind}")
            ->label($label)
            ->color('danger')
            ->requiresConfirmation()
            ->action(function () use ($scope, $kind, $label) {
                Storage::disk('local')->deleteDirectory($this->scopeRoot($scope) . '/' . $kind);

                if ($kind === 'pages') {
                    Storage::disk('local')->delete($this->scopeRoot($scope) . '/index.json');
                    $col = $scope === 'sample' ? 'sample_file_path' : 'json_file';
                    $this->record->update([$col => null]);
                } elseif ($kind === 'audio') {
                    $col = $scope === 'sample' ? 'sample_audio_files' : 'audio_files';
                    $this->record->update([$col => []]);
                } elseif ($kind === 'images') {
                    $col = $scope === 'sample' ? 'sample_images' : 'images';
                    $this->record->update([$col => []]);
                }

                $verCol = $scope === 'sample'
                    ? 'sample_version'
                    : ['pages' => 'json_version', 'audio' => 'audio_version', 'images' => 'images_version'][$kind];
                $this->record->increment($verCol);

                Notification::make()->title("«{$label}» انجام شد.")->success()->send();
            });
    }
}
