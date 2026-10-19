<?php

namespace App\Filament\Resources\FeedbackResource\Pages;

use App\Filament\Resources\FeedbackResource;
use App\Models\Feedback;
use App\Services\FeedbackService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditFeedback extends EditRecord
{
    protected static string $resource = FeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /** بازکردنِ پیامِ تازه یعنی «در حالِ بررسی». */
    protected function afterFill(): void
    {
        if ($this->record->status === 'new') {
            $this->record->update(['status' => 'in_progress']);
            $this->data['status'] = 'in_progress';
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Feedback $record */
        app(FeedbackService::class)->reply($record, $data['admin_reply'] ?? null, $data['status'], Auth::user());

        return $record;
    }
}
