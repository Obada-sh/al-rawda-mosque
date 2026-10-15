<?php

namespace App\Services\Statistics;

use App\Models\Circle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The figures of each circle in the scope of a report.
 *
 * A circle's figures are the sum of what its current students did: a
 * student who was moved brings everything they did to the new circle.
 */
final class CircleStandings
{
    public function __construct(private readonly StudentStandings $students) {}

    /**
     * @param  Collection<int, StudentStanding>|null  $standings  rows already loaded for the same filter
     * @return Collection<int, CircleStanding> ordered by course, then by circle name
     */
    public function for(ReportFilter $filter, ?Collection $standings = null): Collection
    {
        $byCircle = ($standings ?? $this->students->for($filter))->groupBy('circleId');

        return Circle::query()
            ->with(['course', 'supervisor'])
            ->when($filter->courseId, fn (Builder $query, int $courseId) => $query->where('course_id', $courseId))
            ->when($filter->circleId, fn (Builder $query, int $circleId) => $query->whereKey($circleId))
            // Without a course, only the circles that have students in the period are listed.
            ->when($filter->courseId === null, fn (Builder $query) => $query->whereKey($byCircle->keys()->all()))
            ->orderByDesc('course_id')
            ->orderBy('name')
            ->get()
            ->map(function (Circle $circle) use ($byCircle): CircleStanding {
                /** @var Collection<int, StudentStanding> $members */
                $members = $byCircle->get($circle->getKey(), collect());

                return new CircleStanding(
                    circleId: $circle->getKey(),
                    circleName: $circle->name,
                    courseId: $circle->course_id,
                    courseName: $circle->course->name,
                    supervisorName: $circle->supervisor?->full_name,
                    students: $members->count(),
                    newPages: (int) $members->sum('newPages'),
                    recitedPages: (int) $members->sum('recitedPages'),
                    points: (int) $members->sum('points'),
                    attended: (int) $members->sum('attended'),
                    sessions: (int) $members->sum('sessions'),
                );
            });
    }
}
