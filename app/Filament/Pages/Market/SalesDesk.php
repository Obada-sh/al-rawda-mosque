<?php

namespace App\Filament\Pages\Market;

use App\Actions\Market\SaleResult;
use App\Actions\Market\SellToCard;
use App\Enums\SaleStatus;
use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Markets\MarketResource;
use App\Filament\Support\Options;
use App\Filament\Support\Presenters\MarketPresenter;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Http\Requests\Market\SaleRequest;
use App\Models\Market;
use App\Models\MarketItem;
use App\Models\MarketSale;
use App\Models\MarketSaleLine;
use App\Support\Dates;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * The sales desk of a points market (نافذة البيع). The admin puts the
 * items a student asks for in the basket, then scans the student's card:
 * the basket is sold with the points the student earned in the market's
 * course, or nothing is sold and the desk says why. Then the basket is
 * empty for the next student.
 *
 * A card scanned with an empty basket shows the student's balance.
 */
class SalesDesk extends Page
{
    private const RECENT_SALES = 8;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Market;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'نافذة البيع';

    protected static ?string $title = 'نافذة البيع';

    protected static ?string $slug = 'market/desk';

    protected string $view = 'filament.pages.sales-desk';

    /** The market the desk sells for (one of the open markets). */
    public ?int $marketId = null;

    /**
     * What the next student is buying, in the order it was added.
     *
     * @var list<array{item: int, quantity: int}>
     */
    public array $basket = [];

    public string $cardCode = '';

    /**
     * What the last scanned card led to (null until the first scan).
     *
     * @var array{tone: string, message: string, name: ?string, photo: ?string, place: ?string, lines: list<string>, spent: ?string, balance: ?int, sequence: int}|null
     */
    public ?array $result = null;

    public int $sequence = 0;

    /**
     * The desk opens on the market named in its address (?market=5), or on
     * the only open market. A market that does not sell (closed, or gone)
     * is not a desk.
     */
    public function mount(): void
    {
        $open = Options::openMarkets();
        $requested = request()->query('market');

        $this->marketId = match (true) {
            is_string($requested) && ctype_digit($requested) && array_key_exists((int) $requested, $open) => (int) $requested,
            count($open) === 1 => (int) array_key_first($open),
            default => null,
        };
    }

    public function chooseMarket(?int $marketId): void
    {
        $this->marketId = $marketId !== null && array_key_exists($marketId, Options::openMarkets()) ? $marketId : null;
        $this->basket = [];
        $this->result = null;
    }

    /**
     * One more of an item in the basket, as long as there is one left to
     * sell. The sale checks everything again.
     */
    public function add(int $itemId): void
    {
        $item = $this->items()->get($itemId);

        if ($item === null || ! $item->isOnSale()) {
            return;
        }

        $index = $this->lineOf($itemId);
        $quantity = ($index === null ? 0 : $this->basket[$index]['quantity']) + 1;

        if ($quantity > SaleRequest::MAX_QUANTITY || ($item->stock !== null && $quantity > $item->stock)) {
            return;
        }

        if ($index === null) {
            if (count($this->basket) >= SaleRequest::MAX_LINES) {
                return;
            }

            $this->basket[] = ['item' => $itemId, 'quantity' => 1];

            return;
        }

        $this->basket[$index]['quantity'] = $quantity;
    }

    /** One less of an item; the last one leaves the basket. */
    public function decrease(int $itemId): void
    {
        $index = $this->lineOf($itemId);

        if ($index === null) {
            return;
        }

        if ($this->basket[$index]['quantity'] <= 1) {
            $this->remove($itemId);

            return;
        }

        $this->basket[$index]['quantity']--;
    }

    public function remove(int $itemId): void
    {
        $this->basket = array_values(array_filter($this->basket, fn (array $line): bool => $line['item'] !== $itemId));
    }

    public function clearBasket(): void
    {
        $this->basket = [];
    }

    public function sell(SellToCard $sellToCard): void
    {
        // Enter pressed on an empty box is not a card.
        if (trim($this->cardCode) === '' || $this->marketId === null) {
            return;
        }

        $cardCode = $this->cardCode;
        $this->cardCode = '';

        try {
            $outcome = $sellToCard->handle([
                'market_id' => $this->marketId,
                'card_code' => $cardCode,
                'items' => array_map(fn (array $line): array => ['item_id' => $line['item'], 'quantity' => $line['quantity']], $this->basket),
            ]);
        } catch (ValidationException) {
            // The basket is built by this page and always valid; one that is not is dropped.
            $this->basket = [];
            $this->answer('danger', 'تعذّر قراءة السلة، فأُفرغت. أعد اختيار البضاعة.');

            return;
        }

        if ($outcome->status === SaleStatus::Sold) {
            $this->basket = [];
        }

        $this->show($outcome);
    }

    private function show(SaleResult $outcome): void
    {
        $enrollment = $outcome->enrollment;

        $this->answer(
            tone: match (true) {
                $outcome->status->isSuccess() => 'success',
                $outcome->status->isInformation() => 'warning',
                default => 'danger',
            },
            message: $outcome->message(),
            name: $outcome->studentName(),
            photo: StudentPresenter::photoUrl($enrollment?->student),
            place: $enrollment === null ? null : "{$enrollment->circle->name} — {$enrollment->course->name}",
            lines: $outcome->sale === null ? [] : $outcome->sale->lines
                ->map(fn (MarketSaleLine $line): string => $line->quantity > 1 ? "{$line->item_name} ×{$line->quantity}" : $line->item_name)
                ->all(),
            spent: $outcome->sale === null ? null : MarketPresenter::points($outcome->sale->total_points),
            balance: $outcome->balance?->available(),
        );
    }

    /**
     * @param  list<string>  $lines
     */
    private function answer(string $tone, string $message, ?string $name = null, ?string $photo = null, ?string $place = null, array $lines = [], ?string $spent = null, ?int $balance = null): void
    {
        $this->sequence++;
        $this->result = [
            'tone' => $tone,
            'message' => $message,
            'name' => $name,
            'photo' => $photo,
            'place' => $place,
            'lines' => $lines,
            'spent' => $spent,
            'balance' => $balance,
            'sequence' => $this->sequence,
        ];

        // Whoever holds the scanner looks at the student, not at the screen.
        $this->dispatch('scan-processed', tone: $tone);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $market = $this->market();
        $items = $this->items();

        $basket = collect($this->basket)
            ->filter(fn (array $line): bool => $items->has($line['item']))
            ->map(fn (array $line): array => [
                'item' => $items->get($line['item']),
                'quantity' => $line['quantity'],
                'points' => $items->get($line['item'])->price * $line['quantity'],
            ])
            ->values();

        return [
            'market' => $market,
            'openMarkets' => Options::openMarkets(),
            'items' => $items->filter(fn (MarketItem $item): bool => $item->is_available)->values(),
            'basketLines' => $basket,
            'basketTotal' => $basket->sum('points'),
            'quantities' => $basket->mapWithKeys(fn (array $line): array => [$line['item']->getKey() => $line['quantity']])->all(),
            'recentSales' => $this->recentSales(),
            'marketsUrl' => MarketResource::getUrl(),
        ];
    }

    private function market(): ?Market
    {
        return $this->marketId === null ? null : Market::query()->with('course')->open()->find($this->marketId);
    }

    /**
     * The items of the desk's market, by name.
     *
     * @return Collection<int, MarketItem> keyed by id
     */
    private function items(): Collection
    {
        if ($this->marketId === null) {
            return collect();
        }

        return MarketItem::query()
            ->where('market_id', $this->marketId)
            ->orderBy('name')
            ->get()
            ->keyBy('id');
    }

    private function lineOf(int $itemId): ?int
    {
        foreach ($this->basket as $index => $line) {
            if ($line['item'] === $itemId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Today's latest sales of the desk's market, newest first.
     *
     * @return Collection<int, MarketSale>
     */
    private function recentSales(): Collection
    {
        if ($this->marketId === null) {
            return collect();
        }

        return MarketSale::query()
            ->with(['lines', 'enrollment.student'])
            ->where('market_id', $this->marketId)
            ->whereBetween('sold_at', [Dates::today(), Dates::today()->endOfDay()])
            ->latest('sold_at')
            ->latest('id')
            ->limit(self::RECENT_SALES)
            ->get();
    }
}
