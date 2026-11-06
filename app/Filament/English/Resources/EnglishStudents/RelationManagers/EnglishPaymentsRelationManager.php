<?php

namespace App\Filament\English\Resources\EnglishStudents\RelationManagers;

use App\Models\English\EnglishPayment;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everything the student paid for English courses, the latest first.
 * Payments are recorded from the student's courses above (or from the
 * list of payments).
 */
class EnglishPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'englishPayments';

    protected static ?string $title = 'الدفعات';

    protected static ?string $modelLabel = 'دفعة';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('enrollment.course'))
            ->columns([
                TextColumn::make('paid_on')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة'),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn (int $state): string => Money::pounds($state))
                    ->weight('bold')
                    ->summarize(Sum::make()
                        ->label('المجموع')
                        ->formatStateUsing(fn (mixed $state): string => Money::pounds(is_numeric($state) ? (int) $state : 0))),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->placeholder('—'),
            ])
            ->defaultSort('paid_on', 'desc')
            ->emptyStateHeading('لم يدفع الطالب شيئاً بعد');
    }
}
