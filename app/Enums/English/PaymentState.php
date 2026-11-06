<?php

namespace App\Enums\English;

use App\Enums\Concerns\HasOptions;

/**
 * Where a student stands with the price of an English course.
 */
enum PaymentState: string
{
    use HasOptions;

    case Paid = 'paid';
    case Partial = 'partial';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'مدفوع كاملاً',
            self::Partial => 'مدفوع جزئياً',
            self::Unpaid => 'لم يدفع',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Partial => 'warning',
            self::Unpaid => 'danger',
        };
    }
}
