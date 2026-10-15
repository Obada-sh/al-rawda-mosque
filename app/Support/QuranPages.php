<?php

namespace App\Support;

/**
 * Page numbering of the Madinah mushaf used by the mosque.
 */
final class QuranPages
{
    public const FIRST = 1;

    public const LAST = 604;

    public const JUZ_COUNT = 30;

    /**
     * First page of each juz in the Madinah mushaf (604 pages).
     * A juz ends on the page before the next one starts; juz 30 ends on 604.
     *
     * @var array<int, int> juz number => first page
     */
    public const JUZ_START_PAGES = [
        1 => 1, 2 => 22, 3 => 42, 4 => 62, 5 => 82, 6 => 102,
        7 => 121, 8 => 142, 9 => 162, 10 => 182, 11 => 201, 12 => 222,
        13 => 242, 14 => 262, 15 => 282, 16 => 302, 17 => 322, 18 => 342,
        19 => 362, 20 => 382, 21 => 402, 22 => 422, 23 => 442, 24 => 462,
        25 => 482, 26 => 502, 27 => 522, 28 => 542, 29 => 562, 30 => 582,
    ];

    /**
     * @return list<array{juz_no: int, from_page: int, to_page: int}>
     */
    public static function juzRanges(): array
    {
        $ranges = [];

        foreach (self::JUZ_START_PAGES as $juzNo => $fromPage) {
            $ranges[] = [
                'juz_no' => $juzNo,
                'from_page' => $fromPage,
                'to_page' => (self::JUZ_START_PAGES[$juzNo + 1] ?? self::LAST + 1) - 1,
            ];
        }

        return $ranges;
    }

    public static function isValidPage(int $page): bool
    {
        return $page >= self::FIRST && $page <= self::LAST;
    }
}
