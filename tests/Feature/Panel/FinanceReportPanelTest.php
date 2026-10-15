<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\Reports\FinanceReport;
use App\Filament\Widgets\Finance\MonthlySpendingChart;
use App\Filament\Widgets\Finance\SpendingByDepartment;
use App\Filament\Widgets\Finance\SpendingByMonth;
use App\Filament\Widgets\Finance\SpendingTotalsStats;
use App\Filament\Widgets\Finance\TopItems;
use Livewire\Livewire;
use Tests\Concerns\SeedsExpenses;

/**
 * The expenses report page, on the expenses described in SeedsExpenses.
 */
class FinanceReportPanelTest extends PanelTestCase
{
    use SeedsExpenses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExpenses();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{pageFilters: array<string, mixed>}
     */
    private function on(array $filters = []): array
    {
        return ['pageFilters' => $filters];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed> what the chart draws
     */
    private function chart(array $filters = []): array
    {
        $chart = Livewire::test(MonthlySpendingChart::class, $this->on($filters))->assertOk()->instance();

        return (fn (): array => $this->getData())->call($chart);
    }

    public function test_the_report_opens_on_the_last_twelve_months(): void
    {
        Livewire::test(FinanceReport::class)
            ->assertOk()
            ->assertSchemaStateSet(['from' => '2025-11-01', 'until' => '2026-10-07', 'department_id' => null], 'filtersForm')
            ->assertSee('37,005 ل.س')
            ->assertSee('الإجمالي لكل قسم');
    }

    public function test_a_period_that_ends_before_it_starts_is_explained_under_the_field(): void
    {
        Livewire::test(FinanceReport::class)
            ->set('filters.until', '2025-01-01')
            ->assertHasErrors(['filters.until'])
            ->set('filters.until', '2026-10-07')
            ->assertHasNoErrors();
    }

    public function test_the_headline_figures_follow_the_filter(): void
    {
        Livewire::test(SpendingTotalsStats::class, $this->on())
            ->assertSee('37,005 ل.س')
            ->assertSee('إطعام')
            ->assertSee('32,505 ل.س (87.8%)');

        Livewire::test(SpendingTotalsStats::class, $this->on(['department_id' => $this->purchases->getKey()]))
            ->assertSee('4,500 ل.س')
            ->assertDontSee('37,005 ل.س');

        Livewire::test(SpendingTotalsStats::class, $this->on(['department_id' => $this->logistics->getKey()]))
            ->assertSee('لا توجد مصاريف');
    }

    public function test_departments_months_and_items_are_tabulated(): void
    {
        $food = (string) $this->food->getKey();
        $purchases = (string) $this->purchases->getKey();

        Livewire::test(SpendingByDepartment::class, $this->on())
            ->assertCanSeeTableRecords([$food, $purchases], inOrder: true)
            ->assertCanNotSeeTableRecords([(string) $this->logistics->getKey()])
            ->assertTableColumnStateSet('total', '32,505 ل.س', $food)
            ->assertTableColumnStateSet('share', '87.8%', $food)
            ->assertTableColumnStateSet('count', 2, $purchases);

        Livewire::test(SpendingByMonth::class, $this->on())
            ->assertCanSeeTableRecords(['2026-10', '2026-09', '2026-08'], inOrder: true)
            ->assertTableColumnStateSet('month', '10/2026', '2026-10')
            ->assertTableColumnStateSet('total', '33,000 ل.س', '2026-10')
            ->assertTableColumnStateSet('month', '08/2026', '2026-08');

        Livewire::test(TopItems::class, $this->on())
            ->assertCanSeeTableRecords(['item-1', 'item-2', 'item-3'], inOrder: true)
            ->assertTableColumnStateSet('item', 'تمر', 'item-1')
            ->assertTableColumnStateSet('quantity', '2.5', 'item-1')
            ->assertTableColumnStateSet('item', 'ورق', 'item-2')
            ->assertTableColumnStateSet('quantity', '3', 'item-2')
            ->assertTableColumnStateSet('times', 2, 'item-2')
            ->assertTableColumnStateSet('total', '4,500 ل.س', 'item-2');
    }

    public function test_the_chart_has_a_column_per_month_and_a_series_per_department(): void
    {
        $data = $this->chart();

        $this->assertSame(['08/2026', '09/2026', '10/2026'], $data['labels']);
        $this->assertSame(['مشتريات', 'إطعام'], array_column($data['datasets'], 'label'));
        $this->assertSame([0.0, 1500.0, 3000.0], $data['datasets'][0]['data']);
        $this->assertSame([2505.0, 0.0, 30000.0], $data['datasets'][1]['data']);
        $this->assertSame(['#2a78d6', '#eb6834'], array_column($data['datasets'], 'backgroundColor'));
    }

    public function test_a_department_keeps_its_colour_when_the_chart_is_filtered(): void
    {
        $data = $this->chart(['department_id' => $this->food->getKey()]);

        $this->assertSame(['إطعام'], array_column($data['datasets'], 'label'));
        $this->assertSame(['#eb6834'], array_column($data['datasets'], 'backgroundColor'));
        $this->assertSame(['08/2026', '09/2026', '10/2026'], $data['labels']);
    }

    public function test_the_chart_is_empty_when_nothing_was_spent(): void
    {
        $this->assertSame([], $this->chart(['department_id' => $this->logistics->getKey()]));

        Livewire::test(MonthlySpendingChart::class, $this->on(['department_id' => $this->logistics->getKey()]))
            ->assertSee('لا توجد مصاريف في هذه الفترة');
    }
}
