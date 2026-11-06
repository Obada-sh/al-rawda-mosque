<?php

namespace App\Filament\English\Resources\EnglishDepartments\Pages;

use App\Actions\English\Finance\SaveEnglishDepartment;
use App\Filament\English\Resources\EnglishDepartments\EnglishDepartmentResource;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishDepartment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEnglishDepartments extends ManageRecords
{
    protected static string $resource = EnglishDepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data, Action $action, SaveEnglishDepartment $saveDepartment): EnglishDepartment => Attempt::action(
                    $action,
                    fn (): EnglishDepartment => $saveDepartment->handle($data),
                )),
        ];
    }
}
