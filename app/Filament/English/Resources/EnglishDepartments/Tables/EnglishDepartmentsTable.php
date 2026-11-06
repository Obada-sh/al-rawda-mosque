<?php

namespace App\Filament\English\Resources\EnglishDepartments\Tables;

use App\Actions\English\Finance\DeleteEnglishDepartment;
use App\Actions\English\Finance\SaveEnglishDepartment;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishDepartment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnglishDepartmentsTable
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
                    ->using(fn (EnglishDepartment $record, array $data, Action $action, SaveEnglishDepartment $saveDepartment): EnglishDepartment => Attempt::action(
                        $action,
                        fn (): EnglishDepartment => $saveDepartment->handle($data, $record),
                    )),
                DeleteAction::make()
                    ->using(fn (EnglishDepartment $record, DeleteEnglishDepartment $deleteDepartment): bool => Attempt::confirmed(function () use ($record, $deleteDepartment): bool {
                        $deleteDepartment->handle($record);

                        return true;
                    })),
            ])
            ->defaultSort('name')
            ->paginated(false);
    }
}
