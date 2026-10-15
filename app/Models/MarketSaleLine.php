<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item of a sale, with its name and price as they were when it was
 * sold: changing the item later does not change what was bought.
 *
 * @property int $id
 * @property int $market_sale_id
 * @property int $market_item_id
 * @property string $item_name
 * @property int $price
 * @property int $quantity
 * @property int $points
 */
class MarketSaleLine extends Model
{
    protected $fillable = [
        'market_sale_id',
        'market_item_id',
        'item_name',
        'price',
        'quantity',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
            'points' => 'integer',
        ];
    }

    /** @return BelongsTo<MarketSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(MarketSale::class, 'market_sale_id');
    }

    /** @return BelongsTo<MarketItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'market_item_id');
    }
}
