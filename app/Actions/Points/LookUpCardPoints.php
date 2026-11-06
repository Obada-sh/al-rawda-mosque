<?php

namespace App\Actions\Points;

use App\Enums\PointsLookupStatus;
use App\Http\Requests\Points\CardPointsRequest;
use App\Models\English\EnglishEnrollment;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\TeacherCard;
use App\Services\Market\PointsBalance;
use Illuminate\Validation\ValidationException;

/**
 * The points desk: a student's card is scanned, and their points in the
 * card's course are shown, as the sales desk of a market shows them (see
 * PointsBalance): earned, spent at the course's markets, and left; with
 * what they were earned from and the latest lines.
 *
 * It only reads: nothing is recorded. The card of a student who left the
 * course, is archived, or whose course is over still shows its points,
 * with a word on why they no longer grow. A teacher's card, a card of the
 * English section, and anything else are told apart.
 */
final class LookUpCardPoints
{
    /** How many of the student's latest lines are shown. */
    public const LATEST = 5;

    public function __construct(private readonly PointsBalance $balances) {}

    public function handle(?string $cardCode): CardPoints
    {
        try {
            $data = CardPointsRequest::validateData(['card_code' => $cardCode]);
        } catch (ValidationException) {
            return new CardPoints(PointsLookupStatus::UnknownCard);
        }

        $code = $data['card_code'];

        $enrollment = Enrollment::query()
            ->with(['student', 'circle', 'course'])
            ->where('card_code', $code)
            ->first();

        if ($enrollment === null) {
            return new CardPoints(match (true) {
                TeacherCard::query()->where('card_code', $code)->exists() => PointsLookupStatus::TeacherCard,
                EnglishEnrollment::query()->where('card_code', $code)->exists() => PointsLookupStatus::EnglishCard,
                default => PointsLookupStatus::UnknownCard,
            });
        }

        return new CardPoints(
            PointsLookupStatus::Found,
            $enrollment,
            $this->balances->of($enrollment),
            $this->bySource($enrollment),
            PointTransaction::query()
                ->where('enrollment_id', $enrollment->getKey())
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->limit(self::LATEST)
                ->get()
                ->all(),
        );
    }

    /**
     * @return array<string, int> PointSource value => points, in the order of CardPoints::SOURCE_ORDER
     */
    private function bySource(Enrollment $enrollment): array
    {
        $totals = PointTransaction::query()
            ->toBase()
            ->where('enrollment_id', $enrollment->getKey())
            ->groupBy('source')
            ->selectRaw('source, SUM(points) as total')
            ->pluck('total', 'source');

        $bySource = [];

        foreach (CardPoints::SOURCE_ORDER as $source) {
            if ($totals->has($source->value)) {
                $bySource[$source->value] = (int) $totals->get($source->value);
            }
        }

        return $bySource;
    }
}
