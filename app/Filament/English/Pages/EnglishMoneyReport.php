<?php

namespace App\Filament\English\Pages;

use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Resources\EnglishExpenses\EnglishExpenseResource;
use App\Filament\English\Support\EnglishOptions;
use App\Filament\English\Widgets\EnglishLetterhead;
use App\Filament\English\Widgets\Money\ExpensesByDepartmentTable;
use App\Filament\English\Widgets\Money\FinanceStats;
use App\Filament\English\Widgets\Money\IncomeByCourseTable;
use App\Filament\English\Widgets\Money\MonthlyMoneyTable;
use App\Filament\Pages\Reports\FilteredDashboard;
use App\Filament\Support\Reports\ReportFilterFields;
use App\Services\English\EnglishFinanceFilter;
use App\Support\Dates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The English section's money over a period: what the students paid,
 * what the section spent, and what is left, month by month, by course and
 * by department.
 */
class EnglishMoneyReport extends FilteredDashboard
{
    /** The report opens on the last twelve months. */
    private const DEFAULT_MONTHS = 12;

    protected static string $routePath = 'money-report';

    protected static ?string $title = 'الإيرادات والمصاريف';

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Finance;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?int $navigationSort = 25;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->extraAttributes(['class' => 'rawda-screen-only'])
                    ->columns(['md' => 4])
                    ->columnSpanFull()
                    ->schema([
                        ReportFilterFields::from()
                            ->default(fn (): string => Dates::today()
                                ->startOfMonth()
                                ->subMonths(self::DEFAULT_MONTHS - 1)
                                ->toDateString()),
                        ReportFilterFields::until()
                            ->default(fn (): string => Dates::today()->toDateString()),
                        Select::make('english_course_id')
                            ->label('دفعات دورة')
                            ->placeholder('كل الدورات')
                            ->options(fn (): array => EnglishOptions::courses())
                            ->searchable(),
                        Select::make('english_department_id')
                            ->label('مصاريف قسم')
                            ->placeholder('كل الأقسام')
                            ->options(fn (): array => EnglishOptions::allDepartments()),
                    ]),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EnglishLetterhead::make(['heading' => (string) $this->getTitle(), 'withPeriod' => true]),
        ];
    }

    public function getWidgets(): array
    {
        return [
            FinanceStats::class,
            MonthlyMoneyTable::class,
            IncomeByCourseTable::class,
            ExpensesByDepartmentTable::class,
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
                ->url(fn (): string => EnglishExpenseResource::getUrl()),
        ];
    }

    protected function checkFilters(array $filters): void
    {
        EnglishFinanceFilter::fromInput($filters);
    }
}
