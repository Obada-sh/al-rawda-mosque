<?php

namespace App\Filament\English\Widgets\Money\Concerns;

use App\Services\English\EnglishBalanceFilter;
use App\Services\English\EnglishFinanceFilter;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * For the parts of the English section's money reports: reads the choices
 * made at the top of the page.
 */
trait ReadsMoneyFilters
{
    use InteractsWithPageFilters;

    /** The scope of the payments report, or null while the choice is not a valid one. */
    protected function balanceFilter(): ?EnglishBalanceFilter
    {
        return EnglishBalanceFilter::tryFromInput($this->pageFilters ?? []);
    }

    /** The scope of the money report, or null while the choice is not a valid one. */
    protected function financeFilter(): ?EnglishFinanceFilter
    {
        return EnglishFinanceFilter::tryFromInput($this->pageFilters ?? []);
    }
}
