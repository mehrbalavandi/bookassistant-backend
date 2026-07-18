<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Models\Book;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Facades\Storage;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'کتاب';
    protected static ?string $pluralModelLabel = 'کتاب‌ها';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('اطلاعات پایه کتاب')->schema([
                Grid::make(2)->schema([
                    TextInput::make('title')
                        ->label('عنوان کتاب')->required(),

                    TextInput::make('folder_name')
                        ->label('نام پوشه در سرور (فقط انگلیسی)')
                        ->helperText('مثال: ielts-book-1')
                        ->required()->live(),

                    TextInput::make('price')
                        ->label('قیمت (تومان)')->numeric()->required()->default(0)
                        ->helperText('اگر رایگان است، 0'),

                    TextInput::make('discount')
                        ->label('درصد تخفیف')->numeric()->default(0)
                        ->minValue(0)->maxValue(100),
                ]),
            ]),

            Section::make('نسخه‌ها')
                ->description('این اعداد پرچمِ «محتوا عوض شد» برای اپ هستند و هنگام آپلود/حذفِ گروهی خودکار بالا می‌روند.')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('json_version')->label('نسخهٔ دیتای اصلی')->numeric()->default(1),
                        TextInput::make('audio_version')->label('نسخهٔ صوت اصلی')->numeric()->default(1),
                        TextInput::make('images_version')->label('نسخهٔ تصاویر اصلی')->numeric()->default(1),
                        TextInput::make('sample_version')->label('نسخهٔ نمونه')->numeric()->default(1),
                    ]),
                ]),

            // وضعیتِ محتوا؛ آپلود/حذف از دکمه‌های بالای صفحهٔ ویرایش انجام می‌شود
            Section::make('وضعیت محتوا')
                ->visibleOn('edit')
                ->description('برای آپلود یا حذفِ گروهیِ محتوا، از دکمه‌های «آپلود محتوا» و «حذف گروهی» در بالای همین صفحه استفاده کنید.')
                ->schema([
                    Placeholder::make('main_status')
                        ->label('🔵 محتوای اصلی (کامل)')
                        ->content(fn($record) => static::statusText($record, 'main')),

                    Placeholder::make('sample_status')
                        ->label('🟠 محتوای نمونه (دمو رایگان)')
                        ->content(fn($record) => static::statusText($record, 'sample')),
                ]),
        ]);
    }

    protected static function statusText($record, string $scope): string
    {
        if (! $record) {
            return '—';
        }
        $root = $scope === 'sample'
            ? "books/{$record->folder_name}/sample"
            : "books/{$record->folder_name}";

        $pages  = count(Storage::disk('local')->files("$root/pages"));
        $audio  = count(Storage::disk('local')->files("$root/audio"));
        $images = count(Storage::disk('local')->files("$root/images"));
        $idx    = Storage::disk('local')->exists("$root/index.json") ? '✓ موجود' : '— ندارد';

        return "index.json: {$idx}  |  صفحات: {$pages}  |  صوت: {$audio}  |  تصویر: {$images}";
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان کتاب')->searchable(),
                Tables\Columns\TextColumn::make('folder_name')->label('نام پوشه'),
                Tables\Columns\TextColumn::make('json_version')->label('نسخه دیتا'),
                Tables\Columns\TextColumn::make('images_version')->label('نسخه تصاویر'),
                Tables\Columns\TextColumn::make('audio_version')->label('نسخه صوت'),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ ثبت')->dateTime('Y-m-d'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit'   => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
