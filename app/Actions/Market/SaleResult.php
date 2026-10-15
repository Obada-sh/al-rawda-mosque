<?php

namespace App\Actions\Market;

use App\Enums\SaleStatus;
use App\Models\Enrollment;
use App\Models\MarketItem;
use App\Models\MarketSale;
use App\Services\Market\Balance;
use App\Support\ArabicNoun;

/**
 * What happened when a card was scanned at the sales desk, with everything
 * the desk needs to show.
 */
final readonly class SaleResult
{
    /**
     * @param  Enrollment|null  $enrollment  the student in the market's course, once the card is known
     * @param  MarketSale|null  $sale  the sale, with its lines, when it was made
     * @param  Balance|null  $balance  the student's points after the sale (or now, when nothing was sold)
     * @param  int  $cost  the points the basket costs
     * @param  MarketItem|null  $item  the item that stopped the sale (no longer offered, or not enough left)
     */
    public function __construct(
        public SaleStatus $status,
        public ?Enrollment $enrollment = null,
        public ?MarketSale $sale = null,
        public ?Balance $balance = null,
        public int $cost = 0,
        public ?MarketItem $item = null,
    ) {}

    /**
     * What the desk says, in full: "رصيد الطالب لا يكفي: رصيده 30 والسلة بـ 45 نقطة".
     */
    public function message(): string
    {
        $label = $this->status->label();

        return match ($this->status) {
            SaleStatus::NotEnoughPoints => "{$label}: رصيده {$this->balance?->available()} والسلة بـ ".ArabicNoun::point()->count($this->cost),
            SaleStatus::OutOfStock => "{$label}: بقي من «{$this->item?->name}» {$this->item?->stock} فقط",
            SaleStatus::ItemUnavailable => $this->item === null ? $label : "«{$this->item->name}» لم تعد معروضة في هذا السوق",
            default => $label,
        };
    }

    public function studentName(): ?string
    {
        return $this->enrollment?->student?->full_name;
    }
}
