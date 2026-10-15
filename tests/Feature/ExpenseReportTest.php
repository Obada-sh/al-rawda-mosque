<?php

namespace Tests\Feature;

use App\Enums\ExpenseKind;
use App\Services\Finance\DepartmentSpending;
use App\Services\Finance\ExpenseFilter;
use App\Services\Finance\ExpenseReport;
use App\Services\Finance\ItemSpending;
use App\Services\Finance\MonthSpending;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\SeedsExpenses;
use Tests\TestCase;

/**
 * The expenses report, on the expenses described in SeedsExpenses.
 */
class ExpenseReportTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;
    use SeedsExpenses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');
        $this->seedExpenses();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function filter(array $input = []): ExpenseFilter
    {
        return ExpenseFilter::fromInput($input);
    }

    private function report(): ExpenseReport
    {
        return app(ExpenseReport::class);
    }

    public function test_everything_spent_is_totalled(): void
    {
        $totals = $this->report()->totals($this->filter());

        $this->assertSame(['37005.00', 4], [$totals->total, $totals->count]);
    }

    public function test_a_period_or_a_department_narrows_the_total(): void
    {
        $october = $this->report()->totals($this->filter(['from' => '2026-10-01', 'until' => '2026-10-31']));
        $this->assertSame(['33000.00', 2], [$october->total, $october->count]);

        $purchases = $this->report()->totals($this->filter(['department_id' => $this->purchases->getKey()]));
        $this->assertSame(['4500.00', 2], [$purchases->total, $purchases->count]);

        $nothing = $this->report()->totals($this->filter(['department_id' => $this->logistics->getKey()]));
        $this->assertSame(['0.00', 0], [$nothing->total, $nothing->count]);
    }

    public function test_departments_are_listed_with_their_share_of_the_total(): void
    {
        $lines = $this->report()->byDepartment($this->filter())
            ->map(fn (DepartmentSpending $department): array => [$department->departmentName, $department->total, $department->count, $department->share])
            ->all();

        // A department that spent nothing is not listed.
        $this->assertSame([
            ['إطعام', '32505.00', 2, 87.8],
            ['مشتريات', '4500.00', 2, 12.2],
        ], $lines);
    }

    public function test_months_are_listed_in_order_with_what_each_department_spent(): void
    {
        $months = $this->report()->byMonth($this->filter());

        $this->assertSame(['2026-08', '2026-09', '2026-10'], $months->pluck('month')->all());
        $this->assertSame(['2505.00', '1500.00', '33000.00'], $months->pluck('total')->all());
        $this->assertSame([1, 1, 2], $months->pluck('count')->all());

        /** @var MonthSpending $october */
        $october = $months->last();
        $this->assertSame('3000.00', $october->of($this->purchases->getKey()));
        $this->assertSame('30000.00', $october->of($this->food->getKey()));
        $this->assertSame('0.00', $october->of($this->logistics->getKey()));
        $this->assertSame([2026, 10], [$october->year(), $october->monthNumber()]);
    }

    public function test_a_month_without_expenses_inside_the_period_is_listed_with_zero(): void
    {
        $months = $this->report()->byMonth($this->filter(['from' => '2026-06-15', 'until' => '2026-10-07']));

        $this->assertSame(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10'], $months->pluck('month')->all());
        $this->assertSame(['0.00', '0.00', '2505.00', '1500.00', '33000.00'], $months->pluck('total')->all());

        $this->assertTrue($this->report()->byMonth($this->filter(['department_id' => $this->logistics->getKey()]))->isEmpty());
    }

    public function test_the_most_expensive_items_come_first_and_same_names_are_added_up(): void
    {
        $lines = $this->report()->topItems($this->filter())
            ->map(fn (ItemSpending $item): array => [$item->itemName, $item->quantity, $item->total, $item->times])
            ->all();

        $this->assertSame([
            ['تمر', '2.500', '30000.00', 1],
            ['ورق', '3.000', '4500.00', 2],
            ['ماء', '10.000', '2505.00', 1],
        ], $lines);

        $this->assertSame(['تمر'], $this->report()->topItems($this->filter(), 1)->pluck('itemName')->all());
    }

    public function test_an_amount_paid_as_a_whole_counts_in_the_totals_but_is_not_an_item(): void
    {
        $this->spend([
            'kind' => ExpenseKind::LumpSum->value,
            'department_id' => $this->food->getKey(),
            'item_name' => 'ضيافة متنوعة',
            'amount' => '50000',
            'expense_date' => '2026-10-03',
        ]);

        $this->assertSame(['87005.00', 5], [$this->report()->totals($this->filter())->total, $this->report()->totals($this->filter())->count]);
        $this->assertSame('82505.00', $this->report()->byDepartment($this->filter())->first()->total);
        $this->assertSame('83000.00', $this->report()->byMonth($this->filter())->last()->total);
        $this->assertSame(['تمر', 'ورق', 'ماء'], $this->report()->topItems($this->filter())->pluck('itemName')->all());
    }

    public function test_the_filter_accepts_only_a_period_that_ends_after_it_starts(): void
    {
        $filter = ExpenseFilter::fromInput(['from' => '2026-10-01 00:00:00', 'department_id' => (string) $this->food->getKey()]);

        $this->assertSame(['2026-10-01', null, $this->food->getKey()], [$filter->from, $filter->until, $filter->departmentId]);
        $this->assertNull(ExpenseFilter::tryFromInput(['department_id' => 999999]));
        $this->assertNull(ExpenseFilter::tryFromInput(['from' => '2026-10-05', 'until' => '2026-10-01']));

        $this->expectException(ValidationException::class);
        ExpenseFilter::fromInput(['from' => '2026-10-05', 'until' => '2026-10-01']);
    }
}
