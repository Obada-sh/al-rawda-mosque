<?php

namespace App\Actions\Documents;

use App\Models\Circle;

/**
 * On the mosque's sheet ordered by circle: one circle with its students,
 * or the students who are in no circle now.
 */
final readonly class MosqueSheetGroup
{
    /**
     * @param  Circle|null  $circle  with its course, supervisor and assistant; null for the students in no circle now
     * @param  list<SheetLine>  $lines  by student name
     */
    public function __construct(
        public ?Circle $circle,
        public array $lines,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function newPages(): int
    {
        return array_sum(array_map(fn (SheetLine $line): int => $line->newPages, $this->lines));
    }

    public function examsPassed(): int
    {
        return array_sum(array_map(fn (SheetLine $line): int => $line->examsPassed(), $this->lines));
    }
}
