<?php

namespace App\Filament\Widgets\Reports;

use App\Enums\ExamGrade;
use App\Filament\Resources\JuzExams\JuzExamResource;
use App\Filament\Widgets\Reports\Concerns\ReadsReportFilter;
use App\Services\Statistics\ExamSummaries;
use App\Services\Statistics\ExamSummary;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Juz exams per circle: how many are waiting, and how the exams taken in
 * the period went. The result of each student is on the exams page.
 */
class ExamsReport extends TableWidget
{
    use ReadsReportFilter;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('السبر')
            ->description('المنتظرون يُحسبون مهما كانت الفترة، والسبر المنجز يُحسب بتاريخ إجرائه.')
            ->headerActions([
                Action::make('exams')
                    ->label('نتائج الطلاب')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('gray')
                    ->url(fn (): string => JuzExamResource::getUrl('index', ['tab' => 'all'])),
            ])
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('circle')
                    ->label('الحلقة')
                    ->weight('medium')
                    ->placeholder('بدون دورة'),
                TextColumn::make('course')
                    ->label('الدورة')
                    ->placeholder('—'),
                TextColumn::make('pending')
                    ->label('بانتظار السبر')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->alignCenter(),
                TextColumn::make('done')
                    ->label('تم')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold'),
                ...array_map(
                    fn (ExamGrade $grade): TextColumn => TextColumn::make("grade_{$grade->value}")
                        ->label($grade->label())
                        ->numeric()
                        ->alignCenter(),
                    ExamGrade::cases(),
                ),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا يوجد سبر في هذا النطاق');
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function rows(): Collection
    {
        $filter = $this->reportFilter();

        if ($filter === null) {
            return collect();
        }

        return app(ExamSummaries::class)->for($filter)->mapWithKeys(function (ExamSummary $summary): array {
            $row = [
                'circle' => $summary->circleName,
                'course' => $summary->courseName,
                'pending' => $summary->pending,
                'done' => $summary->done,
            ];

            foreach (ExamGrade::cases() as $grade) {
                $row["grade_{$grade->value}"] = $summary->countOf($grade);
            }

            return ['circle-'.($summary->circleId ?? 'none') => $row];
        });
    }
}
