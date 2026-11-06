<?php

namespace App\Filament\English\Resources\EnglishTeachers\Pages;

use App\Actions\English\Teachers\SaveEnglishTeacher;
use App\Filament\English\Resources\EnglishTeachers\EnglishTeacherResource;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishTeacher;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEnglishTeachers extends ManageRecords
{
    protected static string $resource = EnglishTeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data, Action $action, SaveEnglishTeacher $saveTeacher): EnglishTeacher => Attempt::action(
                    $action,
                    fn (): EnglishTeacher => $saveTeacher->handle($data),
                )),
        ];
    }
}
