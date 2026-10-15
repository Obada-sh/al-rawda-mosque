<?php

namespace App\Services\Statistics;

/**
 * One student's place in the Quran Knight ranking.
 */
final readonly class KnightEntry
{
    /**
     * @param  int  $place  1 for the first; students with the same pages share a place
     * @param  list<string>  $circles  where the pages were recited, e.g. "حلقة النور (دورة الصيف)"
     */
    public function __construct(
        public int $place,
        public int $studentId,
        public string $studentName,
        public array $circles,
        public int $newPages,
    ) {}
}
