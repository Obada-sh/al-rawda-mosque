<?php

namespace App\Actions\Documents;

use App\Models\Course;

/**
 * The students of the mosque on one sheet, by name: every student in the
 * system with their records in every course, or the students of one
 * course with what they did in it.
 */
final readonly class MosqueSheet
{
    /**
     * @param  Course|null  $course  the course of the sheet; null for every course
     * @param  bool  $withArchived  with every course: archived students are on the sheet too
     * @param  list<SheetLine>  $lines  by student name
     */
    public function __construct(
        public SheetOptions $options,
        public ?Course $course,
        public bool $withArchived,
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
