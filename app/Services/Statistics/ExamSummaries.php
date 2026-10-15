<?php

namespace App\Services\Statistics;

use App\Enums\ExamStatus;
use App\Models\JuzExam;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Juz exams per circle in the scope of a report.
 *
 * Waiting exams are counted whatever the period (they are waiting now);
 * taken exams are counted by the date they were taken.
 */
final class ExamSummaries
{
    /**
     * @return Collection<int, ExamSummary> ordered by course, then by circle name
     */
    public function for(ReportFilter $filter): Collection
    {
        $narrowsToCourse = $filter->courseId !== null || $filter->circleId !== null;

        $exams = JuzExam::query()
            ->with(['enrollment.circle', 'enrollment.course'])
            ->when($narrowsToCourse, fn (Builder $query) => $query->whereHas('enrollment', function (Builder $query) use ($filter): void {
                if ($filter->courseId !== null) {
                    $query->where('course_id', $filter->courseId);
                }

                if ($filter->circleId !== null) {
                    $query->where('circle_id', $filter->circleId);
                }
            }))
            ->where(fn (Builder $query) => $query
                ->where('status', ExamStatus::Pending->value)
                ->orWhere(function (Builder $query) use ($filter): void {
                    $query->where('status', ExamStatus::Done->value);
                    $filter->applyPeriod($query, 'exam_date');
                }))
            ->get();

        return $exams
            ->groupBy(fn (JuzExam $exam): int => $exam->enrollment?->circle_id ?? 0)
            ->map(function (Collection $group, int $circleId): ExamSummary {
                /** @var JuzExam $first */
                $first = $group->first();
                $taken = $group->reject(fn (JuzExam $exam): bool => $exam->isPending());

                return new ExamSummary(
                    circleId: $circleId === 0 ? null : $circleId,
                    circleName: $first->enrollment?->circle->name,
                    courseName: $first->enrollment?->course->name,
                    pending: $group->count() - $taken->count(),
                    done: $taken->count(),
                    grades: $taken
                        ->filter(fn (JuzExam $exam): bool => $exam->grade !== null)
                        ->countBy(fn (JuzExam $exam): string => $exam->grade->value)
                        ->all(),
                );
            })
            ->sortBy(fn (ExamSummary $summary): array => [
                $summary->circleId === null ? 1 : 0,
                $summary->courseName ?? '',
                $summary->circleName ?? '',
            ])
            ->values();
    }
}
