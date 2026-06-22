<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Models\Book;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

// ابزارهای فرم فلامنت
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
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
                Section::make('اطلاعات پایه کتاب')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title')
                                ->label('عنوان کتاب (مثلاً IELTS Cambridge 1)')
                                ->required(),

                            TextInput::make('folder_name')
                                ->label('نام پوشه در سرور (فقط انگلیسی)')
                                ->helperText('مثال: ielts-book-1')
                                ->required()
                                ->live(),
                        ]),
                    ]),

                Section::make('مدیریت فایل‌ها و نسخه‌گذاری (Granulation)')
                    ->schema([
                        // ۱. بخش فایل نمونه
                        Grid::make(3)->schema([
                            FileUpload::make('sample_file_path')
                                ->label('فایل نمونه (دمو)')
                                ->disk('local')
                                ->directory(fn(Get $get) => 'books/' . $get('folder_name'))
                                ->columnSpan(2),
                            TextInput::make('sample_version')
                                ->label('نسخه نمونه')
                                ->numeric()
                                ->default(1)
                                ->required(),
                        ]),

                        // ۲. بخش فایل ساختار محتوا
                        Grid::make(3)->schema([
                            FileUpload::make('json_file')
                                ->label('فایل ساختار JSON')
                                ->disk('local')
                                ->acceptedFileTypes(['application/json'])
                                ->directory(fn(Get $get) => 'books/' . $get('folder_name'))
                                ->columnSpan(2),
                            TextInput::make('json_version')
                                ->label('نسخه JSON')
                                ->numeric()
                                ->default(1)
                                ->required(),
                        ]),

                        // ۳. بخش فایل‌های صوتی
                        Grid::make(3)->schema([
                            FileUpload::make('audio_files')
                                ->label('فایل‌های صوتی کتاب')
                                ->multiple()
                                ->disk('local')
                                ->directory(fn(Get $get) => 'books/' . $get('folder_name') . '/audio')
                                ->columnSpan(2),
                            TextInput::make('audio_version')
                                ->label('نسخه صوت‌ها')
                                ->numeric()
                                ->default(1)
                                ->required(),
                        ]),

                        // ۴. بخش تصاویر
                        Grid::make(3)->schema([
                            FileUpload::make('images')
                                ->label('تصاویر کتاب')
                                ->multiple()
                                ->image()
                                ->disk('local')
                                ->directory(fn(Get $get) => 'books/' . $get('folder_name') . '/images')
                                ->columnSpan(2),
                            TextInput::make('images_version')
                                ->label('نسخه تصاویر')
                                ->numeric()
                                ->default(1)
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان کتاب')->searchable(),
                Tables\Columns\TextColumn::make('folder_name')->label('نام پوشه'),
                Tables\Columns\TextColumn::make('json_version')->label('نسخه دیتا'),
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
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
