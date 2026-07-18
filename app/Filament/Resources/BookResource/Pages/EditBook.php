<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use Filament\Actions;
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

            Actions\DeleteAction::make(),
        ];
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
