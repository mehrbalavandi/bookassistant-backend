<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookCategoryResource\Pages;
use App\Models\BookCategory;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * 🌟 دسته‌های کتاب (مثلاً «Mindset for IELTS»، «Cambridge IELTS»، …).
 * ترتیبِ دسته‌ها در سایت و اپ = «شماره‌ی ردیف» (یکتا)، نه ترتیبِ ساخت.
 * دسته‌ای که هنوز کتاب دارد حذف نمی‌شود؛ اول کتاب‌هایش را منتقل کنید.
 */
class BookCategoryResource extends Resource
{
    protected static ?string $model = BookCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $modelLabel = 'دسته';
    protected static ?string $pluralModelLabel = 'دسته‌های کتاب';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('دسته')->schema([
                Grid::make(3)->schema([
                    TextInput::make('title')
                        ->label('عنوانِ دسته')
                        ->helperText('همین عنوان در سایت و اپ نشان داده می‌شود. مثال: Mindset for IELTS')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    TextInput::make('sort_order')
                        ->label('شماره‌ی ردیف')
                        ->helperText('ترتیبِ دسته‌ها در سایت و اپ؛ یکتا.')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(fn () => BookCategory::nextSortOrder())
                        ->unique(ignoreRecord: true)
                        ->validationMessages([
                            'unique' => 'این شماره‌ی ردیف قبلاً به دسته‌ی دیگری داده شده.',
                        ]),
                ]),

                Textarea::make('description')
                    ->label('توضیح (اختیاری)')
                    ->helperText('زیرِ عنوانِ دسته در سایت و اپ نشان داده می‌شود.')
                    ->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('ردیف')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('عنوانِ دسته')->searchable(),
                Tables\Columns\TextColumn::make('books_count')->label('تعدادِ کتاب')->counts('books'),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین تغییر')->dateTime('Y-m-d'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // حذف فقط برای دسته‌ی خالی (کلیدِ خارجیِ books هم restrict است).
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (BookCategory $record) => $record->books()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookCategories::route('/'),
            'create' => Pages\CreateBookCategory::route('/create'),
            'edit' => Pages\EditBookCategory::route('/{record}/edit'),
        ];
    }
}
