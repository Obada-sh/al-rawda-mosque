<?php

namespace App\Filament\Widgets\Finance;

use App\Filament\Support\Presenters\ReportPresenter;
use App\Filament\Widgets\Finance\Concerns\ReadsExpenseFilter;
use App\Services\Finance\ExpenseReport;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The headline figures of the expenses report.
 */
class SpendingTotalsStats extends StatsOverviewWidget
{
    use ReadsExpenseFilter;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $filter = $this->expenseFilter();

        if ($filter === null) {
            return [];
        }

        $report = app(ExpenseReport::class);
        $totals = $report->totals($filter);
        $largest = $report->byDepartment($filter)->first();

        return [
            Stat::make('إجمالي المصاريف', Money::pounds($totals->total))
                ->description('في الفترة المختارة')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
            Stat::make('عدد المصاريف', number_format($totals->count))
                ->description('مصروف مسجَّل')
                ->descriptionIcon(Heroicon::OutlinedListBullet)
                ->color('primary'),
            Stat::make('أكثر الأقسام صرفاً', $largest?->departmentName ?? '—')
                ->description($largest === null
                    ? 'لا توجد مصاريف'
                    : Money::pounds($largest->total).' ('.ReportPresenter::percent($largest->share).')')
                ->descriptionIcon(Heroicon::OutlinedBuildingStorefront)
                ->color('primary'),
        ];
    }
}
