<?php

namespace App\Actions\Finance;

use App\Models\Expense;

final class DeleteExpense
{
    public function handle(Expense $expense): void
    {
        $expense->delete();
    }
}
