<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Models\Book;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

// ابزارهای فرم
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Get;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'کتاب';
    protected static ?string $pluralModelLabel = 'کتاب‌ها';

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
                    ->live(), // خواندن زنده نام پوشه برای مسیردهی فایل‌ها

                FileUpload::make('sample_file_path')
                    ->label('فایل نمونه (نسخه دمو برای کاربران رایگان)')
                    ->disk('local') // 🔒 ذخیره در پوشه امن (storage/app)
                    ->acceptedFileTypes(['application/pdf', 'audio/*', 'application/zip'])
                    ->directory(fn (Get $get) => 'books/' . $get('folder_name')),

                FileUpload::make('json_file')
                    ->label('فایل ساختار JSON (ترجمه‌ها و محتوا)')
                    ->disk('local') // 🔒 
                    ->acceptedFileTypes(['application/json'])
                    ->directory(fn (Get $get) => 'books/' . $get('folder_name')),

                FileUpload::make('audio_files')
                    ->label('فایل‌های صوتی')
                    ->multiple()
                    ->disk('local') // 🔒 
                    ->acceptedFileTypes(['audio/*'])
                    ->directory(fn (Get $get) => 'books/' . $get('folder_name') . '/audio'),

                FileUpload::make('images')
                    ->label('تصاویر کتاب')
                    ->multiple()
                    ->image()
                    ->disk('local') // 🔒 
                    ->directory(fn (Get $get) => 'books/' . $get('folder_name') . '/images'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان کتاب')->searchable(),
                Tables\Columns\TextColumn::make('folder_name')->label('نام پوشه'),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ ثبت')->dateTime('Y-m-d')->sortable(),
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