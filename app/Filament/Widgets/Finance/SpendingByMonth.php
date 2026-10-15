<?php

namespace App\Filament\Widgets\Finance;

use App\Filament\Widgets\Finance\Concerns\ReadsExpenseFilter;
use App\Services\Finance\ExpenseReport;
use App\Services\Finance\MonthSpending;
use App\Support\Dates;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * What was spent in each month: the same figures as the chart, as numbers.
 */
class SpendingByMonth extends TableWidget
{
    use ReadsExpenseFilter;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('الإجمالي لكل شهر')
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('month')
                    ->label('الشهر')
                    ->weight('medium'),
                TextColumn::make('count')
                    ->label('عدد المصاريف')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('total')
                    ->label('الإجمالي')
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا توجد مصاريف في هذه الفترة');
    }

    /**
     * @return Collection<string, array<string, mixed>> keyed by month ("2026-10"), the latest month first
     */
    private function rows(): Collection
    {
        $filter = $this->expenseFilter();

        if ($filter === null) {
            return collect();
        }

        return app(ExpenseReport::class)->byMonth($filter)->reverse()->mapWithKeys(fn (MonthSpending $month): array => [
            $month->month => [
                'month' => Dates::showMonth($month->year(), $month->monthNumber()),
                'count' => $month->count,
                'total' => Money::pounds($month->total),
            ],
        ]);
    }
}
