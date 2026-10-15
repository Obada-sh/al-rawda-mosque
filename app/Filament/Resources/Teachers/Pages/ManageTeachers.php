<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Actions\Teachers\SaveTeacher;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Support\Attempt;
use App\Filament\Support\Cards\CardPrintActions;
use App\Models\Teacher;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTeachers extends ManageRecords
{
    protected static string $resource = TeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CardPrintActions::chooseTeachersCourse(),
            CreateAction::make()
                ->using(fn (array $data, Action $action, SaveTeacher $saveTeacher): Teacher => Attempt::action(
                    $action,
                    fn (): Teacher => $saveTeacher->handle($data),
                )),
        ];
    }
}
