<?php

namespace App\Filament\Widgets\Finance;

use App\Filament\Support\Theme;
use App\Filament\Widgets\Finance\Concerns\ReadsExpenseFilter;
use App\Models\Department;
use App\Services\Finance\ExpenseReport;
use App\Services\Finance\MonthSpending;
use App\Support\Dates;
use App\Support\Money;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

/**
 * What was spent each month, split between the departments: one column
 * per month, one coloured segment per department.
 *
 * A department keeps its colour whatever the filter, and when there are
 * more departments than colours the last ones share the colour of "أخرى".
 * The same figures are in the table of months under the chart.
 */
class MonthlySpendingChart extends ChartWidget
{
    use ReadsExpenseFilter;

    private const OTHERS = 'أخرى';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'الصرف الشهري حسب القسم';

    protected ?string $maxHeight = '320px';

    protected ?string $emptyStateHeading = 'لا توجد مصاريف في هذه الفترة';

    public function getChartAssistiveContent(): string
    {
        return 'أعمدة تبيّن ما صُرف في كل شهر موزعاً على الأقسام. الأرقام نفسها في جدول «الإجمالي لكل شهر».';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $filter = $this->expenseFilter();

        if ($filter === null) {
            return [];
        }

        $months = app(ExpenseReport::class)->byMonth($filter);

        if ($months->isEmpty()) {
            return [];
        }

        return [
            'labels' => $months
                ->map(fn (MonthSpending $month): string => Dates::showMonth($month->year(), $month->monthNumber()))
                ->all(),
            'datasets' => $this->datasets($months),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $font = ['family' => "'".Theme::FONT_FAMILY."', sans-serif"];

        return [
            // Digits 0-9 with thousands separators, as everywhere else.
            'locale' => 'en-US',
            // Pointing at a month shows all its departments together.
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => [
                    'rtl' => true,
                    'textDirection' => 'rtl',
                    'labels' => ['font' => $font, 'usePointStyle' => true, 'boxHeight' => 8],
                ],
                'tooltip' => [
                    'rtl' => true,
                    'textDirection' => 'rtl',
                    'titleFont' => $font,
                    'bodyFont' => $font,
                ],
            ],
            'scales' => [
                // Arabic reads from the right: time runs right to left and the scale stands on the right.
                'x' => [
                    'stacked' => true,
                    'reverse' => true,
                    'grid' => ['display' => false],
                    // On a narrow screen every other month is named, instead of all of them at an angle.
                    'ticks' => ['font' => $font, 'maxRotation' => 0, 'autoSkipPadding' => 8],
                ],
                'y' => [
                    'stacked' => true,
                    'position' => 'right',
                    'beginAtZero' => true,
                    'ticks' => ['font' => $font],
                    'title' => ['display' => true, 'text' => Money::CURRENCY, 'font' => $font],
                ],
            ],
        ];
    }

    /**
     * One series per department that spent something in the period.
     *
     * @param  Collection<int, MonthSpending>  $months
     * @return list<array<string, mixed>>
     */
    private function datasets(Collection $months): array
    {
        $datasets = [];

        foreach ($this->seriesOfDepartments() as $slot => $series) {
            $data = $months
                ->map(fn (MonthSpending $month): float => array_sum(array_map(
                    fn (int $departmentId): float => (float) $month->of($departmentId),
                    $series['departments'],
                )))
                ->all();

            if (array_sum($data) <= 0) {
                continue;
            }

            $datasets[] = [
                'label' => $series['label'],
                'data' => $data,
                'backgroundColor' => Theme::SERIES_COLORS[$slot],
                // A thin line of the card's own colour separates the segments of a column.
                'borderColor' => Theme::SURFACE,
                'borderWidth' => ['top' => 2, 'right' => 0, 'bottom' => 0, 'left' => 0],
                'borderRadius' => 4,
                'maxBarThickness' => 24,
            ];
        }

        return $datasets;
    }

    /**
     * Every department takes the next colour, in the order the departments
     * were created, so its colour never changes. Past the last colour,
     * the remaining departments are shown together as "أخرى".
     *
     * @return array<int, array{label: string, departments: list<int>}> colour slot => series
     */
    private function seriesOfDepartments(): array
    {
        $lastSlot = count(Theme::SERIES_COLORS) - 1;
        $departments = Department::query()->orderBy('id')->pluck('name', 'id');
        $series = [];

        foreach ($departments->keys()->values() as $position => $departmentId) {
            $slot = min($position, $lastSlot);
            $isShared = $position >= $lastSlot && $departments->count() > count(Theme::SERIES_COLORS);

            $series[$slot] ??= [
                'label' => $isShared ? self::OTHERS : $departments[$departmentId],
                'departments' => [],
            ];
            $series[$slot]['departments'][] = (int) $departmentId;
        }

        return $series;
    }
}
