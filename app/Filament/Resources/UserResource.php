<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 🌟 BK-4: فهرستِ کاربران (فقط خواندنی) — موبایل، آخرین بازدید، تعدادِ خرید، تعدادِ
 * موردهای «مرورِ من» و آخرین نسخه‌ی اپ. برای پشتیبانی و تصمیم‌گیری، نه ویرایش.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $modelLabel = 'کاربر';
    protected static ?string $pluralModelLabel = 'کاربران';
    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['purchasedBooks', 'studyItems' => fn ($s) => $s->where('deleted', false), 'devices'])
                ->with(['devices' => fn ($d) => $d->latest('last_seen_at')]))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('mobile')->label('موبایل')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('email')->label('ایمیل')->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_admin')->label('ادمین')->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('purchased_books_count')->label('خرید')->sortable(),
                Tables\Columns\TextColumn::make('study_items_count')->label('مرور')->sortable(),
                Tables\Columns\TextColumn::make('devices_count')->label('دستگاه'),
                Tables\Columns\TextColumn::make('app_version')->label('نسخه‌ی اپ')
                    ->state(fn (User $u) => ($d = $u->devices->first()) ? trim($d->platform.' '.$d->app_version) : null),
                Tables\Columns\TextColumn::make('last_seen_at')->label('آخرین بازدید')->since()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('عضویت')->date()->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('buyers')->label('فقط خریداران')
                    ->query(fn (Builder $query) => $query->has('purchasedBooks')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUsers::route('/')];
    }
}
