<?php

namespace App\Actions\English\Payments;

use App\Models\English\EnglishPayment;

/**
 * Removes a payment recorded by mistake: what the student owes goes back up.
 */
final class DeleteEnglishPayment
{
    public function handle(EnglishPayment $payment): void
    {
        $payment->delete();
    }
}
