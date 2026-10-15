<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Support\Presenters\ReportPresenter;
use App\Filament\Widgets\Reports\Concerns\ReadsReportFilter;
use App\Services\Statistics\ScopeTotals;
use App\Services\Statistics\StudentStandings;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The totals of the chosen scope: one circle, one course, or the whole
 * mosque when nothing is chosen.
 */
class ScopeTotalsStats extends StatsOverviewWidget
{
    use ReadsReportFilter;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $filter = $this->reportFilter();

        if ($filter === null) {
            return [];
        }

        $totals = ScopeTotals::of(app(StudentStandings::class)->for($filter));
        $attendanceRate = $totals->attendanceRate();

        return [
            Stat::make('الطلاب', number_format($totals->students))
                ->description('في النطاق المختار')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('primary'),
            Stat::make('نسبة الحضور', ReportPresenter::percent($attendanceRate) ?? '—')
                ->description(number_format($totals->attended).' حضور من '.number_format($totals->sessions))
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color(ReportPresenter::attendanceColor($attendanceRate)),
            Stat::make('صفحات جديدة', number_format($totals->newPages))
                ->description('من '.number_format($totals->recitedPages).' صفحة مسمَّعة')
                ->descriptionIcon(Heroicon::OutlinedBookOpen)
                ->color('primary'),
            Stat::make('النقاط', number_format($totals->points))
                ->description('المتوسط للطالب: '.(ReportPresenter::decimal($totals->averagePoints()) ?? '—'))
                ->descriptionIcon(Heroicon::OutlinedStar)
                ->color('primary'),
        ];
    }
}
