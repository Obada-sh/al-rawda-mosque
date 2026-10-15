<?php

namespace Tests\Unit;

use App\Support\Ranking;
use PHPUnit\Framework\TestCase;

class RankingTest extends TestCase
{
    public function test_equal_scores_share_a_place_and_the_next_place_is_skipped(): void
    {
        $scores = ['a' => 10, 'b' => 10, 'c' => 7, 'd' => 5, 'e' => 5, 'f' => 1];

        $this->assertSame(
            ['a' => 1, 'b' => 1, 'c' => 3, 'd' => 4, 'e' => 4, 'f' => 6],
            Ranking::places($scores, fn (int $score): int => $score),
        );
    }

    public function test_without_ties_places_run_from_one(): void
    {
        $this->assertSame([1, 2, 3], Ranking::places([9.5, 9.0, 0.0], fn (float $score): float => $score));
    }

    public function test_an_empty_list_has_no_places(): void
    {
        $this->assertSame([], Ranking::places([], fn (int $score): int => $score));
    }

    public function test_a_single_zero_score_is_still_first(): void
    {
        $this->assertSame([1], Ranking::places([0], fn (int $score): int => $score));
    }
}
