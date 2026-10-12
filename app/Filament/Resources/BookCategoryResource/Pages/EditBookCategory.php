<?php

namespace App\Filament\Resources\BookCategoryResource\Pages;

use App\Filament\Resources\BookCategoryResource;
use App\Models\BookCategory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBookCategory extends EditRecord
{
    protected static string $resource = BookCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // حذف فقط برای دسته‌ی خالی.
            Actions\DeleteAction::make()
                ->hidden(fn (BookCategory $record) => $record->books()->exists()),
        ];
    }
}
