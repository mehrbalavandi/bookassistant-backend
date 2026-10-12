<?php

namespace App\Filament\Resources\AppReleaseResource\Pages;

use App\Filament\Resources\AppReleaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppReleases extends ListRecords
{
    protected static string $resource = AppReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('انتشارِ نسخه‌ی تازه')];
    }

}
