<?php

namespace Tests\Feature;

use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Actions\Market\SaveMarket;
use App\Actions\Market\SaveMarketItem;
use App\Actions\Market\SellToCard;
use App\Actions\Points\CardPoints;
use App\Actions\Points\LookUpCardPoints;
use App\Actions\Students\ArchiveStudent;
use App\Enums\CourseStatus;
use App\Enums\PointsLookupStatus;
use App\Enums\PointSource;
use App\Models\English\EnglishEnrollment;
use App\Models\MarketSale;
use App\Models\PointTransaction;
use App\Services\Teachers\TeacherCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\SeedsReportWeek;
use Tests\TestCase;

/**
 * The points desk: a card is scanned and its student's points in the
 * card's course are shown, as the sales desk shows the balance. On the
 * week of SeedsReportWeek: أحمد has 27 points (24 from his pages, 3 from
 * attendance), بلال 6 (8, 1, and 3 taken away), خالد 9 (2, and 7 given).
 */
class PointsDeskTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;
    use SeedsReportWeek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');
        $this->seedReportWeek();
    }

    private function lookUp(?string $card): CardPoints
    {
        return app(LookUpCardPoints::class)->handle($card);
    }

    public function test_a_students_card_shows_what_they_earned_spent_and_have_left_in_its_course(): void
    {
        $market = app(SaveMarket::class)->handle(['course_id' => $this->course->getKey(), 'name' => 'سوق الشهر']);
        $pen = app(SaveMarketItem::class)->handle($market, ['name' => 'قلم', 'price' => '10']);
        app(SellToCard::class)->handle(['market_id' => $market->getKey(), 'card_code' => $this->ahmad->card_code, 'items' => [['item_id' => $pen->getKey(), 'quantity' => 2]]]);

        $points = $this->lookUp($this->ahmad->card_code);

        $this->assertSame(PointsLookupStatus::Found, $points->status);
        $this->assertSame($this->ahmad->getKey(), $points->enrollment->getKey());
        $this->assertSame([27, 20, 7], [$points->balance->earned, $points->balance->spent, $points->balance->available()]);
        $this->assertSame(['recitation' => 24, 'attendance' => 3], $points->bySource);
        $this->assertNull($points->notice());

        // The latest lines, newest first, five at most.
        $this->assertCount(LookUpCardPoints::LATEST, $points->latest);
        $this->assertSame(
            [['2026-10-05', PointSource::Attendance], ['2026-10-03', PointSource::Recitation], ['2026-10-03', PointSource::Attendance], ['2026-10-01', PointSource::Recitation], ['2026-10-01', PointSource::Attendance]],
            array_map(fn (PointTransaction $line): array => [$line->occurred_on->toDateString(), $line->source], $points->latest),
        );

        // Each source in its order, a penalty taken away from its own.
        $this->assertSame(['recitation' => 8, 'attendance' => 1, 'manual' => -3], $this->lookUp($this->bilal->card_code)->bySource);
        $this->assertSame(['attendance' => 2, 'manual' => 7], $this->lookUp($this->khalid->card_code)->bySource);

        // The digits the scanner of an Arabic keyboard types.
        $arabic = strtr($this->bilal->card_code, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
        $this->assertSame(6, $this->lookUp($arabic)->balance->available());
    }

    public function test_a_card_that_is_not_a_mosque_students_is_told_apart(): void
    {
        $teacherCard = app(TeacherCards::class)->of($this->yaser, $this->course)->card_code;
        $englishCard = EnglishEnrollment::factory()->create()->card_code;

        foreach ([
            [$teacherCard, PointsLookupStatus::TeacherCard],
            [$englishCard, PointsLookupStatus::EnglishCard],
            ['99999999', PointsLookupStatus::UnknownCard],
            ['abc', PointsLookupStatus::UnknownCard],
            ['', PointsLookupStatus::UnknownCard],
            [null, PointsLookupStatus::UnknownCard],
        ] as [$card, $status]) {
            $points = $this->lookUp($card);

            $this->assertSame($status, $points->status, (string) $card);
            $this->assertNull($points->enrollment);
            $this->assertNull($points->balance);
            $this->assertSame([], $points->bySource);
        }
    }

    public function test_the_points_stay_shown_when_they_can_no_longer_grow_and_nothing_is_written(): void
    {
        $lines = PointTransaction::query()->count();

        app(UpdateEnrollmentDates::class)->handle($this->khalid, ['enrolled_on' => $this->khalid->enrolled_on->toDateString(), 'left_on' => '2026-10-06']);
        $left = $this->lookUp($this->khalid->card_code);
        $this->assertSame('غادر الطالب الدورة في 06/10/2026', $left->notice());
        $this->assertSame(9, $left->balance->earned);

        app(ArchiveStudent::class)->handle($this->bilal->student);
        $this->assertSame('ملف الطالب مؤرشف', $this->lookUp($this->bilal->card_code)->notice());

        $this->course->update(['status' => CourseStatus::Finished]);
        $finished = $this->lookUp($this->ahmad->card_code);
        $this->assertSame('الدورة منتهية', $finished->notice());
        $this->assertSame(27, $finished->balance->available());

        $this->assertSame($lines, PointTransaction::query()->count());
        $this->assertSame(0, MarketSale::query()->count());
    }
}
