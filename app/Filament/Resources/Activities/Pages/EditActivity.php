<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Actions\Activities\DeleteActivity;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\Concerns\SavesActivity;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Activity;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditActivity extends EditRecord
{
    use SavesActivity;

    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalHeading('حذف النشاط')
                ->modalDescription(fn (Activity $record): string => ActivitiesTable::deletionWarning($record))
                ->using(function (Activity $record, DeleteActivity $deleteActivity): bool {
                    $deleteActivity->handle($record);

                    return true;
                }),
        ];
    }

    /**
     * What the form shows and the table of activities does not hold: who
     * the activity is for, the course that leads to its circle, and the
     * students who attended.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Activity $activity */
        $activity = $this->getRecord();

        $data['audience'] = $activity->audience()->value;
        $data['course_id'] = $activity->circle?->course_id;
        $data['enrollment_ids'] = $activity->attendances()->pluck('enrollment_id')->all();

        return $data;
    }

    /**
     * @param  Activity  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveActivity($data, $record);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تم حفظ النشاط';
    }
}
