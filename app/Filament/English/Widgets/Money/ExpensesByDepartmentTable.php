<?php

namespace App\Filament\English\Widgets\Money;

use App\Filament\English\Widgets\Money\Concerns\ReadsMoneyFilters;
use App\Services\English\EnglishFinanceReport;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * What each department of the English section spent in the period.
 */
class ExpensesByDepartmentTable extends TableWidget
{
    use ReadsMoneyFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('المصاريف لكل قسم')
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('department')->label('القسم')->weight('medium'),
                TextColumn::make('count')->label('عدد المصاريف')->alignCenter(),
                TextColumn::make('total')->label('الإجمالي')->alignEnd()->weight('bold'),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا توجد مصاريف في هذه الفترة');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(): Collection
    {
        $filter = $this->financeFilter();

        if ($filter === null) {
            return collect();
        }

        return app(EnglishFinanceReport::class)->expensesByDepartment($filter)->map(fn (array $row): array => [
            'department' => $row['department'],
            'count' => $row['count'],
            'total' => Money::pounds($row['total']),
        ]);
    }
}
