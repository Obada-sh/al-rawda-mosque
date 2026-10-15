<?php

namespace App\Support;

/**
 * Places in a ranking where equal scores share a place.
 *
 * Two students tied for first are both 1st and the next one is 3rd, the
 * way places are counted in a competition.
 */
final class Ranking
{
    /**
     * @template TKey of array-key
     * @template TItem
     *
     * @param  iterable<TKey, TItem>  $items  ordered from the best to the worst
     * @param  callable(TItem): (int|float)  $score
     * @return array<TKey, int> the place of each item, by its key
     */
    public static function places(iterable $items, callable $score): array
    {
        $places = [];
        $seen = 0;
        $place = 0;
        $previous = null;

        foreach ($items as $key => $item) {
            $seen++;
            $value = $score($item);

            if ($seen === 1 || $value !== $previous) {
                $place = $seen;
                $previous = $value;
            }

            $places[$key] = $place;
        }

        return $places;
    }
}
