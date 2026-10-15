<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Support\Presenters\ReportPresenter;
use App\Filament\Support\Reports\ReportRows;
use App\Filament\Widgets\Reports\Concerns\ReadsReportFilter;
use App\Services\Statistics\TeacherAttendanceStanding;
use App\Services\Statistics\TeacherAttendanceStandings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * How often each supervisor and assistant came, out of the sessions held
 * in the courses of their circles.
 */
class TeacherAttendanceReport extends TableWidget
{
    use ReadsReportFilter;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('حضور الأساتذة')
            ->description('المشرفون والمساعدون في حلقات النطاق المختار: ما حضروه من الجلسات التي أقيمت في دوراتهم.')
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
                TextColumn::make('roles')
                    ->label('عمله')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('sessions')
                    ->label('الجلسات')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('attended')
                    ->label('حضر')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('absences')
                    ->label('غاب')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('rate')
                    ->label('نسبة الحضور')
                    ->formatStateUsing(fn (?float $state): ?string => ReportPresenter::percent($state))
                    ->badge()
                    ->color(fn (?float $state): string => ReportPresenter::attendanceColor($state))
                    ->placeholder('—')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('last_attended_on')
                    ->label('آخر حضور')
                    ->date()
                    ->placeholder('—')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا يوجد أساتذة لحلقات هذا النطاق');
    }

    /**
     * @return Collection<int, array<string, mixed>> keyed by teacher id
     */
    private function rows(): Collection
    {
        $filter = $this->reportFilter();

        if ($filter === null) {
            return collect();
        }

        return app(TeacherAttendanceStandings::class)->for($filter)->mapWithKeys(fn (TeacherAttendanceStanding $teacher): array => [
            $teacher->teacherId => [
                'teacher' => $teacher->teacherName,
                'roles' => $teacher->roles,
                'sessions' => $teacher->sessions,
                'attended' => $teacher->attended,
                'absences' => $teacher->absences(),
                'rate' => $teacher->rate(),
                'last_attended_on' => $teacher->lastAttendedOn,
            ],
        ]);
    }
}
