<?php

namespace App\Services\Statistics;

use App\Support\Ranking;
use Illuminate\Support\Collection;

/**
 * The Quran Knight ranking: the students who recited the most new pages
 * in a period, in the whole mosque or in one course or circle.
 *
 * Only pages recited for the first time count; a repeated page never
 * does. Students with the same number of pages share a place.
 */
final class QuranKnight
{
    public function __construct(private readonly StudentStandings $students) {}

    /**
     * @return Collection<int, KnightEntry> the first place first; students without a new page are left out
     */
    public function ranking(ReportFilter $filter): Collection
    {
        $ordered = $this->students->for($filter)
            ->where('newPages', '>', 0)
            ->groupBy('studentId')
            ->map(fn (Collection $courses): array => [
                'studentId' => $courses->first()->studentId,
                'studentName' => $courses->first()->studentName,
                'circles' => $courses
                    ->map(fn (StudentStanding $standing): string => "{$standing->circleName} ({$standing->courseName})")
                    ->values()
                    ->all(),
                'newPages' => (int) $courses->sum('newPages'),
            ])
            // The rows arrive ordered by name, and a stable sort keeps that order between equals.
            ->sortByDesc('newPages')
            ->values();

        $places = Ranking::places($ordered, fn (array $row): int => $row['newPages']);

        return $ordered->map(fn (array $row, int $index): KnightEntry => new KnightEntry(
            place: $places[$index],
            studentId: $row['studentId'],
            studentName: $row['studentName'],
            circles: $row['circles'],
            newPages: $row['newPages'],
        ));
    }
}
