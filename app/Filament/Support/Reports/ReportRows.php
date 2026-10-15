<?php

namespace App\Filament\Support\Reports;

use Collator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Sorting, searching and paging for report tables.
 *
 * Report rows are computed figures, not database rows, so the table cannot
 * ask the database to do these for it.
 */
final class ReportRows
{
    /**
     * Orders the rows by one of their columns. Text is compared the Arabic
     * way, and rows without a value always come last.
     *
     * @param  Collection<array-key, array<string, mixed>>  $rows
     * @return Collection<array-key, array<string, mixed>>
     */
    public static function sort(Collection $rows, ?string $column, ?string $direction): Collection
    {
        if (blank($column)) {
            return $rows;
        }

        $collator = new Collator('ar');
        $factor = $direction === 'desc' ? -1 : 1;

        return $rows->sort(function (array $first, array $second) use ($column, $collator, $factor): int {
            $a = $first[$column] ?? null;
            $b = $second[$column] ?? null;

            if ($a === null || $b === null) {
                return ($a === null) <=> ($b === null);
            }

            $order = is_string($a) || is_string($b)
                ? (int) $collator->compare((string) $a, (string) $b)
                : $a <=> $b;

            return $factor * $order;
        });
    }

    /**
     * Keeps the rows in which one of the given columns contains the text.
     *
     * @param  Collection<array-key, array<string, mixed>>  $rows
     * @param  list<string>  $columns
     * @return Collection<array-key, array<string, mixed>>
     */
    public static function search(Collection $rows, ?string $search, array $columns): Collection
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $rows;
        }

        return $rows->filter(function (array $row) use ($search, $columns): bool {
            foreach ($columns as $column) {
                if (mb_stripos((string) ($row[$column] ?? ''), $search) !== false) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * One page of the rows, with what the table needs to draw its pager.
     *
     * @param  Collection<array-key, array<string, mixed>>  $rows
     * @param  int|string|null  $perPage  a number of rows, or "all"
     */
    public static function page(Collection $rows, int|string $page, int|string|null $perPage, string $pageName): LengthAwarePaginator
    {
        $total = $rows->count();
        $perPage = is_numeric($perPage) ? max(1, (int) $perPage) : max(1, $total);
        $page = max(1, (int) $page);

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage),
            $total,
            $perPage,
            $page,
            ['pageName' => $pageName],
        );
    }
}
