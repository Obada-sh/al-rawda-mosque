<?php

namespace App\Models\English;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A department of the English section's spending (rent, books, ...),
 * apart from the mosque's departments.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
class EnglishDepartment extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<EnglishExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(EnglishExpense::class);
    }

    /** @param Builder<EnglishDepartment> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
