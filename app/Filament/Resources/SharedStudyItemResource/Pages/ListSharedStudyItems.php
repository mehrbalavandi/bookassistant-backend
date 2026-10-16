<?php

namespace App\Filament\Resources\SharedStudyItemResource\Pages;

use App\Filament\Resources\SharedStudyItemResource;
use App\Models\SharedStudyItem;
use App\Support\StudyItemFormat;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * 🌟 ST-7: فهرستِ «مرورِ آماده» + دو کنشِ بالای صفحه:
 * - «پرامپتِ هوش مصنوعی»: متنی که با مطلبِ کانال/سؤالتان به ChatGPT، Claude، … می‌دهید.
 * - «واردکردن از هوش مصنوعی»: خروجیِ JSONِ همان پرامپت (چسباندن یا فایل) ← ساخت/به‌روزرسانیِ موردها.
 */
class ListSharedStudyItems extends ListRecords
{
    protected static string $resource = SharedStudyItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('prompt')
                ->label('پرامپتِ هوش مصنوعی')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->modalHeading('پرامپت برای ساختنِ موردهای مرور')
                ->modalDescription('این متن را کپی کنید، در انتهایش (جای «paste your text here») مطلبِ کانال یا سؤالتان را بگذارید و به هوش مصنوعی بدهید. خروجیِ JSON را با «واردکردن از هوش مصنوعی» وارد کنید.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('بستن')
                ->form([
                    Radio::make('langs')->label('زبانِ توضیح‌ها')->inline()->live()->default('both')
                        ->options(['both' => 'فارسی و عربی', 'fa' => 'فقط فارسی', 'ar' => 'فقط عربی']),
                    Placeholder::make('text')->label('')
                        ->content(fn (Get $get) => new HtmlString(
                            '<pre dir="ltr" style="white-space:pre-wrap;font-size:12px;max-height:55vh;overflow:auto;'
                            .'padding:12px;border-radius:8px;background:rgba(127,127,127,.12);user-select:all">'
                            .e(StudyItemFormat::prompt($get('langs') ?? 'both')).'</pre>'
                        )),
                ]),

            Actions\Action::make('import')
                ->label('واردکردن از هوش مصنوعی')
                ->icon('heroicon-o-arrow-down-tray')
                ->modalHeading('واردکردنِ موردهای مرور (JSON)')
                ->modalSubmitActionLabel('وارد کن')
                ->form([
                    Textarea::make('json')->label('خروجیِ هوش مصنوعی')->rows(14)
                        ->extraInputAttributes(['dir' => 'ltr', 'style' => 'font-family:monospace;font-size:12px'])
                        ->helperText('همان JSON را بچسبانید (با یا بدونِ ```json). موردی که همان نوع و متن را دارد به‌روز می‌شود، نه تکرار.'),
                    FileUpload::make('file')->label('یا فایلِ .json')
                        ->disk('local')->directory('tmp-imports')
                        ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])
                        ->maxSize(2048),
                    Toggle::make('publish')->label('بلافاصله منتشر شود')->default(true),
                ])
                ->action(function (array $data) {
                    $json = trim((string) ($data['json'] ?? ''));
                    if (! empty($data['file'])) {
                        $json = (string) Storage::disk('local')->get($data['file']);
                        Storage::disk('local')->delete($data['file']);
                    }
                    if ($json === '') {
                        Notification::make()->title('متنی برای واردکردن نیست.')->warning()->send();

                        return;
                    }
                    $r = SharedStudyItem::import($json, (bool) ($data['publish'] ?? true));
                    $n = Notification::make()
                        ->title("{$r['added']} موردِ تازه، {$r['updated']} به‌روزرسانی")
                        ->body($r['errors'] ? 'رد شده: '.implode(' | ', array_slice($r['errors'], 0, 5)) : null);
                    ($r['added'] + $r['updated'] > 0 ? $n->success() : $n->danger())->persistent()->send();
                }),

            Actions\CreateAction::make()->label('موردِ تازه'),
        ];
    }
}
