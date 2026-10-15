<?php

namespace App\Services\Finance;

use App\Http\Requests\Finance\ExpenseFilterRequest;
use App\Support\Dates;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The scope of the expenses report: a department and a period.
 */
final readonly class ExpenseFilter
{
    /**
     * @param  string|null  $from  first day of the period (Y-m-d), or null for "since the first expense"
     * @param  string|null  $until  last day of the period (Y-m-d), or null for "until the last expense"
     */
    public function __construct(
        public ?int $departmentId = null,
        public ?string $from = null,
        public ?string $until = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        $data = ExpenseFilterRequest::validateData($input);

        return new self(
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            from: isset($data['from']) ? Dates::day($data['from']) : null,
            until: isset($data['until']) ? Dates::day($data['until']) : null,
        );
    }

    /**
     * The filter, or null when the input is not a valid one.
     *
     * @param  array<string, mixed>  $input
     */
    public static function tryFromInput(array $input): ?self
    {
        try {
            return self::fromInput($input);
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * Keeps only the expenses of the chosen department and period.
     */
    public function apply(Builder $query): void
    {
        if ($this->departmentId !== null) {
            $query->where('expenses.department_id', $this->departmentId);
        }

        if ($this->from !== null) {
            $query->where('expenses.expense_date', '>=', $this->from);
        }

        if ($this->until !== null) {
            $query->where('expenses.expense_date', '<=', $this->until);
        }
    }
}
