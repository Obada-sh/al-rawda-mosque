<?php

namespace App\Services\Finance;

use App\Support\Dates;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The expenses report: the total, and how it splits between departments,
 * months and items. Every amount is in Syrian pounds.
 *
 * Amounts are added by the database on exact decimals and travel as
 * decimal strings; they are never turned into floating-point numbers.
 */
final class ExpenseReport
{
    public const TOP_ITEMS = 10;

    public function totals(ExpenseFilter $filter): ExpenseTotals
    {
        $row = $this->expenses($filter)
            ->selectRaw('COALESCE(SUM(expenses.total), 0) as spent, COUNT(*) as times')
            ->first();

        return new ExpenseTotals(
            total: Money::sum((string) $row->spent),
            count: (int) $row->times,
        );
    }

    /**
     * @return Collection<int, DepartmentSpending> the department that spent the most first
     */
    public function byDepartment(ExpenseFilter $filter): Collection
    {
        $rows = $this->expenses($filter)
            ->join('departments', 'departments.id', '=', 'expenses.department_id')
            ->groupBy('departments.id', 'departments.name')
            ->select('departments.id', 'departments.name')
            ->selectRaw('SUM(expenses.total) as spent, COUNT(*) as times')
            ->orderByDesc('spent')
            ->orderBy('departments.name')
            ->get();

        $everything = Money::sum(...$rows->map(fn (object $row): string => (string) $row->spent)->all());

        return $rows->map(fn (object $row): DepartmentSpending => new DepartmentSpending(
            departmentId: (int) $row->id,
            departmentName: (string) $row->name,
            total: Money::sum((string) $row->spent),
            count: (int) $row->times,
            share: Money::share((string) $row->spent, $everything),
        ));
    }

    /**
     * Every month of the period, in order, including the months in which
     * nothing was spent (a gap in a chart of months would hide them).
     *
     * @return Collection<int, MonthSpending>
     */
    public function byMonth(ExpenseFilter $filter): Collection
    {
        $rows = $this->expenses($filter)
            ->selectRaw("DATE_FORMAT(expenses.expense_date, '%Y-%m') as spent_month")
            ->addSelect('expenses.department_id')
            ->selectRaw('SUM(expenses.total) as spent, COUNT(*) as times')
            ->groupBy('spent_month', 'expenses.department_id')
            ->orderBy('spent_month')
            ->get()
            ->groupBy('spent_month');

        if ($rows->isEmpty()) {
            return collect();
        }

        return collect($this->months($filter, (string) $rows->keys()->first(), (string) $rows->keys()->last()))
            ->map(function (string $month) use ($rows): MonthSpending {
                /** @var Collection<int, object> $spent */
                $spent = $rows->get($month, collect());

                return new MonthSpending(
                    month: $month,
                    total: Money::sum(...$spent->map(fn (object $row): string => (string) $row->spent)->all()),
                    count: (int) $spent->sum('times'),
                    byDepartment: $spent
                        ->mapWithKeys(fn (object $row): array => [(int) $row->department_id => Money::sum((string) $row->spent)])
                        ->all(),
                );
            });
    }

    /**
     * The items bought by quantity. An amount paid as a whole (several
     * things whose items nobody kept) is not an item, so it is left out.
     *
     * @return Collection<int, ItemSpending> the item that cost the most first
     */
    public function topItems(ExpenseFilter $filter, int $limit = self::TOP_ITEMS): Collection
    {
        return $this->expenses($filter)
            ->whereNotNull('expenses.quantity')
            ->groupBy('expenses.item_name')
            ->select('expenses.item_name')
            ->selectRaw('SUM(expenses.quantity) as bought, SUM(expenses.total) as spent, COUNT(*) as times')
            ->orderByDesc('spent')
            ->orderBy('expenses.item_name')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): ItemSpending => new ItemSpending(
                itemName: (string) $row->item_name,
                quantity: (string) $row->bought,
                total: Money::sum((string) $row->spent),
                times: (int) $row->times,
            ));
    }

    private function expenses(ExpenseFilter $filter): Builder
    {
        $query = DB::table('expenses');

        $filter->apply($query);

        return $query;
    }

    /**
     * The months from the start of the period to its end, as "2026-10".
     * A side of the period that was left open stops at the first (or the
     * last) month that has an expense.
     *
     * @return list<string>
     */
    private function months(ExpenseFilter $filter, string $firstSpent, string $lastSpent): array
    {
        $month = $filter->from !== null
            ? Dates::parse($filter->from)->startOfMonth()
            : CarbonImmutable::createFromFormat('!Y-m', $firstSpent);

        $last = $filter->until !== null
            ? Dates::parse($filter->until)->startOfMonth()
            : CarbonImmutable::createFromFormat('!Y-m', $lastSpent);

        $months = [];

        while ($month->lessThanOrEqualTo($last)) {
            $months[] = $month->format('Y-m');
            $month = $month->addMonth();
        }

        return $months;
    }
}
