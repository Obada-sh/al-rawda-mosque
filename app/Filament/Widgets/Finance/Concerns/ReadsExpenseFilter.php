<?php

namespace App\Filament\Widgets\Finance\Concerns;

use App\Services\Finance\ExpenseFilter;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * For the parts of the expenses report: reads the department and period
 * chosen at the top of the page.
 */
trait ReadsExpenseFilter
{
    use InteractsWithPageFilters;

    /**
     * The chosen scope, or null while the choice is not a valid one.
     */
    protected function expenseFilter(): ?ExpenseFilter
    {
        return ExpenseFilter::tryFromInput($this->pageFilters ?? []);
    }
}
