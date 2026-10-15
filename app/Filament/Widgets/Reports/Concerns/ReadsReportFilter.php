<?php

namespace App\Filament\Widgets\Reports\Concerns;

use App\Services\Statistics\ReportFilter;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * For the parts of a report page: reads the course, circle and period
 * chosen at the top of the page.
 */
trait ReadsReportFilter
{
    use InteractsWithPageFilters;

    /**
     * The chosen scope, or null while the choice is not a valid one (the
     * page shows why under the field; the report shows nothing meanwhile).
     */
    protected function reportFilter(): ?ReportFilter
    {
        return ReportFilter::tryFromInput($this->pageFilters ?? []);
    }
}
