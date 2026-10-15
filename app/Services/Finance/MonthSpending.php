<?php

namespace App\Services\Finance;

/**
 * What was spent in one calendar month.
 */
final readonly class MonthSpending
{
    /**
     * @param  string  $month  the month as "2026-10"
     * @param  string  $total  decimal string such as "1250.50"
     * @param  array<int, string>  $byDepartment  department id => what it spent in the month
     */
    public function __construct(
        public string $month,
        public string $total,
        public int $count,
        public array $byDepartment,
    ) {}

    public function year(): int
    {
        return (int) substr($this->month, 0, 4);
    }

    public function monthNumber(): int
    {
        return (int) substr($this->month, 5, 2);
    }

    /**
     * What one department spent in the month ("0.00" when it spent nothing).
     */
    public function of(int $departmentId): string
    {
        return $this->byDepartment[$departmentId] ?? '0.00';
    }
}
