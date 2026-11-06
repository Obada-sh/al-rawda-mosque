<?php

namespace App\Services\English;

use App\Enums\English\PaymentState;

/**
 * A student's account in one English course, in whole Syrian pounds.
 */
final readonly class EnglishBalance
{
    public function __construct(
        public int $due,
        public int $paid,
    ) {}

    /** Never below zero: nothing is ever paid beyond the price (RecordEnglishPayment). */
    public function remaining(): int
    {
        return max(0, $this->due - $this->paid);
    }

    public function state(): PaymentState
    {
        return match (true) {
            $this->remaining() === 0 => PaymentState::Paid,
            $this->paid > 0 => PaymentState::Partial,
            default => PaymentState::Unpaid,
        };
    }
}
