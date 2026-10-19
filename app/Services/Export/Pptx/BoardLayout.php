<?php

namespace App\Services\Export\Pptx;

use InvalidArgumentException;

/**
 * Where the copies of a cell go on a board: as many copies as there are
 * items, in the rows and columns that let them be drawn as large as the
 * area allows.
 *
 * - Every copy has the proportions of the cell drawn in the template; it
 *   is only made larger or smaller, never stretched.
 * - The copies are read from right to left, then from top to bottom: the
 *   first item stands at the top right.
 * - The grid stands in the middle of the area. A last row that is not
 *   full starts on the right, like the rows above it.
 */
final readonly class BoardLayout
{
    /** The space between two cells, as a share of a cell's height. */
    public const GAP = 0.155;

    /** A cell is never drawn more than this much larger than in the template... */
    public const MAX_SCALE = 1.5;

    /** ...nor smaller than this, so that it can still be read from the back of the room. */
    public const MIN_SCALE = 0.5;

    /** No more cells than this ever share a slide, however small the cell. */
    public const MAX_CELLS = 60;

    /**
     * @param  float  $scale  how much larger (above 1) or smaller (below 1) than in the template the cells are drawn
     * @param  list<Box>  $cells  where each copy goes, the first item first
     */
    private function __construct(
        public float $scale,
        public int $columns,
        public int $rows,
        public array $cells,
    ) {}

    /**
     * The layout of a number of cells, or null when they do not fit in the
     * area without being drawn smaller than MIN_SCALE.
     *
     * @param  Box  $area  where the grid may go
     * @param  int  $cellWidth  the width of the cell in the template
     * @param  int  $cellHeight  the height of the cell in the template
     */
    public static function fit(Box $area, int $cellWidth, int $cellHeight, int $count): ?self
    {
        if ($count < 1 || $count > self::MAX_CELLS) {
            return null;
        }

        if ($cellWidth < 1 || $cellHeight < 1 || ! $area->hasRoom()) {
            throw new InvalidArgumentException('A board needs a cell and an area that both have a size.');
        }

        $gap = self::GAP * $cellHeight;
        $best = null;

        for ($columns = 1; $columns <= $count; $columns++) {
            $rows = intdiv($count + $columns - 1, $columns);

            // One more column that saves no row would only leave empty places.
            if ($columns > 1 && intdiv($count + $columns - 2, $columns - 1) === $rows) {
                continue;
            }

            $scale = min(
                $area->width / ($columns * $cellWidth + ($columns - 1) * $gap),
                $area->height / ($rows * $cellHeight + ($rows - 1) * $gap),
                self::MAX_SCALE,
            );

            // On equal size, fewer columns: the names have more room around them.
            if ($best === null || $scale > $best[0] + 1e-9) {
                $best = [$scale, $columns, $rows];
            }
        }

        [$scale, $columns, $rows] = $best;

        if ($scale < self::MIN_SCALE) {
            return null;
        }

        $width = $cellWidth * $scale;
        $height = $cellHeight * $scale;
        $step = $gap * $scale;

        $gridWidth = $columns * $width + ($columns - 1) * $step;
        $gridHeight = $rows * $height + ($rows - 1) * $step;

        $right = $area->x + ($area->width + $gridWidth) / 2;
        $top = $area->y + ($area->height - $gridHeight) / 2;

        $cells = [];

        for ($index = 0; $index < $count; $index++) {
            $row = intdiv($index, $columns);
            $column = $index % $columns;

            $cells[] = new Box(
                (int) round($right - ($column + 1) * $width - $column * $step),
                (int) round($top + $row * ($height + $step)),
                (int) round($width),
                (int) round($height),
            );
        }

        return new self($scale, $columns, $rows, $cells);
    }

    /**
     * How many cells at most one slide can show.
     */
    public static function capacity(Box $area, int $cellWidth, int $cellHeight): int
    {
        $count = 0;

        while ($count < self::MAX_CELLS && self::fit($area, $cellWidth, $cellHeight, $count + 1) !== null) {
            $count++;
        }

        return $count;
    }
}
