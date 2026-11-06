<?php

namespace App\Filament\English\Resources\EnglishExpenses\Tables;

use App\Actions\English\Finance\DeleteEnglishExpense;
use App\Actions\English\Finance\SaveEnglishExpense;
use App\Enums\ExpenseKind;
use App\Filament\English\Resources\EnglishExpenses\Schemas\EnglishExpenseForm;
use App\Filament\English\Support\EnglishOptions;
use App\Filament\Support\Attempt;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Models\English\EnglishExpense;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EnglishExpensesTable
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
                    ->description(fn (EnglishExpense $record): ?string => $record->kind === ExpenseKind::LumpSum ? ExpenseKind::LumpSum->label() : null)
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
                SelectFilter::make('english_department_id')
                    ->label('القسم')
                    ->options(fn (): array => EnglishOptions::allDepartments()),
                DateRangeFilter::make('expense_date', 'التاريخ'),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (EnglishExpense $record): array => EnglishExpenseForm::fill($record))
                    ->using(fn (EnglishExpense $record, array $data, Action $action, SaveEnglishExpense $saveExpense): EnglishExpense => Attempt::action(
                        $action,
                        fn (): EnglishExpense => $saveExpense->handle($data, $record),
                    )),
                DeleteAction::make()
                    ->using(function (EnglishExpense $record, DeleteEnglishExpense $deleteExpense): bool {
                        $deleteExpense->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('expense_date', 'desc');
    }
}
