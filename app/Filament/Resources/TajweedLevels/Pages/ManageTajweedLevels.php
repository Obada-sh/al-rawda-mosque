<?php

namespace App\Filament\Resources\TajweedLevels\Pages;

use App\Actions\TajweedLevels\SaveTajweedLevel;
use App\Filament\Resources\TajweedLevels\TajweedLevelResource;
use App\Filament\Support\Attempt;
use App\Models\TajweedLevel;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTajweedLevels extends ManageRecords
{
    protected static string $resource = TajweedLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data, Action $action, SaveTajweedLevel $saveTajweedLevel): TajweedLevel => Attempt::action(
                    $action,
                    fn (): TajweedLevel => $saveTajweedLevel->handle($data),
                )),
        ];
    }
}
