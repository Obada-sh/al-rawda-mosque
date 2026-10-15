<?php

namespace App\Filament\Resources\PointTransactions\Tables;

use App\Actions\Points\DeleteManualPoints;
use App\Actions\Points\UpdateManualPoints;
use App\Enums\PointSource;
use App\Filament\Support\Attempt;
use App\Filament\Support\Filters\CourseFilter;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Filament\Support\Presenters\PointsPresenter;
use App\Models\PointTransaction;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PointTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_on')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollment.student.full_name')
                    ->label('الطالب')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('enrollment.circle.name')
                    ->label('الحلقة'),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة')
                    ->toggleable(),
                TextColumn::make('source')
                    ->label('المصدر')
                    ->badge()
                    ->formatStateUsing(fn (PointSource $state): string => $state->label())
                    ->color(fn (PointSource $state): string => PointsPresenter::sourceColor($state)),
                TextColumn::make('points')
                    ->label('النقاط')
                    ->alignCenter()
                    ->weight('bold')
                    ->color(fn (PointTransaction $record): string => PointsPresenter::amountColor($record->points))
                    ->sortable()
                    ->summarize(Sum::make()->label('المجموع')),
                TextColumn::make('reason')
                    ->label('السبب')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                CourseFilter::make(),
                SelectFilter::make('source')
                    ->label('المصدر')
                    ->options(PointSource::options()),
                DateRangeFilter::make('occurred_on', 'التاريخ'),
            ])
            ->recordActions([
                self::editAction(),
                DeleteAction::make()
                    ->visible(fn (PointTransaction $record): bool => $record->isManual())
                    ->modalHeading('حذف النقاط')
                    ->using(fn (PointTransaction $record, DeleteManualPoints $deleteManualPoints): bool => Attempt::confirmed(function () use ($record, $deleteManualPoints): bool {
                        $deleteManualPoints->handle($record);

                        return true;
                    })),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('لا توجد نقاط بعد');
    }

    /**
     * Only manual points are corrected here. Automatic points follow the
     * attendance or the recitation that earned them.
     */
    private static function editAction(): Action
    {
        return Action::make('edit')
            ->label('تعديل')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (PointTransaction $record): bool => $record->isManual())
            ->modalHeading(fn (PointTransaction $record): string => "تعديل نقاط {$record->enrollment->student->full_name}")
            ->modalSubmitActionLabel('حفظ')
            ->fillForm(fn (PointTransaction $record): array => [
                'points' => $record->points,
                'reason' => $record->reason,
                'occurred_on' => $record->occurred_on->toDateString(),
            ])
            ->schema(self::pointsFields())
            ->action(fn (PointTransaction $record, array $data, Action $action, UpdateManualPoints $updateManualPoints): PointTransaction => Attempt::action(
                $action,
                fn (): PointTransaction => $updateManualPoints->handle($record, $data),
            ))
            ->successNotificationTitle('تم حفظ النقاط');
    }

    /**
     * The fields shared by "add points" and "edit points".
     *
     * @return array<int, TextInput|DatePicker>
     */
    public static function pointsFields(): array
    {
        return [
            TextInput::make('points')
                ->label('عدد النقاط')
                ->helperText('للخصم ضع إشارة السالب قبل العدد.')
                ->inputMode('text')
                ->extraInputAttributes(['dir' => 'ltr'])
                ->markAsRequired(),
            DatePicker::make('occurred_on')
                ->label('التاريخ')
                ->default(fn (): string => Dates::today()->toDateString())
                ->maxDate(fn () => Dates::today())
                ->markAsRequired(),
            TextInput::make('reason')
                ->label('السبب')
                ->markAsRequired()
                ->columnSpanFull(),
        ];
    }
}
