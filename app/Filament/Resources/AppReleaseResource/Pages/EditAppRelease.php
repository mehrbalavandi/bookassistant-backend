<?php

namespace App\Filament\Resources\AppReleaseResource\Pages;

use App\Filament\Resources\AppReleaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppRelease extends EditRecord
{
    protected static string $resource = AppReleaseResource::class;


    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
