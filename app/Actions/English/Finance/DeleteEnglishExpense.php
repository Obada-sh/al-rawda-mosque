<?php

namespace App\Actions\English\Finance;

use App\Models\English\EnglishExpense;

final class DeleteEnglishExpense
{
    public function handle(EnglishExpense $expense): void
    {
        $expense->delete();
    }
}
