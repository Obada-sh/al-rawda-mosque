<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Support\Export\LeadersSlidesAction;
use App\Services\Statistics\ReportFilter;

/**
 * Base of the pages that report on students: their filter is a course,
 * one of its circles, and a period.
 */
abstract class ReportDashboard extends FilteredDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
            LeadersSlidesAction::make(fn (): array => $this->filters ?? []),
        ];
    }

    protected function checkFilters(array $filters): void
    {
        ReportFilter::fromInput($filters);
    }
}
