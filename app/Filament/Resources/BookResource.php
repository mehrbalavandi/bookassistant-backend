<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Filament\Resources\BookResource\RelationManagers;
use App\Models\Book;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Get; // این کلاس برای خواندن زنده اطلاعات فرم استفاده می‌شود

class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('عنوان کتاب (مثلاً IELTS Cambridge 1)')
                    ->required(),

                TextInput::make('folder_name')
                    ->label('نام پوشه در سرور (فقط انگلیسی)')
                    ->helperText('مثال: ielts-book-1 (بدون فاصله)')
                    ->required()
                    ->live(), // با تایپ شما، سیستم نام پوشه را درک می‌کند

                FileUpload::make('json_file')
                    ->label('فایل ساختار JSON (حاوی ترجمه‌ها و محتوا)')
                    ->acceptedFileTypes(['application/json'])
                    // ذخیره در: books/ielts-book-1/
                    ->directory(fn (Get $get) => 'books/' . $get('folder_name')),

                FileUpload::make('audio_files')
                    ->label('فایل‌های صوتی')
                    ->multiple() // قابلیت انتخاب همزمان چند فایل
                    ->acceptedFileTypes(['audio/*'])
                    // ذخیره در: books/ielts-book-1/audio/
                    ->directory(fn (Get $get) => 'books/' . $get('folder_name') . '/audio'),

                FileUpload::make('images')
                    ->label('تصاویر کتاب')
                    ->multiple() // قابلیت انتخاب همزمان چند فایل
                    ->image()
                    // ذخیره در: books/ielts-book-1/images/
                    ->directory(fn (Get $get) => 'books/' . $get('folder_name') . '/images'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
