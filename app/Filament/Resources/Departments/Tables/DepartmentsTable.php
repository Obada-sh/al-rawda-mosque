<?php

namespace App\Filament\Resources\Departments\Tables;

use App\Actions\Finance\DeleteDepartment;
use App\Actions\Finance\SaveDepartment;
use App\Filament\Support\Attempt;
use App\Models\Department;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('القسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('expenses_count')
                    ->label('عدد المصاريف')
                    ->counts('expenses')
                    ->alignCenter(),
                TextColumn::make('expenses_sum_total')
                    ->label('إجمالي المصاريف')
                    ->sum('expenses', 'total')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('0.00'),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (Department $record, array $data, Action $action, SaveDepartment $saveDepartment): Department => Attempt::action(
                        $action,
                        fn (): Department => $saveDepartment->handle($data, $record),
                    )),
                DeleteAction::make()
                    ->using(fn (Department $record, DeleteDepartment $deleteDepartment): bool => Attempt::confirmed(function () use ($record, $deleteDepartment): bool {
                        $deleteDepartment->handle($record);

                        return true;
                    })),
            ])
            ->defaultSort('name')
            ->paginated(false);
    }
}
