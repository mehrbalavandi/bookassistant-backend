<?php

namespace App\Filament\Resources\SharedStudyItemResource\Pages;

use App\Filament\Resources\SharedStudyItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSharedStudyItem extends EditRecord
{
    protected static string $resource = SharedStudyItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
