<?php

namespace App\Services\Cards;

use App\Contracts\CardCodeGenerator;
use App\Models\English\EnglishEnrollment;
use App\Models\Enrollment;
use App\Models\TeacherCard;

/**
 * Random 10-digit codes. Random (not sequential) so a card cannot be
 * guessed from the one next to it.
 *
 * Students' and teachers' cards share the scan screen, so a code is never
 * one that a student's or a teacher's card already has. Nor is it one of
 * the English section's cards: a card scanned in the wrong section is
 * recognised as the other section's, never taken for someone else's.
 */
final class NumericCardCodeGenerator implements CardCodeGenerator
{
    private const MIN = 1_000_000_000;

    private const MAX = 9_999_999_999;

    public function generate(): string
    {
        do {
            $code = (string) random_int(self::MIN, self::MAX);
        } while (
            Enrollment::query()->where('card_code', $code)->exists()
            || TeacherCard::query()->where('card_code', $code)->exists()
            || EnglishEnrollment::query()->where('card_code', $code)->exists()
        );

        return $code;
    }
}
