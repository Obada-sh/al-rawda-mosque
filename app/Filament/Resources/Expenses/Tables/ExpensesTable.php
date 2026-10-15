<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Actions\Finance\DeleteExpense;
use App\Actions\Finance\SaveExpense;
use App\Enums\ExpenseKind;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Support\Attempt;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Filament\Support\Options;
use App\Models\Expense;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('expense_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label('القسم')
                    ->badge()
                    ->sortable(),
                TextColumn::make('item_name')
                    ->label('الصنف / الوصف')
                    ->description(fn (Expense $record): ?string => $record->kind === ExpenseKind::LumpSum ? ExpenseKind::LumpSum->label() : null)
                    ->searchable()
                    ->wrap(),
                // An amount paid as a whole has no quantity and no unit price.
                TextColumn::make('quantity')
                    ->label('الكمية')
                    ->numeric(maxDecimalPlaces: 3)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('unit_price')
                    ->label('سعر الواحدة')
                    ->formatStateUsing(fn (string|int|float $state): string => Money::pounds($state))
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('total')
                    ->label('الإجمالي')
                    ->formatStateUsing(fn (string|int|float $state): string => Money::pounds($state))
                    ->alignEnd()
                    ->sortable()
                    ->weight('bold')
                    ->summarize(Sum::make()
                        ->label('المجموع')
                        ->formatStateUsing(fn (mixed $state): string => Money::pounds(is_numeric($state) ? $state : 0))),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('القسم')
                    ->options(fn (): array => Options::allDepartments()),
                DateRangeFilter::make('expense_date', 'التاريخ'),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (Expense $record): array => ExpenseForm::fill($record))
                    ->using(fn (Expense $record, array $data, Action $action, SaveExpense $saveExpense): Expense => Attempt::action(
                        $action,
                        fn (): Expense => $saveExpense->handle($data, $record),
                    )),
                DeleteAction::make()
                    ->using(function (Expense $record, DeleteExpense $deleteExpense): bool {
                        $deleteExpense->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('expense_date', 'desc');
    }
}
