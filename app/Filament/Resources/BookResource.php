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
                            // 🌟 اضافه شدن فیلد قیمت
                            TextInput::make('price')
                                ->label('قیمت اصلی (تومان)')
                                ->numeric()
                                ->required()
                                ->default(0)
                                ->helperText('اگر رایگان است، 0 وارد کنید'),

                            // 🌟 اضافه شدن فیلد درصد تخفیف
                            TextInput::make('discount')
                                ->label('درصد تخفیف')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(100)
                                ->helperText('عددی بین 0 تا 100'),
                        ]),
                    ]),

                Section::make('مدیریت فایل‌ها و نسخه‌گذاری (Granulation)')
                    ->schema([
                        // بخش فایل نمونه PDF یا زیپ کلی و نسخه آن
                        Grid::make(3)->schema([
                            FileUpload::make('sample_file_path')
                                ->label('فایل دمو کلی (PDF یا Zip)')
                                ->disk('local')
                                ->directory(fn(Get $get) => 'books/' . $get('folder_name') . '/samples')
                                ->preserveFilenames(),

                            TextInput::make('sample_version')
                                ->label('نسخه اجزای نمونه')
                                ->numeric()
                                ->default(1)
                                ->required(),
                        ]),

                        // 🆕 جدید: بخش آپلود فایل‌های صوتی نمونه (مثلاً فقط فصل اول)
                        Grid::make(1)->schema([
                            FileUpload::make('sample_audio_files')
                                ->label('فایل‌های صوتی نمونه (فصل اول)')
                                ->multiple()
                                ->disk('local')
                                ->directory(fn(Get $get) => 'books/' . $get('folder_name') . '/samples/audio')
                                ->preserveFilenames(),
                        ]),

                        // 🆕 جدید: بخش آپلود تصاویر نمونه (مثلاً تصاویر فصل اول)
                        Grid::make(1)->schema([
                            FileUpload::make('sample_images')
                                ->label('تصاویر نمونه (فصل اول)')
                                ->multiple()
                                ->image()
                                ->disk('local')
                                ->directory(fn(Get $get) => 'books/' . $get('folder_name') . '/samples/images')
                                ->preserveFilenames(),
                        ]),

                        // بخش فایل‌های اصلی و پولی (بدون تغییر نسبت به قبل)
                        Grid::make(1)->schema([
                            FileUpload::make('json_file')
                                ->label('فایل ساختار JSON اصلی')
                                ->disk('local')
                                ->acceptedFileTypes(['application/json'])
                                ->directory(fn(Get $get) => 'books/' . $get('folder_name'))
                                ->preserveFilenames(),
                        ]),

                        Grid::make(2)->schema([
                            FileUpload::make('audio_files')
                                ->label('فایل‌های صوتی اصلی (کل کتاب)')
                                ->multiple()
                                ->disk('local')
                                ->directory(fn(Get $get) => 'books/' . $get('folder_name') . '/audio')
                                ->preserveFilenames(),

                            TextInput::make('audio_version')
                                ->label('نسخه صوت‌های اصلی')
                                ->numeric()
                                ->default(1)
                                ->required(),
                        ]),

                        Grid::make(2)->schema([
                            FileUpload::make('images')
                                ->label('تصاویر اصلی (کل کتاب)')
                                ->multiple()
                                ->image()
                                ->disk('local')
                                ->directory(fn(Get $get) => 'books/' . $get('folder_name') . '/images')
                                ->preserveFilenames(),

                            TextInput::make('images_version')
                                ->label('نسخه تصاویر اصلی')
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
