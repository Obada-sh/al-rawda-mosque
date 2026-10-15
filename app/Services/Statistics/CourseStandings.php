<?php

namespace App\Services\Statistics;

use App\Models\Course;
use Illuminate\Support\Collection;

/**
 * The figures of each course in the scope of a report.
 */
final class CourseStandings
{
    public function __construct(private readonly StudentStandings $students) {}

    /**
     * @param  Collection<int, StudentStanding>|null  $standings  rows already loaded for the same filter
     * @return Collection<int, CourseStanding> newest course first
     */
    public function for(ReportFilter $filter, ?Collection $standings = null): Collection
    {
        $byCourse = ($standings ?? $this->students->for($filter))->groupBy('courseId');

        return Course::query()
            ->whereKey($byCourse->keys()->all())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Course $course) use ($byCourse): CourseStanding {
                /** @var Collection<int, StudentStanding> $members */
                $members = $byCourse->get($course->getKey(), collect());

                return new CourseStanding(
                    courseId: $course->getKey(),
                    courseName: $course->name,
                    isActive: $course->isActive(),
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
