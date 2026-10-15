<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Actions\Finance\SaveExpense;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Support\Attempt;
use App\Models\Expense;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data, Action $action, SaveExpense $saveExpense): Expense => Attempt::action(
                    $action,
                    fn (): Expense => $saveExpense->handle($data),
                )),
        ];
    }
}
