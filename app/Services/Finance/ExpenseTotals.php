<?php

namespace App\Services\Finance;

/**
 * Everything spent in the scope of the report.
 */
final readonly class ExpenseTotals
{
    /**
     * @param  string  $total  decimal string such as "1250.50"
     * @param  int  $count  number of expenses
     */
    public function __construct(
        public string $total,
        public int $count,
    ) {}
}
