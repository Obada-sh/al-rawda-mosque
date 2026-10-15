<?php

namespace App\Services\Market;

use App\Models\Enrollment;
use App\Models\MarketSale;
use App\Models\PointTransaction;
use Illuminate\Support\Collection;

/**
 * What a student can spend at the markets of a course: the points they
 * earned in it, less what they already bought there.
 *
 * Buying never writes to the points ledger, so the statistics, the Quran
 * Knight and the slides keep counting what was earned.
 */
final class PointsBalance
{
    public function of(Enrollment $enrollment): Balance
    {
        return new Balance(
            earned: (int) PointTransaction::query()->where('enrollment_id', $enrollment->getKey())->sum('points'),
            spent: (int) MarketSale::query()->where('enrollment_id', $enrollment->getKey())->sum('total_points'),
        );
    }

    /**
     * The balances of many enrollments at once (two queries).
     *
     * @param  list<int>  $enrollmentIds
     * @return Collection<int, Balance> keyed by enrollment id; every id given is there
     */
    public function ofMany(array $enrollmentIds): Collection
    {
        $earned = PointTransaction::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->groupBy('enrollment_id')
            ->selectRaw('enrollment_id, SUM(points) as total')
            ->pluck('total', 'enrollment_id');

        $spent = MarketSale::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->groupBy('enrollment_id')
            ->selectRaw('enrollment_id, SUM(total_points) as total')
            ->pluck('total', 'enrollment_id');

        return collect($enrollmentIds)->mapWithKeys(fn (int $id): array => [
            $id => new Balance((int) ($earned[$id] ?? 0), (int) ($spent[$id] ?? 0)),
        ]);
    }
}
