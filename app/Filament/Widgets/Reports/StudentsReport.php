<?php

namespace App\Filament\Widgets\Reports;

use App\Enums\RankingCriterion;
use App\Filament\Support\Presenters\ReportPresenter;
use App\Filament\Support\Reports\ReportRows;
use App\Filament\Widgets\Reports\Concerns\ReadsReportFilter;
use App\Services\Statistics\RankedStanding;
use App\Services\Statistics\StudentRanking;
use App\Services\Statistics\StudentStandings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The students of the chosen scope, ranked by points, by new pages or by
 * attendance. Narrow the page to a course or to a circle to see the
 * ranking inside it.
 */
class StudentsReport extends TableWidget
{
    use ReadsReportFilter;

    /** The key of the ranking criterion among the filters of the page. */
    public const CRITERION_FILTER = 'rank_by';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->queryStringIdentifier('students')
            ->heading('ترتيب الطلاب')
            ->description(fn (): string => 'الترتيب حسب: '.$this->criterion()->label().'. الطلاب المتساوون يتشاركون المرتبة.')
            ->records(fn (int|string $page, int|string|null $recordsPerPage, ?string $search): LengthAwarePaginator => ReportRows::page(
                ReportRows::search($this->rows(), $search, ['student', 'circle']),
                $page,
                $recordsPerPage,
                $this->getTablePaginationPageName(),
            ))
            ->columns([
                TextColumn::make('place')
                    ->label('المرتبة')
                    ->badge()
                    ->color(fn (int $state): string => ReportPresenter::placeColor($state))
                    ->alignCenter(),
                TextColumn::make('student')
                    ->label('الطالب')
                    ->weight('medium'),
                TextColumn::make('circle')
                    ->label('الحلقة')
                    ->description(fn (array $record): ?string => $record['has_left'] ? 'خرج من الدورة' : null),
                TextColumn::make('course')
                    ->label('الدورة')
                    ->toggleable(),
                TextColumn::make('attendance')
                    ->label('الحضور')
                    ->tooltip('الجلسات التي حضرها من الجلسات التي انعقدت وهو في الدورة')
                    ->alignCenter(),
                TextColumn::make('attendance_rate')
                    ->label('نسبة الحضور')
                    ->formatStateUsing(fn (?float $state): ?string => ReportPresenter::percent($state))
                    ->badge()
                    ->color(fn (?float $state): string => ReportPresenter::attendanceColor($state))
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('new_pages')
                    ->label('صفحات جديدة')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('recited_pages')
                    ->label('كل الصفحات المسمَّعة')
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('points')
                    ->label('النقاط')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold'),
            ])
            ->searchable()
            ->paginationMode(PaginationMode::Default)
            ->emptyStateHeading('لا يوجد طلاب في هذا النطاق');
    }

    /**
     * @return Collection<int, array<string, mixed>> keyed by enrollment id, the first place first
     */
    private function rows(): Collection
    {
        $filter = $this->reportFilter();

        if ($filter === null) {
            return collect();
        }

        $ranked = app(StudentRanking::class)->rank(
            app(StudentStandings::class)->for($filter),
            $this->criterion(),
        );

        return $ranked->mapWithKeys(fn (RankedStanding $ranked): array => [
            $ranked->standing->enrollmentId => [
                'place' => $ranked->place,
                'student' => $ranked->standing->studentName,
                'has_left' => $ranked->standing->hasLeft(),
                'circle' => $ranked->standing->circleName,
                'course' => $ranked->standing->courseName,
                'attendance' => "{$ranked->standing->attended} / {$ranked->standing->sessions}",
                'attendance_rate' => $ranked->standing->attendanceRate(),
                'new_pages' => $ranked->standing->newPages,
                'recited_pages' => $ranked->standing->recitedPages,
                'points' => $ranked->standing->points,
            ],
        ]);
    }

    private function criterion(): RankingCriterion
    {
        $chosen = $this->pageFilters[self::CRITERION_FILTER] ?? null;

        return (is_string($chosen) ? RankingCriterion::tryFrom($chosen) : null) ?? RankingCriterion::Points;
    }
}
