<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Support\Presenters\ReportPresenter;
use App\Filament\Support\Reports\ReportRows;
use App\Filament\Widgets\Reports\Concerns\ReadsReportFilter;
use App\Services\Statistics\CourseStanding;
use App\Services\Statistics\CourseStandings;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * One line per course: its students, attendance, pages and points.
 */
class CoursesReport extends TableWidget
{
    use ReadsReportFilter;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('الدورات')
            ->records(fn (?string $sortColumn, ?string $sortDirection): Collection => ReportRows::sort(
                $this->rows(),
                $sortColumn,
                $sortDirection,
            ))
            ->columns([
                TextColumn::make('course')
                    ->label('الدورة')
                    ->weight('medium')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('نشطة')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('students')
                    ->label('الطلاب')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('attendance_rate')
                    ->label('نسبة الحضور')
                    ->formatStateUsing(fn (?float $state): ?string => ReportPresenter::percent($state))
                    ->badge()
                    ->color(fn (?float $state): string => ReportPresenter::attendanceColor($state))
                    ->placeholder('—')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('new_pages')
                    ->label('صفحات جديدة')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('points')
                    ->label('مجموع النقاط')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('average_points')
                    ->label('متوسط النقاط للطالب')
                    ->formatStateUsing(fn (?float $state): ?string => ReportPresenter::decimal($state))
                    ->placeholder('—')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا توجد دورات في هذا النطاق');
    }

    /**
     * @return Collection<int, array<string, mixed>> keyed by course id
     */
    private function rows(): Collection
    {
        $filter = $this->reportFilter();

        if ($filter === null) {
            return collect();
        }

        return app(CourseStandings::class)->for($filter)->mapWithKeys(fn (CourseStanding $course): array => [
            $course->courseId => [
                'course' => $course->courseName,
                'is_active' => $course->isActive,
                'students' => $course->students,
                'attendance_rate' => $course->attendanceRate(),
                'new_pages' => $course->newPages,
                'points' => $course->points,
                'average_points' => $course->averagePoints(),
            ],
        ]);
    }
}
