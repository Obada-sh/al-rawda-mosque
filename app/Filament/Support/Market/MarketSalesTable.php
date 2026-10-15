<?php

namespace App\Filament\Support\Market;

use App\Actions\Market\CancelSale;
use App\Filament\Support\Attempt;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Filament\Support\Options;
use App\Filament\Support\Presenters\MarketPresenter;
use App\Models\MarketSale;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The purchases at the points markets, as one table wherever they are
 * listed: the register of all purchases, a market's page, a student's
 * file. Each place hides what it already says (the market, the student).
 */
final class MarketSalesTable
{
    public static function configure(Table $table, bool $showMarket = true, bool $showStudent = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['lines', 'market.course', 'enrollment.student', 'enrollment.circle']))
            ->columns([
                TextColumn::make('sold_at')
                    ->label('الوقت')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('enrollment.student.full_name')
                    ->label('الطالب')
                    ->weight('medium')
                    ->searchable()
                    ->visible($showStudent),
                TextColumn::make('enrollment.circle.name')
                    ->label('الحلقة')
                    ->visible($showStudent),
                TextColumn::make('market.name')
                    ->label('السوق')
                    ->description(fn (MarketSale $record): string => $record->market->course->name)
                    ->visible($showMarket),
                TextColumn::make('lines_summary')
                    ->label('ما اشتراه')
                    ->state(fn (MarketSale $record): string => MarketPresenter::lines($record))
                    ->wrap(),
                TextColumn::make('total_points')
                    ->label('النقاط')
                    ->weight('bold')
                    ->alignCenter()
                    ->sortable()
                    ->summarize(Sum::make()->label('المجموع')),
            ])
            ->filters([
                SelectFilter::make('market_id')
                    ->label('السوق')
                    ->options(fn (): array => Options::markets())
                    ->visible($showMarket),
                DateRangeFilter::make('sold_at', 'التاريخ'),
            ])
            ->recordActions([
                self::cancelAction(),
            ])
            ->defaultSort('sold_at', 'desc')
            ->emptyStateHeading('لا توجد مشتريات بعد');
    }

    /**
     * Cancels a purchase made by mistake: the points go back to the student
     * and the items back to the market.
     */
    public static function cancelAction(): Action
    {
        return Action::make('cancelSale')
            ->label('إلغاء الشراء')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (MarketSale $record): string => "إلغاء شراء {$record->enrollment->student->full_name}")
            ->modalDescription(fn (MarketSale $record): string => 'ترجع '.MarketPresenter::points($record->total_points).' إلى رصيد الطالب، وترجع الكميات إلى السوق.')
            ->modalSubmitActionLabel('إلغاء الشراء')
            ->action(fn (MarketSale $record, CancelSale $cancelSale) => Attempt::confirmed(fn () => $cancelSale->handle($record)))
            ->successNotificationTitle('أُلغي الشراء ورجعت النقاط إلى الطالب');
    }
}
