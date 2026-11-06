<?php

namespace App\Actions\English\Finance;

use App\Enums\ExpenseKind;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishExpenseRequest;
use App\Models\English\EnglishDepartment;
use App\Models\English\EnglishExpense;
use App\Services\Finance\ExpenseAmounts;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Records an expense of the English section, or corrects one: the same
 * way as the mosque's (an item by quantity × unit price, or an amount paid
 * as a whole), in the section's own departments.
 */
final class SaveEnglishExpense
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation department.inactive | expense.total_too_large
     */
    public function handle(array $input, ?EnglishExpense $expense = null): EnglishExpense
    {
        $data = EnglishExpenseRequest::validateData($input);

        $departmentChanged = (int) $data['english_department_id'] !== (int) $expense?->english_department_id;

        if ($departmentChanged && ! EnglishDepartment::query()->whereKey($data['english_department_id'])->where('is_active', true)->exists()) {
            throw BusinessRuleViolation::because('department.inactive', 'القسم المختار غير نشط.');
        }

        $expense ??= new EnglishExpense;

        $expense->fill([
            'english_department_id' => $data['english_department_id'],
            'item_name' => $data['item_name'],
            ...ExpenseAmounts::of(ExpenseKind::from($data['kind']), $data),
            'expense_date' => Dates::day($data['expense_date']),
            'notes' => $data['notes'] ?? null,
        ])->save();

        return $expense;
    }
}
