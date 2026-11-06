<?php

namespace App\Filament\English\Widgets\Money;

use App\Filament\English\Widgets\Money\Concerns\ReadsMoneyFilters;
use App\Services\English\EnglishMoneyReport;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The headline figures of the payments report: what the chosen students
 * have to pay, what they paid and what remains.
 */
class BalanceStats extends StatsOverviewWidget
{
    use ReadsMoneyFilters;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $filter = $this->balanceFilter();

        if ($filter === null) {
            return [];
        }

        $report = app(EnglishMoneyReport::class);
        $total = $report->total($report->students($filter));

        return [
            Stat::make('المطلوب من الطلاب', Money::pounds($total->due))
                ->description("عدد الطلاب: {$total->students}")
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('primary'),
            Stat::make('المدفوع', Money::pounds($total->paid))
                ->description("دفعوا كاملاً: {$total->paidInFull}")
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success'),
            Stat::make('المتبقي', Money::pounds($total->remaining))
                ->description("دفعوا جزءاً: {$total->partly} · لم يدفعوا: {$total->unpaid}")
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($total->remaining > 0 ? 'warning' : 'success'),
        ];
    }
}
