<?php

namespace App\Filament\English\Resources\EnglishExpenses\Pages;

use App\Actions\English\Finance\SaveEnglishExpense;
use App\Filament\English\Resources\EnglishExpenses\EnglishExpenseResource;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishExpense;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEnglishExpenses extends ManageRecords
{
    protected static string $resource = EnglishExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data, Action $action, SaveEnglishExpense $saveExpense): EnglishExpense => Attempt::action(
                    $action,
                    fn (): EnglishExpense => $saveExpense->handle($data),
                )),
        ];
    }
}
