<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Actions\Finance\SaveDepartment;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Support\Attempt;
use App\Models\Department;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDepartments extends ManageRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data, Action $action, SaveDepartment $saveDepartment): Department => Attempt::action(
                    $action,
                    fn (): Department => $saveDepartment->handle($data),
                )),
        ];
    }
}
