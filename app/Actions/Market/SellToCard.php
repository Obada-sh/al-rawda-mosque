<?php

namespace App\Actions\Market;

use App\Enums\SaleStatus;
use App\Http\Requests\Market\SaleRequest;
use App\Models\Enrollment;
use App\Models\Market;
use App\Models\MarketItem;
use App\Models\MarketSale;
use App\Models\TeacherCard;
use App\Services\Market\PointsBalance;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The sales desk of a points market: the basket is sold to the student
 * whose card was scanned, with the points they earned in the market's
 * course, or nothing happens and the result says why.
 *
 * A sale is all or nothing: every item is still offered, there is enough
 * of each, and the student's balance covers the whole basket. The student
 * and the items are locked while this is checked and written, so a card
 * scanned twice in a hurry cannot spend the same points twice, and two
 * students cannot buy the last one together.
 *
 * An empty basket sells nothing: the result shows the student's balance.
 * Points are never written to the points ledger (see PointsBalance).
 */
final class SellToCard
{
    public function __construct(private readonly PointsBalance $balances) {}

    /**
     * @param  array<string, mixed>  $input  market_id, card_code, items: list of {item_id, quantity}
     *
     * @throws ValidationException when the basket itself is malformed
     */
    public function handle(array $input, ?CarbonInterface $at = null): SaleResult
    {
        try {
            $data = SaleRequest::validateData($input);
        } catch (ValidationException $exception) {
            // What the scanner typed is not a card; anything else is a broken request.
            if (array_keys($exception->errors()) === ['card_code']) {
                return new SaleResult(SaleStatus::UnknownCard);
            }

            throw $exception;
        }

        $market = Market::query()->findOrFail($data['market_id']);

        if (! $market->is_open) {
            return new SaleResult(SaleStatus::MarketClosed);
        }

        $enrollment = Enrollment::query()
            ->with(['student', 'circle', 'course'])
            ->where('card_code', $data['card_code'])
            ->first();

        if ($enrollment === null) {
            $isTeacher = TeacherCard::query()->where('card_code', $data['card_code'])->exists();

            return new SaleResult($isTeacher ? SaleStatus::TeacherCard : SaleStatus::UnknownCard);
        }

        if ((int) $enrollment->course_id !== (int) $market->course_id) {
            return new SaleResult(SaleStatus::OtherCourse, $enrollment);
        }

        if ($enrollment->hasLeft() || $enrollment->student->isArchived()) {
            return new SaleResult(SaleStatus::InactiveEnrollment, $enrollment);
        }

        /** @var array<int, int> $basket item id => quantity */
        $basket = collect($data['items'])->mapWithKeys(fn (array $line): array => [(int) $line['item_id'] => (int) $line['quantity']])->all();

        if ($basket === []) {
            return new SaleResult(SaleStatus::BalanceShown, $enrollment, balance: $this->balances->of($enrollment));
        }

        return DB::transaction(fn (): SaleResult => $this->sell($market, $enrollment, $basket, $at ?? now()));
    }

    /**
     * @param  array<int, int>  $basket  item id => quantity
     */
    private function sell(Market $market, Enrollment $enrollment, array $basket, CarbonInterface $at): SaleResult
    {
        // One sale at a time per student: the second scan waits for the first.
        Enrollment::query()->whereKey($enrollment->getKey())->lockForUpdate()->first();

        /** @var Collection<int, MarketItem> $items */
        $items = MarketItem::query()
            ->whereKey(array_keys($basket))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $cost = 0;

        foreach ($basket as $itemId => $quantity) {
            $item = $items->get($itemId);

            $isOurs = $item !== null && (int) $item->market_id === (int) $market->getKey();

            if (! $isOurs || ! $item->is_available) {
                // An item of another market is not named: it is not on this desk.
                return new SaleResult(SaleStatus::ItemUnavailable, $enrollment, item: $isOurs ? $item : null);
            }

            if ($item->stock !== null && $item->stock < $quantity) {
                return new SaleResult(SaleStatus::OutOfStock, $enrollment, item: $item);
            }

            $cost += $item->price * $quantity;
        }

        $balance = $this->balances->of($enrollment);

        if (! $balance->covers($cost)) {
            return new SaleResult(SaleStatus::NotEnoughPoints, $enrollment, balance: $balance, cost: $cost);
        }

        $sale = MarketSale::query()->create([
            'market_id' => $market->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'total_points' => $cost,
            'sold_at' => $at,
        ]);

        foreach ($basket as $itemId => $quantity) {
            $item = $items->get($itemId);

            $sale->lines()->create([
                'market_item_id' => $item->getKey(),
                'item_name' => $item->name,
                'price' => $item->price,
                'quantity' => $quantity,
                'points' => $item->price * $quantity,
            ]);

            if ($item->stock !== null) {
                $item->decrement('stock', $quantity);
            }
        }

        return new SaleResult(
            SaleStatus::Sold,
            $enrollment,
            sale: $sale->load('lines'),
            balance: $this->balances->of($enrollment),
            cost: $cost,
        );
    }
}
