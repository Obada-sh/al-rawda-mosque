<?php

namespace App\Services\English;

use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishPayment;
use Illuminate\Support\Collection;

/**
 * What each student owes for an English course: the price due (the
 * student's own, or the course's), what was paid, and what remains.
 * Amounts are whole Syrian pounds.
 */
final class EnglishBalances
{
    public function of(EnglishEnrollment $enrollment): EnglishBalance
    {
        $enrollment->loadMissing('course');

        return new EnglishBalance(
            due: $enrollment->duePrice(),
            paid: (int) EnglishPayment::query()->where('english_enrollment_id', $enrollment->getKey())->sum('amount'),
        );
    }

    /**
     * The balances of many enrollments, with one query for all the payments.
     *
     * @param  Collection<int, EnglishEnrollment>  $enrollments  with their course loaded
     * @return array<int, EnglishBalance> by enrollment id
     */
    public function ofMany(Collection $enrollments): array
    {
        $paid = EnglishPayment::query()
            ->whereIn('english_enrollment_id', $enrollments->modelKeys())
            ->groupBy('english_enrollment_id')
            ->selectRaw('english_enrollment_id, SUM(amount) AS paid')
            ->pluck('paid', 'english_enrollment_id');

        $balances = [];

        foreach ($enrollments as $enrollment) {
            $balances[$enrollment->getKey()] = new EnglishBalance(
                due: $enrollment->duePrice(),
                paid: (int) ($paid[$enrollment->getKey()] ?? 0),
            );
        }

        return $balances;
    }
}
