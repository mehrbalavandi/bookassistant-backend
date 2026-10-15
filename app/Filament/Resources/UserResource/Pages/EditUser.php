<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UserResource::configureDelete(Actions\Action::make('deleteUser'))
                ->after(fn () => $this->redirect(UserResource::getUrl('index'))),
            Actions\RestoreAction::make()->label('فعال‌سازیِ دوباره'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return self::withDisplayName($data, $this->record->name);
    }

    /** نامِ نمایشی = نام + نام خانوادگی (اگر پر باشند)، وگرنه همان که بود. */
    public static function withDisplayName(array $data, ?string $current = null): array
    {
        $full = trim(trim((string) ($data['first_name'] ?? '')).' '.trim((string) ($data['last_name'] ?? '')));
        if ($full !== '') {
            $data['name'] = $full;
        } elseif (blank($data['name'] ?? null)) {
            $data['name'] = $current ?: ($data['mobile'] ?? $data['email'] ?? 'کاربر');
        }

        return $data;
    }
}
