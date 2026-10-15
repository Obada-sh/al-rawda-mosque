<?php

namespace Tests\Feature;

use App\Actions\Courses\DeleteCourse;
use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Actions\Market\CancelSale;
use App\Actions\Market\DeleteMarket;
use App\Actions\Market\DeleteMarketItem;
use App\Actions\Market\SaleResult;
use App\Actions\Market\SaveMarket;
use App\Actions\Market\SaveMarketItem;
use App\Actions\Market\SellToCard;
use App\Actions\Students\ArchiveStudent;
use App\Enums\SaleStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Market;
use App\Models\MarketItem;
use App\Models\MarketSale;
use App\Models\MarketSaleLine;
use App\Models\PointTransaction;
use App\Services\Market\MarketItemPictures;
use App\Services\Market\PointsBalance;
use App\Services\Statistics\StudentStandings;
use App\Services\Teachers\TeacherCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\SeedsReportWeek;
use Tests\Support\Pictures;
use Tests\TestCase;

/**
 * The points market: a course's students buy its items with the points
 * they earned in it. On the week of SeedsReportWeek: أحمد has 27 points,
 * خالد 9, بلال 6.
 */
class PointsMarketTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;
    use SeedsReportWeek;

    private Market $market;

    private MarketItem $pen;

    private MarketItem $medal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');
        $this->seedReportWeek();

        $this->market = app(SaveMarket::class)->handle(['course_id' => $this->course->getKey(), 'name' => 'سوق نهاية الشهر']);
        $this->pen = $this->item(['name' => 'قلم', 'price' => '10']);
        $this->medal = $this->item(['name' => 'ميدالية', 'price' => '5', 'stock' => '3']);
    }

    /** @param array<string, mixed> $input */
    private function item(array $input, ?MarketItem $item = null, ?Market $market = null): MarketItem
    {
        return app(SaveMarketItem::class)->handle($market ?? $this->market, $input, $item);
    }

    /**
     * @param  array<int, int>  $basket  item id => quantity
     */
    private function sell(Enrollment|string $card, array $basket = [], ?Market $market = null): SaleResult
    {
        return app(SellToCard::class)->handle([
            'market_id' => ($market ?? $this->market)->getKey(),
            'card_code' => $card instanceof Enrollment ? $card->card_code : $card,
            'items' => array_map(fn (int $id, int $quantity): array => ['item_id' => $id, 'quantity' => $quantity], array_keys($basket), $basket),
        ]);
    }

    private function available(Enrollment $enrollment): int
    {
        return app(PointsBalance::class)->of($enrollment)->available();
    }

    public function test_a_student_buys_with_the_points_of_the_markets_course(): void
    {
        $result = $this->sell($this->ahmad, [$this->pen->getKey() => 1, $this->medal->getKey() => 2]);

        $this->assertSame(SaleStatus::Sold, $result->status);
        $this->assertSame('تم الشراء', $result->message());
        $this->assertSame('أحمد', $result->studentName());

        $sale = MarketSale::query()->sole();
        $this->assertSame(20, $sale->total_points);
        $this->assertSame('2026-10-07 17:00:00', $sale->sold_at->toDateTimeString());
        $this->assertSame(
            [['قلم', 10, 1, 10], ['ميدالية', 5, 2, 10]],
            $sale->lines()->orderBy('id')->get()->map(fn (MarketSaleLine $line): array => [$line->item_name, $line->price, $line->quantity, $line->points])->all(),
        );

        // 27 earned, 20 spent: 7 left. What was earned does not change, nor do the rankings.
        $this->assertSame([27, 20, 7], [$result->balance->earned, $result->balance->spent, $result->balance->available()]);
        $this->assertSame(27, (int) PointTransaction::query()->where('enrollment_id', $this->ahmad->getKey())->sum('points'));
        $this->assertSame(27, app(StudentStandings::class)->forStudent($this->ahmad->student)->get($this->ahmad->getKey())->points);
        $this->assertSame(1, $this->medal->fresh()->stock);
        $this->assertNull($this->pen->fresh()->stock);

        // A later price applies to later sales only.
        $this->item(['name' => 'قلم', 'price' => '12'], $this->pen);
        $this->assertSame(10, $sale->lines()->where('market_item_id', $this->pen->getKey())->value('price'));
    }

    public function test_a_basket_the_balance_does_not_cover_sells_nothing(): void
    {
        $result = $this->sell($this->bilal, [$this->pen->getKey() => 1]);

        $this->assertSame(SaleStatus::NotEnoughPoints, $result->status);
        $this->assertSame('رصيد الطالب لا يكفي: رصيده 6 والسلة بـ 10 نقاط', $result->message());
        $this->assertSame(0, MarketSale::query()->count());

        // What was bought before counts: 27 → 17 → 7, and the third pen is refused.
        $this->assertSame(SaleStatus::Sold, $this->sell($this->ahmad, [$this->pen->getKey() => 1])->status);
        $this->assertSame(SaleStatus::Sold, $this->sell($this->ahmad, [$this->pen->getKey() => 1])->status);
        $third = $this->sell($this->ahmad, [$this->pen->getKey() => 1]);
        $this->assertSame(SaleStatus::NotEnoughPoints, $third->status);
        $this->assertSame(7, $third->balance->available());
        $this->assertSame(2, MarketSale::query()->count());
    }

    public function test_an_item_cannot_be_sold_beyond_what_is_left_of_it(): void
    {
        $result = $this->sell($this->ahmad, [$this->medal->getKey() => 4]);

        $this->assertSame(SaleStatus::OutOfStock, $result->status);
        $this->assertSame('الكمية المتبقية لا تكفي: بقي من «ميدالية» 3 فقط', $result->message());

        $this->assertSame(SaleStatus::Sold, $this->sell($this->ahmad, [$this->medal->getKey() => 3])->status);
        $this->assertSame(0, $this->medal->fresh()->stock);
        $this->assertFalse($this->medal->fresh()->isOnSale());
        $this->assertSame(SaleStatus::OutOfStock, $this->sell($this->khalid, [$this->medal->getKey() => 1])->status);
    }

    public function test_a_card_scanned_with_an_empty_basket_shows_the_balance(): void
    {
        $this->sell($this->ahmad, [$this->pen->getKey() => 2]);
        // Points taken back after a purchase can leave the balance below zero.
        $this->award($this->ahmad, -10, '2026-10-07');

        $result = $this->sell($this->ahmad);

        $this->assertSame(SaleStatus::BalanceShown, $result->status);
        $this->assertSame(-3, $result->balance->available());
        $this->assertSame(1, MarketSale::query()->count());
        $this->assertSame(SaleStatus::NotEnoughPoints, $this->sell($this->ahmad, [$this->medal->getKey() => 1])->status);
    }

    public function test_only_a_current_student_of_the_markets_course_buys_at_an_open_market(): void
    {
        $basket = [$this->medal->getKey() => 1];

        $this->assertSame(SaleStatus::UnknownCard, $this->sell('1234567890', $basket)->status);
        $this->assertSame(SaleStatus::UnknownCard, $this->sell('not a card', $basket)->status);

        $this->circleA->update(['supervisor_teacher_id' => $this->yaser->getKey()]);
        $teacherCard = app(TeacherCards::class)->of($this->yaser, $this->course)->card_code;
        $this->assertSame(SaleStatus::TeacherCard, $this->sell($teacherCard, $basket)->status);

        $elsewhere = $this->enroll($this->ahmad->student, Course::factory()->create());
        $this->assertSame(SaleStatus::OtherCourse, $this->sell($elsewhere, $basket)->status);

        app(UpdateEnrollmentDates::class)->handle($this->khalid, ['enrolled_on' => $this->khalid->enrolled_on->toDateString(), 'left_on' => '2026-10-06']);
        $this->assertSame(SaleStatus::InactiveEnrollment, $this->sell($this->khalid, $basket)->status);

        app(ArchiveStudent::class)->handle($this->bilal->student);
        $this->assertSame(SaleStatus::InactiveEnrollment, $this->sell($this->bilal, $basket)->status);

        // An item taken off the desk, or one of another market, is not sold.
        $this->item(['name' => 'قلم', 'price' => '10', 'is_available' => false], $this->pen);
        $offDesk = $this->sell($this->ahmad, [$this->pen->getKey() => 1]);
        $this->assertSame(SaleStatus::ItemUnavailable, $offDesk->status);
        $this->assertSame('«قلم» لم تعد معروضة في هذا السوق', $offDesk->message());

        $other = Market::factory()->create(['course_id' => $this->course->getKey()]);
        $foreign = $this->item(['name' => 'كرة', 'price' => '1'], market: $other);
        $this->assertSame('في السلة بضاعة لم تعد معروضة', $this->sell($this->ahmad, [$foreign->getKey() => 1])->message());

        app(SaveMarket::class)->handle(['name' => 'سوق نهاية الشهر', 'is_open' => false], $this->market);
        $this->assertSame(SaleStatus::MarketClosed, $this->sell($this->ahmad, $basket)->status);

        $this->assertSame(0, MarketSale::query()->count());
        $this->assertSame(3, $this->medal->fresh()->stock);
    }

    public function test_a_cancelled_sale_gives_the_points_and_the_items_back(): void
    {
        $this->sell($this->ahmad, [$this->pen->getKey() => 1, $this->medal->getKey() => 3]);
        $this->assertSame(2, $this->available($this->ahmad));

        app(CancelSale::class)->handle(MarketSale::query()->sole());

        $this->assertSame(27, $this->available($this->ahmad));
        $this->assertSame(3, $this->medal->fresh()->stock);
        $this->assertSame(0, MarketSaleLine::query()->count());
    }

    public function test_a_malformed_basket_is_refused(): void
    {
        $malformed = [
            'no quantity' => [['item_id' => $this->pen->getKey(), 'quantity' => 0]],
            'too many' => [['item_id' => $this->pen->getKey(), 'quantity' => 100]],
            'the same item twice' => [['item_id' => $this->pen->getKey(), 'quantity' => 1], ['item_id' => $this->pen->getKey(), 'quantity' => 1]],
            'not an item' => [['item_id' => 'قلم', 'quantity' => 1]],
        ];

        foreach ($malformed as $case => $items) {
            try {
                app(SellToCard::class)->handle(['market_id' => $this->market->getKey(), 'card_code' => $this->ahmad->card_code, 'items' => $items]);
                $this->fail("Accepted: {$case}");
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors(), $case);
            }
        }

        $this->assertSame(0, MarketSale::query()->count());
    }

    public function test_markets_and_items_are_checked(): void
    {
        $refused = [
            'no course' => fn () => app(SaveMarket::class)->handle(['name' => 'سوق آخر']),
            'a name already used in the course' => fn () => app(SaveMarket::class)->handle(['course_id' => $this->course->getKey(), 'name' => 'سوق نهاية الشهر']),
            'no price' => fn () => $this->item(['name' => 'دفتر']),
            'a free item' => fn () => $this->item(['name' => 'دفتر', 'price' => '0']),
            'a negative stock' => fn () => $this->item(['name' => 'دفتر', 'price' => '5', 'stock' => '-1']),
            'an item name already used in the market' => fn () => $this->item(['name' => 'قلم', 'price' => '5']),
            'text that calls itself a picture' => fn () => $this->item(['name' => 'دفتر', 'price' => '5', 'image' => Pictures::pretender('book.png')]),
        ];

        foreach ($refused as $case => $save) {
            try {
                $save();
                $this->fail("Accepted: {$case}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        // The same name in another course, and Arabic digits, are fine.
        $other = app(SaveMarket::class)->handle(['course_id' => Course::factory()->create()->getKey(), 'name' => 'سوق نهاية الشهر']);
        $this->assertTrue($other->is_open);
        $book = $this->item(['name' => 'دفتر', 'price' => '١٥', 'stock' => '٤', 'description' => 'دفتر 60 ورقة']);
        $this->assertSame([15, 4, 'دفتر 60 ورقة', true], [$book->price, $book->stock, $book->description, $book->is_available]);
    }

    public function test_an_items_picture_is_kept_replaced_and_removed(): void
    {
        $disk = Storage::disk(MarketItemPictures::DISK);

        $pen = $this->item(['name' => 'قلم', 'price' => '10', 'image' => Pictures::png('pen.png')], $this->pen);
        $first = $pen->image_path;
        $this->assertMatchesRegularExpression('#^market-items/'.$pen->getKey().'-[a-z0-9]{10}\.png$#', (string) $first);
        $disk->assertExists($first);

        $pen = $this->item(['name' => 'قلم', 'price' => '10', 'image' => Pictures::png('pen.png')], $pen);
        $this->assertNotSame($first, $pen->image_path);
        $this->assertSame([$pen->image_path], $disk->files(MarketItemPictures::FOLDER));

        // Saving without a picture keeps it; asking removes it.
        $this->assertSame($pen->image_path, $this->item(['name' => 'قلم', 'price' => '11'], $pen)->image_path);
        $this->item(['name' => 'قلم', 'price' => '11', 'remove_image' => true], $pen);
        $this->assertNull($pen->fresh()->image_path);
        $this->assertSame([], $disk->files(MarketItemPictures::FOLDER));
    }

    public function test_what_was_sold_cannot_be_deleted(): void
    {
        $this->sell($this->ahmad, [$this->pen->getKey() => 1]);

        $this->assertViolates('market_item.sold', fn () => app(DeleteMarketItem::class)->handle($this->pen));
        $this->assertViolates('market.has_sales', fn () => app(DeleteMarket::class)->handle($this->market));
        $this->assertViolates('course.has_records', fn () => app(DeleteCourse::class)->handle($this->course));

        // An item never sold goes, with its picture; a market that sold nothing goes, with its items.
        $this->item(['name' => 'ميدالية', 'price' => '5', 'image' => Pictures::png()], $this->medal);
        $picture = $this->medal->fresh()->image_path;
        app(DeleteMarketItem::class)->handle($this->medal->fresh());
        $this->assertModelMissing($this->medal);
        Storage::disk(MarketItemPictures::DISK)->assertMissing($picture);

        $empty = Market::factory()->create(['course_id' => $this->course->getKey()]);
        $leftover = $this->item(['name' => 'كرة', 'price' => '50'], market: $empty);
        app(DeleteMarket::class)->handle($empty);
        $this->assertModelMissing($empty);
        $this->assertModelMissing($leftover);

        // A course whose only use is a market keeps it until the market is deleted.
        $course = Course::factory()->create();
        $market = Market::factory()->create(['course_id' => $course->getKey()]);
        $this->assertViolates('course.has_records', fn () => app(DeleteCourse::class)->handle($course));
        app(DeleteMarket::class)->handle($market);
        app(DeleteCourse::class)->handle($course);
        $this->assertModelMissing($course);
    }
}
