<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentResource\Pages;
use App\Filament\Resources\ContentResource\RelationManagers;
use App\Models\Content;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContentResource extends Resource
{
    protected static ?string $model = Content::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

public static function form(Form $form): Form
{
    return $form
        ->schema([
            // کادر ورود عنوان
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->label('عنوان درس / محتوا'),

            // کادر ورود نسخه (که برای سینک شدن فلاتر حیاتیه)
            Forms\Components\TextInput::make('version')
                ->numeric()
                ->default(1)
                ->required()
                ->label('نسخه فایل'),

            // کادر آپلود فایل صوتی به صورت کاملاً امن و رمزگذاری شده در پوشه خصوصی
            Forms\Components\FileUpload::make('audio_filename')
                ->required()
                ->disk('local') 
                ->directory('private_audios') // ذخیره در مسیر امنی که در فاز اول ساختیم
                ->visibility('private')
                ->label('فایل صوتی پادکست'),

            // کادر بزرگ برای وارد کردن متن یا ساختار داده مربوط به درس
            Forms\Components\Textarea::make('text_content')
                ->required()
                ->rows(10)
                ->label('متن آموزشی یا ساختار متنی درس'),
        ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // ستون‌هایی که در لیست پنل مدیریت نمایش داده می‌شوند
            Tables\Columns\TextColumn::make('id')->label('شناسه')->sortable(),
            Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
            Tables\Columns\TextColumn::make('version')->label('نسخه فعلی'),
            Tables\Columns\TextColumn::make('created_at')->label('تاریخ ایجاد')->dateTime('Y/m/d'),
        ])
        ->filters([
            // فیلترها (فعلاً خالی)
        ])
        ->actions([
            Tables\Actions\EditAction::make(), // دکمه ویرایش
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(), // دکمه حذف دسته‌جمعی
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
            'index' => Pages\ListContents::route('/'),
            'create' => Pages\CreateContent::route('/create'),
            'edit' => Pages\EditContent::route('/{record}/edit'),
        ];
    }
}
