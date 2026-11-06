<?php

namespace App\Filament\English\Resources\EnglishLevels\Pages;

use App\Actions\English\Levels\SaveEnglishLevel;
use App\Filament\English\Resources\EnglishLevels\EnglishLevelResource;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishLevel;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEnglishLevels extends ManageRecords
{
    protected static string $resource = EnglishLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data, Action $action, SaveEnglishLevel $saveLevel): EnglishLevel => Attempt::action(
                    $action,
                    fn (): EnglishLevel => $saveLevel->handle($data),
                )),
        ];
    }
}
