<?php

namespace App\Actions\Documents;

use App\Models\Circle;

/**
 * A circle of a course with its current students, ready to print.
 */
final readonly class CircleSheet
{
    /**
     * @param  Circle  $circle  with its course, supervisor and assistant
     * @param  list<SheetLine>  $lines  by student name
     */
    public function __construct(
        public Circle $circle,
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
