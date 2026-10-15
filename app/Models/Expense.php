<?php

namespace App\Models;

use App\Enums\ExpenseKind;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money spent, in Syrian pounds: an item bought by quantity and unit price,
 * or an amount paid as a whole with a description (no quantity and no
 * unit price).
 *
 * @property int $id
 * @property int $department_id
 * @property string $item_name the item, or the description of an amount paid as a whole
 * @property string|null $quantity
 * @property string|null $unit_price
 * @property string $total
 * @property CarbonInterface $expense_date
 * @property string|null $notes
 * @property-read ExpenseKind $kind
 */
class Expense extends Model
{
    protected $fillable = [
        'department_id',
        'item_name',
        'quantity',
        'unit_price',
        'total',
        'expense_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'expense_date' => Dates::CAST,
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * An expense without a quantity is an amount paid as a whole.
     *
     * @return Attribute<ExpenseKind, never>
     */
    protected function kind(): Attribute
    {
        return Attribute::get(fn (): ExpenseKind => $this->quantity === null ? ExpenseKind::LumpSum : ExpenseKind::Itemized);
    }
}
