<?php

namespace App\Filament\Pages\Reports;

use App\Enums\RankingCriterion;
use App\Filament\Enums\PanelGroup;
use App\Filament\Support\Options;
use App\Filament\Support\Reports\ReportFilterFields;
use App\Filament\Widgets\Reports\CirclesReport;
use App\Filament\Widgets\Reports\CoursesReport;
use App\Filament\Widgets\Reports\ExamsReport;
use App\Filament\Widgets\Reports\ScopeTotalsStats;
use App\Filament\Widgets\Reports\StudentsReport;
use App\Filament\Widgets\Reports\TeacherAttendanceReport;
use App\Filament\Widgets\Reports\TeachersReport;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Attendance, pages, points, rankings, teachers and exams, for the whole
 * mosque or narrowed to a course, a circle and a period.
 */
class Statistics extends ReportDashboard
{
    protected static string $routePath = 'statistics';

    protected static ?string $title = 'الإحصائيات';

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Reports;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 10;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    // The filter is for the screen: on paper the letterhead says what was chosen.
                    ->extraAttributes(['class' => 'rawda-screen-only'])
                    ->columns(['md' => 2, 'xl' => 5])
                    ->columnSpanFull()
                    ->schema([
                        ReportFilterFields::course()
                            ->default(fn (): ?int => Options::soleActiveCourseId()),
                        ReportFilterFields::circle(),
                        ReportFilterFields::from(),
                        ReportFilterFields::until(),
                        Select::make(StudentsReport::CRITERION_FILTER)
                            ->label('ترتيب الطلاب حسب')
                            ->options(RankingCriterion::options())
                            ->default(RankingCriterion::Points->value)
                            ->selectablePlaceholder(false),
                    ]),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            ScopeTotalsStats::class,
            CoursesReport::class,
            CirclesReport::class,
            StudentsReport::class,
            TeachersReport::class,
            TeacherAttendanceReport::class,
            ExamsReport::class,
        ];
    }
}
