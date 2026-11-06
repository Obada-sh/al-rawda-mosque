<?php

namespace App\Services\Finance;

use App\Enums\ExpenseKind;
use App\Support\Money;

/**
 * The amounts stored with an expense, in Syrian pounds, the same in both
 * sections: an item's total is always computed from quantity × unit price,
 * never taken from input; an amount paid as a whole is the total itself,
 * with no quantity and no unit price.
 */
final class ExpenseAmounts
{
    /**
     * @param  array<string, mixed>  $data  validated: quantity and unit_price, or amount
     * @return array{quantity: string|null, unit_price: string|null, total: string}
     */
    public static function of(ExpenseKind $kind, array $data): array
    {
        return match ($kind) {
            ExpenseKind::Itemized => [
                'quantity' => (string) $data['quantity'],
                'unit_price' => (string) $data['unit_price'],
                'total' => Money::lineTotal((string) $data['quantity'], (string) $data['unit_price']),
            ],
            ExpenseKind::LumpSum => [
                'quantity' => null,
                'unit_price' => null,
                'total' => Money::sum((string) $data['amount']),
            ],
        };
    }
}
