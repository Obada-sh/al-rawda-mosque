<?php

namespace App\Filament\Resources\Recitations\Pages;

use App\Actions\Recitations\RecordRecitation;
use App\Filament\Resources\Recitations\RecitationResource;
use App\Filament\Support\Attempt;
use App\Models\Recitation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRecitations extends ManageRecords
{
    protected static string $resource = RecitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('تسجيل تسميع')
                ->modalHeading('تسجيل تسميع')
                // Recitations are entered one after another: the teacher and
                // the date stay as they were for the next student.
                ->preserveFormDataWhenCreatingAnother(['teacher_id', 'recited_on'])
                ->using(fn (array $data, Action $action, RecordRecitation $recordRecitation): Recitation => Attempt::action(
                    $action,
                    fn (): Recitation => $recordRecitation->handle($data),
                ))
                ->successNotificationTitle('تم تسجيل التسميع'),
        ];
    }
}
