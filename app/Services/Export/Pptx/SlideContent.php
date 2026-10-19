<?php

namespace App\Services\Export\Pptx;

/**
 * What one copy of the repeated slide is filled with.
 */
final readonly class SlideContent
{
    /**
     * @param  array<string, string>  $values  placeholder name (without braces) => text
     * @param  array<int, int>|null  $slotPlaces  the place held by each filled slot (slot number => place). The shapes
     *                                            named after an empty slot are removed, and so are those made for another
     *                                            place than the slot's. Null keeps every shape.
     * @param  list<array<string, string>>|null  $cells  on a board, the values of each copy of the cell, the first item
     *                                                   first (see SlideBoard). Null on a slide that is not a board.
     */
    public function __construct(
        public array $values,
        public ?array $slotPlaces = null,
        public ?array $cells = null,
    ) {}
}
