<?php

namespace App\Support;

/**
 * Pages of the Quran written the short way: the pages that follow each
 * other become one range. [1, 2, 3, 4, 7, 9, 10] is "1–4، 7، 9–10".
 */
final class PageRanges
{
    private const DASH = '–';

    private const COMMA = '، ';

    /**
     * @param  iterable<int>  $pages  in any order; a page given twice counts once
     */
    public static function of(iterable $pages): string
    {
        $sorted = [];

        foreach ($pages as $page) {
            $sorted[(int) $page] = true;
        }

        ksort($sorted);

        $ranges = [];
        $start = null;
        $previous = null;

        foreach (array_keys($sorted) as $page) {
            if ($previous !== null && $page === $previous + 1) {
                $previous = $page;

                continue;
            }

            if ($start !== null) {
                $ranges[] = self::range($start, $previous);
            }

            $start = $previous = $page;
        }

        if ($start !== null) {
            $ranges[] = self::range($start, $previous);
        }

        return implode(self::COMMA, $ranges);
    }

    private static function range(int $from, int $to): string
    {
        return $from === $to ? (string) $from : $from.self::DASH.$to;
    }
}
