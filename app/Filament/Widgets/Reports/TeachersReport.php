<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Support\Reports\ReportRows;
use App\Filament\Widgets\Reports\Concerns\ReadsReportFilter;
use App\Services\Statistics\TeacherStanding;
use App\Services\Statistics\TeacherStandings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * How many pages each teacher heard.
 */
class TeachersReport extends TableWidget
{
    use ReadsReportFilter;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('أساتذة التسميع')
            ->records(fn (?string $sortColumn, ?string $sortDirection): Collection => ReportRows::sort(
                $this->rows(),
                $sortColumn,
                $sortDirection,
            ))
            ->columns([
                TextColumn::make('teacher')
                    ->label('الأستاذ')
                    ->weight('medium')
                    ->sortable(),
                TextColumn::make('pages')
                    ->label('الصفحات المسمَّعة')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('new_pages')
                    ->label('منها جديدة')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('recitations')
                    ->label('مرات التسميع')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('students')
                    ->label('عدد الطلاب')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا يوجد تسميع في هذا النطاق');
    }

    /**
     * @return Collection<int, array<string, mixed>> keyed by teacher id, most pages first
     */
    private function rows(): Collection
    {
        $filter = $this->reportFilter();

        if ($filter === null) {
            return collect();
        }

        return app(TeacherStandings::class)->for($filter)->mapWithKeys(fn (TeacherStanding $teacher): array => [
            $teacher->teacherId => [
                'teacher' => $teacher->teacherName,
                'pages' => $teacher->pages,
                'new_pages' => $teacher->newPages,
                'recitations' => $teacher->recitations,
                'students' => $teacher->students,
            ],
        ]);
    }
}
