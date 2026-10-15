<?php

namespace App\Filament\Widgets\Finance;

use App\Filament\Support\Presenters\ReportPresenter;
use App\Filament\Widgets\Finance\Concerns\ReadsExpenseFilter;
use App\Services\Finance\DepartmentSpending;
use App\Services\Finance\ExpenseReport;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * What each department spent, and its part of the total.
 */
class SpendingByDepartment extends TableWidget
{
    use ReadsExpenseFilter;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('الإجمالي لكل قسم')
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('department')
                    ->label('القسم')
                    ->weight('medium'),
                TextColumn::make('count')
                    ->label('عدد المصاريف')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('total')
                    ->label('الإجمالي')
                    ->alignEnd()
                    ->weight('bold'),
                TextColumn::make('share')
                    ->label('النسبة من المجموع')
                    ->placeholder('—')
                    ->alignCenter(),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا توجد مصاريف في هذه الفترة');
    }

    /**
     * @return Collection<int, array<string, mixed>> keyed by department id, the largest first
     */
    private function rows(): Collection
    {
        $filter = $this->expenseFilter();

        if ($filter === null) {
            return collect();
        }

        return app(ExpenseReport::class)->byDepartment($filter)->mapWithKeys(fn (DepartmentSpending $department): array => [
            $department->departmentId => [
                'department' => $department->departmentName,
                'count' => $department->count,
                'total' => Money::pounds($department->total),
                'share' => ReportPresenter::percent($department->share),
            ],
        ]);
    }
}
