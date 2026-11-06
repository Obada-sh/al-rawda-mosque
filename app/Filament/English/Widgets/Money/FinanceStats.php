<?php

namespace App\Filament\English\Widgets\Money;

use App\Filament\English\Widgets\Money\Concerns\ReadsMoneyFilters;
use App\Services\English\EnglishFinanceReport;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The headline figures of the money report: what came in from the
 * students, what was spent, and what is left.
 */
class FinanceStats extends StatsOverviewWidget
{
    use ReadsMoneyFilters;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $filter = $this->financeFilter();

        if ($filter === null) {
            return [];
        }

        $totals = app(EnglishFinanceReport::class)->totals($filter);
        $loss = str_starts_with($totals['net'], '-');

        return [
            Stat::make('الإيرادات (دفعات الطلاب)', Money::pounds($totals['income']))
                ->description("عدد الدفعات: {$totals['payments']}")
                ->descriptionIcon(Heroicon::OutlinedArrowDownTray)
                ->color('success'),
            Stat::make('المصاريف', Money::pounds($totals['expenses']))
                ->description("عدد المصاريف: {$totals['expenseCount']}")
                ->descriptionIcon(Heroicon::OutlinedArrowUpTray)
                ->color('danger'),
            Stat::make('الصافي', Money::signedPounds($totals['net']))
                ->description($loss ? 'المصاريف أكثر من الإيرادات' : 'الإيرادات بعد المصاريف')
                ->descriptionIcon($loss ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedScale)
                ->color($loss ? 'danger' : 'primary'),
        ];
    }
}
