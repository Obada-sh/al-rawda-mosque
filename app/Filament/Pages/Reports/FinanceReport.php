<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Support\Options;
use App\Filament\Support\Reports\ReportFilterFields;
use App\Filament\Widgets\Finance\MonthlySpendingChart;
use App\Filament\Widgets\Finance\SpendingByDepartment;
use App\Filament\Widgets\Finance\SpendingByMonth;
use App\Filament\Widgets\Finance\SpendingTotalsStats;
use App\Filament\Widgets\Finance\TopItems;
use App\Services\Finance\ExpenseFilter;
use App\Support\Dates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The expenses report: the total of a period, and how it splits between
 * departments, months and items. The expenses themselves are listed on the
 * expenses page.
 */
class FinanceReport extends FilteredDashboard
{
    /** The report opens on the last twelve months. */
    private const DEFAULT_MONTHS = 12;

    protected static string $routePath = 'finance-report';

    protected static ?string $title = 'تقرير المصاريف';

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Finance;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?int $navigationSort = 15;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    // The filter is for the screen: on paper the letterhead says what was chosen.
                    ->extraAttributes(['class' => 'rawda-screen-only'])
                    ->columns(['md' => 3])
                    ->columnSpanFull()
                    ->schema([
                        ReportFilterFields::from()
                            ->default(fn (): string => Dates::today()
                                ->startOfMonth()
                                ->subMonths(self::DEFAULT_MONTHS - 1)
                                ->toDateString()),
                        ReportFilterFields::until()
                            ->default(fn (): string => Dates::today()->toDateString()),
                        Select::make('department_id')
                            ->label('القسم')
                            ->placeholder('كل الأقسام')
                            ->options(fn (): array => Options::allDepartments()),
                    ]),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            SpendingTotalsStats::class,
            MonthlySpendingChart::class,
            SpendingByDepartment::class,
            SpendingByMonth::class,
            TopItems::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
            Action::make('expenses')
                ->label('قائمة المصاريف')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->url(fn (): string => ExpenseResource::getUrl()),
        ];
    }

    protected function checkFilters(array $filters): void
    {
        ExpenseFilter::fromInput($filters);
    }
}
