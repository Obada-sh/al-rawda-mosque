<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One purchase at a market: a student (an enrollment in the market's
 * course) bought one or more items at once. Written by SellToCard only;
 * cancelled by CancelSale.
 *
 * @property int $id
 * @property int $market_id
 * @property int $enrollment_id
 * @property int $total_points
 * @property CarbonInterface $sold_at
 */
class MarketSale extends Model
{
    protected $fillable = [
        'market_id',
        'enrollment_id',
        'total_points',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'total_points' => 'integer',
            'sold_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Market, $this> */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return HasMany<MarketSaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(MarketSaleLine::class);
    }
}
