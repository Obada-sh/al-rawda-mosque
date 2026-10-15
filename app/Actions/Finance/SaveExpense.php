<?php

namespace App\Actions\Finance;

use App\Enums\ExpenseKind;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Finance\ExpenseRequest;
use App\Models\Department;
use App\Models\Expense;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Records an expense, or corrects one, in Syrian pounds.
 *
 * An item's total is always computed here from quantity × unit price; it
 * is never taken from input. An amount paid as a whole is the total
 * itself, and has no quantity and no unit price.
 */
final class SaveExpense
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation department.inactive | expense.total_too_large
     */
    public function handle(array $input, ?Expense $expense = null): Expense
    {
        $data = ExpenseRequest::validateData($input);

        $departmentChanged = (int) $data['department_id'] !== (int) $expense?->department_id;

        if ($departmentChanged && ! Department::query()->whereKey($data['department_id'])->where('is_active', true)->exists()) {
            throw BusinessRuleViolation::because('department.inactive', 'القسم المختار غير نشط.');
        }

        $expense ??= new Expense;

        $expense->fill([
            'department_id' => $data['department_id'],
            'item_name' => $data['item_name'],
            ...$this->amounts(ExpenseKind::from($data['kind']), $data),
            'expense_date' => Dates::day($data['expense_date']),
            'notes' => $data['notes'] ?? null,
        ])->save();

        return $expense;
    }

    /**
     * @param  array<string, mixed>  $data  validated
     * @return array{quantity: string|null, unit_price: string|null, total: string}
     */
    private function amounts(ExpenseKind $kind, array $data): array
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
