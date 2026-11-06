<?php

namespace App\Actions\Documents;

use App\Models\Course;

/**
 * The students of the mosque on one sheet: every student in the system
 * with their records in every course, or the students of one course with
 * what they did in it. In one table by name, or circle by circle.
 */
final readonly class MosqueSheet
{
    /** One table, by student name; each student with their circle. */
    public const BY_NAME = 'name';

    /** Circle by circle: under each circle's name its students, by name. */
    public const BY_CIRCLE = 'circles';

    /**
     * @param  Course|null  $course  the course of the sheet; null for every course
     * @param  bool  $withArchived  with every course: archived students are on the sheet too
     * @param  list<SheetLine>  $lines  by student name, each student once
     * @param  bool  $byCircle  the sheet is printed circle by circle
     * @param  list<MosqueSheetGroup>  $groups  circle by circle, when the sheet is printed so: the circles that have students, then those in no circle; empty otherwise
     */
    public function __construct(
        public SheetOptions $options,
        public ?Course $course,
        public bool $withArchived,
        public array $lines,
        public bool $byCircle = false,
        public array $groups = [],
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
