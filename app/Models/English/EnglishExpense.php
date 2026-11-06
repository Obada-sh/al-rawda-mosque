<?php

namespace App\Models\English;

use App\Enums\ExpenseKind;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money the English section spent, in Syrian pounds: an item bought by
 * quantity and unit price, or an amount paid as a whole.
 *
 * @property int $id
 * @property int $english_department_id
 * @property string $item_name
 * @property string|null $quantity
 * @property string|null $unit_price
 * @property string $total
 * @property CarbonInterface $expense_date
 * @property string|null $notes
 * @property-read ExpenseKind $kind
 */
class EnglishExpense extends Model
{
    protected $fillable = [
        'english_department_id',
        'item_name',
        'quantity',
        'unit_price',
        'total',
        'expense_date',
        'notes',
    ];

    protected $attributes = [
        'quantity' => null,
        'unit_price' => null,
        'notes' => null,
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

    /** @return BelongsTo<EnglishDepartment, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(EnglishDepartment::class, 'english_department_id');
    }

    /** @return Attribute<ExpenseKind, never> */
    protected function kind(): Attribute
    {
        return Attribute::get(fn (): ExpenseKind => $this->quantity === null ? ExpenseKind::LumpSum : ExpenseKind::Itemized);
    }
}
