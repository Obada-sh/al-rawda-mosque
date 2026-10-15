<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * How an expense is written: an item bought by quantity and unit price,
 * or only what was paid, with a description (several things bought at
 * once whose items and quantities nobody kept).
 */
enum ExpenseKind: string
{
    use HasOptions;

    case Itemized = 'itemized';
    case LumpSum = 'lump_sum';

    public function label(): string
    {
        return match ($this) {
            self::Itemized => 'صنف بالكمية والسعر',
            self::LumpSum => 'مبلغ إجمالي فقط',
        };
    }
}
