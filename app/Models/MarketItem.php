<?php

namespace App\Models;

use Database\Factories\MarketItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something a market sells, priced in points. Its stock is what is left
 * to sell (null: no limit); a sale takes from it and a cancelled sale
 * gives it back.
 *
 * @property int $id
 * @property int $market_id
 * @property string $name
 * @property string|null $description
 * @property int $price
 * @property int|null $stock
 * @property string|null $image_path
 * @property bool $is_available
 */
class MarketItem extends Model
{
    /** @use HasFactory<MarketItemFactory> */
    use HasFactory;

    /**
     * image_path is not fillable: the file and its name are written
     * together, by SaveMarketItem.
     */
    protected $fillable = [
        'market_id',
        'name',
        'description',
        'price',
        'stock',
        'is_available',
    ];

    protected $attributes = [
        'description' => null,
        'stock' => null,
        'image_path' => null,
        'is_available' => true,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    /** @return BelongsTo<Market, $this> */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /** @return HasMany<MarketSaleLine, $this> */
    public function saleLines(): HasMany
    {
        return $this->hasMany(MarketSaleLine::class);
    }

    public function hasStockLimit(): bool
    {
        return $this->stock !== null;
    }

    /**
     * Whether the desk can sell it at all: offered, and not sold out.
     */
    public function isOnSale(): bool
    {
        return $this->is_available && ($this->stock === null || $this->stock > 0);
    }
}
