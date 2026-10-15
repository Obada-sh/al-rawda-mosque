<?php

namespace App\Filament\Resources\TajweedLevels\Tables;

use App\Actions\TajweedLevels\DeleteTajweedLevel;
use App\Actions\TajweedLevels\SaveTajweedLevel;
use App\Filament\Support\Attempt;
use App\Models\TajweedLevel;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TajweedLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('المستوى')
                    ->searchable(),
                TextColumn::make('students_count')
                    ->label('عدد الطلاب')
                    ->counts('students')
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (TajweedLevel $record, array $data, Action $action, SaveTajweedLevel $saveTajweedLevel): TajweedLevel => Attempt::action(
                        $action,
                        fn (): TajweedLevel => $saveTajweedLevel->handle($data, $record),
                    )),
                DeleteAction::make()
                    ->using(fn (TajweedLevel $record, DeleteTajweedLevel $deleteTajweedLevel): bool => Attempt::confirmed(function () use ($record, $deleteTajweedLevel): bool {
                        $deleteTajweedLevel->handle($record);

                        return true;
                    })),
            ])
            ->defaultSort('sort_order')
            ->paginated(false);
    }
}
