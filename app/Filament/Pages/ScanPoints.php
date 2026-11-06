<?php

namespace App\Filament\Pages;

use App\Actions\Points\CardPoints;
use App\Actions\Points\LookUpCardPoints;
use App\Enums\PointSource;
use App\Filament\Enums\PanelGroup;
use App\Filament\Support\Presenters\PointsPresenter;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\PointTransaction;
use App\Support\ArabicNoun;
use App\Support\Dates;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The points desk. The USB scanner types the digits of a student's card
 * and presses Enter, as at the attendance desk; the screen answers with
 * the student's points in the card's course, the balance the sales desk
 * of a market shows: what is left to spend, what was earned and from
 * what, what was spent, and the latest lines. Nothing is recorded.
 */
class ScanPoints extends Page
{
    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Daily;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'رصيد النقاط';

    protected static ?string $title = 'رصيد نقاط الطالب';

    protected static ?string $slug = 'scan-points';

    protected string $view = 'filament.pages.scan-points';

    public string $cardCode = '';

    /**
     * What the last scanned card showed (null until the first scan).
     *
     * @var array{tone: string, message: string, name: ?string, photo: ?string, place: ?string, notice: ?string, available: ?int, unit: ?string, earned: ?int, spent: ?int, sources: list<array{label: string, points: string}>, latest: list<array{day: string, what: string, points: string, penalty: bool}>, sequence: int}|null
     */
    public ?array $result = null;

    public int $sequence = 0;

    public function lookUp(LookUpCardPoints $lookUp): void
    {
        // Enter pressed on an empty box is not a card.
        if (trim($this->cardCode) === '') {
            return;
        }

        $cardCode = $this->cardCode;
        $this->cardCode = '';

        $this->show($lookUp->handle($cardCode));
    }

    private function show(CardPoints $points): void
    {
        $enrollment = $points->enrollment;
        $balance = $points->balance;
        $tone = $points->status->isFound() ? 'success' : 'danger';

        $this->sequence++;
        $this->result = [
            'tone' => $tone,
            'message' => $points->status->label(),
            'name' => $enrollment?->student->full_name,
            'photo' => StudentPresenter::photoUrl($enrollment?->student),
            'place' => $enrollment === null ? null : "{$enrollment->circle->name} — {$enrollment->course->name}",
            'notice' => $points->notice(),
            'available' => $balance?->available(),
            // The noun goes with the number of points left, which can be below zero (points taken back after a purchase).
            'unit' => $balance === null ? null : ArabicNoun::point()->after(abs($balance->available())),
            'earned' => $balance?->earned,
            'spent' => $balance?->spent,
            'sources' => array_map(
                fn (string $source, int $total): array => [
                    'label' => PointsPresenter::sourceTotal(PointSource::from($source)),
                    'points' => PointsPresenter::signed($total),
                ],
                array_keys($points->bySource),
                array_values($points->bySource),
            ),
            'latest' => array_map(fn (PointTransaction $line): array => [
                'day' => Dates::show($line->occurred_on),
                'what' => PointsPresenter::line($line),
                'points' => PointsPresenter::signed($line->points),
                'penalty' => $line->points < 0,
            ], $points->latest),
            'sequence' => $this->sequence,
        ];

        // Whoever holds the scanner looks at the student, not at the screen.
        $this->dispatch('scan-processed', tone: $tone);
    }
}
