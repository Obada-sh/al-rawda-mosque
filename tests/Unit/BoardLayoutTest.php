<?php

namespace Tests\Unit;

use App\Services\Export\Pptx\BoardLayout;
use App\Services\Export\Pptx\Box;
use App\Services\Export\Pptx\TextWidth;
use PHPUnit\Framework\TestCase;

/**
 * Where the copies of a cell go on a board, on the area and the cell of
 * the default design: a name and its number, 3.79 by 0.98 million EMU,
 * in an area made for three columns of four rows.
 */
class BoardLayoutTest extends TestCase
{
    private const CELL_WIDTH = 3791980;

    private const CELL_HEIGHT = 984739;

    private static function area(): Box
    {
        return new Box(567849, 2139459, 11481798, 4396156);
    }

    private static function layout(int $count): ?BoardLayout
    {
        return BoardLayout::fit(self::area(), self::CELL_WIDTH, self::CELL_HEIGHT, $count);
    }

    public function test_the_design_s_own_grid_comes_back_for_twelve_students(): void
    {
        $layout = self::layout(12);

        $this->assertSame([3, 4], [$layout?->columns, $layout?->rows]);
        $this->assertEqualsWithDelta(1.0, (float) $layout?->scale, 0.05);
    }

    public function test_the_cells_are_read_from_right_to_left_then_down(): void
    {
        $layout = self::layout(5);
        $cells = $layout->cells ?? [];

        // Five students: two columns of three rows, the last row starting on the right.
        $this->assertSame([2, 3], [$layout?->columns, $layout?->rows]);
        $this->assertCount(5, $cells);
        $this->assertGreaterThan($cells[1]->x, $cells[0]->x);
        $this->assertSame($cells[0]->y, $cells[1]->y);
        $this->assertSame($cells[0]->x, $cells[2]->x);
        $this->assertSame($cells[1]->x, $cells[3]->x);
        $this->assertGreaterThan($cells[0]->y, $cells[2]->y);
        $this->assertSame($cells[0]->x, $cells[4]->x);
        $this->assertGreaterThan($cells[2]->y, $cells[4]->y);
    }

    public function test_one_student_is_drawn_large_in_the_middle(): void
    {
        $layout = self::layout(1);
        $cell = $layout?->cells[0];
        $area = self::area();

        $this->assertSame(BoardLayout::MAX_SCALE, $layout?->scale);
        $this->assertEqualsWithDelta($area->x + $area->width / 2, $cell->x + $cell->width / 2, 1);
        $this->assertEqualsWithDelta($area->y + $area->height / 2, $cell->y + $cell->height / 2, 1);
    }

    public function test_every_cell_keeps_the_proportions_of_the_template_and_stays_in_the_area(): void
    {
        $area = self::area();

        foreach ([1, 2, 3, 7, 12, 13, 20, 30] as $count) {
            $layout = self::layout($count);

            $this->assertNotNull($layout, "{$count} students");

            foreach ($layout->cells as $cell) {
                $this->assertEqualsWithDelta(self::CELL_WIDTH / self::CELL_HEIGHT, $cell->width / $cell->height, 0.01);
                $this->assertGreaterThanOrEqual($area->x - 1, $cell->x);
                $this->assertLessThanOrEqual($area->right() + 1, $cell->right());
                $this->assertGreaterThanOrEqual($area->y - 1, $cell->y);
                $this->assertLessThanOrEqual($area->bottom() + 1, $cell->bottom());
            }

            // No two cells overlap.
            foreach ($layout->cells as $index => $cell) {
                foreach (array_slice($layout->cells, $index + 1) as $other) {
                    $apart = $cell->right() <= $other->x || $other->right() <= $cell->x
                        || $cell->bottom() <= $other->y || $other->bottom() <= $cell->y;

                    $this->assertTrue($apart, "{$count} students");
                }
            }
        }
    }

    public function test_the_more_students_the_smaller_the_cells(): void
    {
        $previous = INF;

        foreach (range(1, 30) as $count) {
            $scale = (float) self::layout($count)?->scale;

            $this->assertLessThanOrEqual($previous, $scale);
            $previous = $scale;
        }
    }

    public function test_a_slide_holds_as_many_students_as_stay_readable(): void
    {
        $capacity = BoardLayout::capacity(self::area(), self::CELL_WIDTH, self::CELL_HEIGHT);

        $this->assertGreaterThanOrEqual(30, $capacity);
        $this->assertLessThanOrEqual(BoardLayout::MAX_CELLS, $capacity);
        $this->assertGreaterThanOrEqual(BoardLayout::MIN_SCALE, (float) self::layout($capacity)?->scale);
        $this->assertNull(self::layout($capacity + 1));
        $this->assertNull(self::layout(0));
    }

    public function test_a_cell_larger_than_the_area_does_not_fit_even_once(): void
    {
        $this->assertNull(BoardLayout::fit(new Box(0, 0, 1000000, 200000), self::CELL_WIDTH, self::CELL_HEIGHT, 1));
        $this->assertSame(0, BoardLayout::capacity(new Box(0, 0, 1000000, 200000), self::CELL_WIDTH, self::CELL_HEIGHT));
    }

    public function test_the_width_of_a_text_is_estimated_by_its_letters(): void
    {
        $this->assertSame(0.0, TextWidth::ems(''));
        $this->assertGreaterThan(TextWidth::ems('أحمد'), TextWidth::ems('عبد الرحمن'));
        // Short vowels sit on the letters and take no room.
        $this->assertSame(TextWidth::ems('محمد'), TextWidth::ems('مُحَمَّد'));
        $this->assertEqualsWithDelta(3 * TextWidth::ems('1'), TextWidth::ems('100'), 0.0001);
        $this->assertGreaterThan(TextWidth::ems(' '), TextWidth::ems('a'));
    }
}
