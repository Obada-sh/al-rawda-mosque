<?php

namespace App\Services\Finance;

/**
 * What one department spent in the scope of the report.
 */
final readonly class DepartmentSpending
{
    /**
     * @param  string  $total  decimal string such as "1250.50"
     * @param  float|null  $share  its part of everything spent, as a percentage
     */
    public function __construct(
        public int $departmentId,
        public string $departmentName,
        public string $total,
        public int $count,
        public ?float $share,
    ) {}
}
