<?php

namespace App\Models;

use Database\Factories\MarketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A points market (سوق النقاط) of a course: items priced in points, bought
 * by the course's students with the points they earned in it. A course can
 * hold as many markets as it likes; only an open one sells.
 *
 * @property int $id
 * @property int $course_id
 * @property string $name
 * @property bool $is_open
 */
class Market extends Model
{
    /** @use HasFactory<MarketFactory> */
    use HasFactory;

    protected $fillable = [
        'course_id',
        'name',
        'is_open',
    ];

    protected $attributes = [
        'is_open' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<MarketItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MarketItem::class);
    }

    /** @return HasMany<MarketSale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(MarketSale::class);
    }

    /** @param Builder<Market> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('is_open', true);
    }
}
