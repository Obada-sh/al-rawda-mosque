<?php

namespace App\Filament\English\Widgets\Money;

use App\Filament\English\Widgets\Money\Concerns\ReadsMoneyFilters;
use App\Services\English\EnglishFinanceReport;
use App\Support\Dates;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Month by month: what came in, what was spent, what was left.
 */
class MonthlyMoneyTable extends TableWidget
{
    use ReadsMoneyFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('لكل شهر')
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('month')->label('الشهر')->weight('medium'),
                TextColumn::make('income')->label('الإيرادات')->alignEnd(),
                TextColumn::make('expenses')->label('المصاريف')->alignEnd(),
                TextColumn::make('net')->label('الصافي')->alignEnd()->weight('bold')
                    ->color(fn (array $record): ?string => $record['loss'] ? 'danger' : null),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا توجد دفعات ولا مصاريف في هذه الفترة');
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function rows(): Collection
    {
        $filter = $this->financeFilter();

        if ($filter === null) {
            return collect();
        }

        return app(EnglishFinanceReport::class)->byMonth($filter)->mapWithKeys(fn (array $month): array => [
            sprintf('%04d-%02d', $month['year'], $month['month']) => [
                'month' => Dates::showMonth($month['year'], $month['month']),
                'income' => Money::pounds($month['income']),
                'expenses' => Money::pounds($month['expenses']),
                'net' => Money::signedPounds($month['net']),
                'loss' => str_starts_with($month['net'], '-'),
            ],
        ]);
    }
}
