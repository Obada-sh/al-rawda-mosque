<?php

namespace App\Actions\Documents;

use Illuminate\Support\Collection;

/**
 * The sheets of one circle, or of every circle of a course, with what
 * they were asked to show.
 */
final readonly class PrintedCircleSheets
{
    /**
     * @param  Collection<int, CircleSheet>  $sheets  by circle name
     */
    public function __construct(
        public SheetOptions $options,
        public Collection $sheets,
    ) {}
}
