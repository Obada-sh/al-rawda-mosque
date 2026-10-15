<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Support\Presenters\ReportPresenter;
use App\Filament\Support\Reports\ReportRows;
use App\Filament\Widgets\Reports\Concerns\ReadsReportFilter;
use App\Services\Statistics\CircleStanding;
use App\Services\Statistics\CircleStandings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * One line per circle: its students, attendance, pages and points. The
 * average per student stands next to every total, because a total alone
 * is unfair to small circles.
 */
class CirclesReport extends TableWidget
{
    use ReadsReportFilter;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('الحلقات')
            ->description('أرقام الحلقة هي مجموع ما أنجزه طلابها الحاليون، ومنه ما أنجزه الطالب قبل نقله إليها.')
            ->records(fn (?string $sortColumn, ?string $sortDirection): Collection => ReportRows::sort(
                $this->rows(),
                $sortColumn,
                $sortDirection,
            ))
            ->columns([
                TextColumn::make('circle')
                    ->label('الحلقة')
                    ->weight('medium')
                    ->sortable(),
                TextColumn::make('course')
                    ->label('الدورة')
                    ->toggleable(),
                TextColumn::make('supervisor')
                    ->label('المشرف')
                    ->placeholder('—')
                    ->toggleable(),
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
                TextColumn::make('average_new_pages')
                    ->label('متوسط الصفحات للطالب')
                    ->formatStateUsing(fn (?float $state): ?string => ReportPresenter::decimal($state))
                    ->placeholder('—')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->weight('bold')
                    ->sortable(),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا توجد حلقات في هذا النطاق');
    }

    /**
     * @return Collection<int, array<string, mixed>> keyed by circle id
     */
    private function rows(): Collection
    {
        $filter = $this->reportFilter();

        if ($filter === null) {
            return collect();
        }

        return app(CircleStandings::class)->for($filter)->mapWithKeys(fn (CircleStanding $circle): array => [
            $circle->circleId => [
                'circle' => $circle->circleName,
                'course' => $circle->courseName,
                'supervisor' => $circle->supervisorName,
                'students' => $circle->students,
                'attendance_rate' => $circle->attendanceRate(),
                'new_pages' => $circle->newPages,
                'average_new_pages' => $circle->averageNewPages(),
                'points' => $circle->points,
                'average_points' => $circle->averagePoints(),
            ],
        ]);
    }
}
