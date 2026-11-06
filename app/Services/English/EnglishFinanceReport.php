<?php

namespace App\Services\English;

use App\Models\English\EnglishExpense;
use App\Models\English\EnglishPayment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The English section's money over a period: what the students paid (the
 * income), what the section spent (its expenses), and what is left (the
 * net, which may be below zero). Nothing of the mosque is counted.
 */
final class EnglishFinanceReport
{
    /**
     * @return array{income: int, payments: int, expenses: string, expenseCount: int, net: string}
     */
    public function totals(EnglishFinanceFilter $filter): array
    {
        $income = (int) $this->payments($filter)->sum('amount');
        $expenses = Money::sum((string) ($this->expenses($filter)->sum('total') ?: '0'));

        return [
            'income' => $income,
            'payments' => $this->payments($filter)->count(),
            'expenses' => $expenses,
            'expenseCount' => $this->expenses($filter)->count(),
            'net' => Money::difference($income, $expenses),
        ];
    }

    /**
     * Month by month, the latest first, the months with money only.
     *
     * @return Collection<int, array{year: int, month: int, income: int, expenses: string, net: string}>
     */
    public function byMonth(EnglishFinanceFilter $filter): Collection
    {
        $income = $this->payments($filter)
            ->selectRaw('YEAR(paid_on) AS y, MONTH(paid_on) AS m, SUM(amount) AS total')
            ->groupByRaw('YEAR(paid_on), MONTH(paid_on)')
            ->get()
            ->mapWithKeys(fn ($row): array => [sprintf('%04d-%02d', $row->y, $row->m) => (int) $row->total]);

        $expenses = $this->expenses($filter)
            ->selectRaw('YEAR(expense_date) AS y, MONTH(expense_date) AS m, SUM(total) AS total')
            ->groupByRaw('YEAR(expense_date), MONTH(expense_date)')
            ->get()
            ->mapWithKeys(fn ($row): array => [sprintf('%04d-%02d', $row->y, $row->m) => Money::sum((string) $row->total)]);

        return $income->keys()
            ->merge($expenses->keys())
            ->unique()
            ->sortDesc()
            ->values()
            ->map(function (string $key) use ($income, $expenses): array {
                $in = (int) ($income[$key] ?? 0);
                $out = $expenses[$key] ?? '0.00';

                return [
                    'year' => (int) substr($key, 0, 4),
                    'month' => (int) substr($key, 5, 2),
                    'income' => $in,
                    'expenses' => $out,
                    'net' => Money::difference($in, $out),
                ];
            });
    }

    /**
     * What each course's students paid in the period, the largest first.
     *
     * @return Collection<int, array{course: string, income: int, payments: int}>
     */
    public function incomeByCourse(EnglishFinanceFilter $filter): Collection
    {
        return $this->payments($filter)
            ->join('english_enrollments', 'english_enrollments.id', '=', 'english_payments.english_enrollment_id')
            ->join('english_courses', 'english_courses.id', '=', 'english_enrollments.english_course_id')
            ->groupBy('english_courses.id', 'english_courses.name')
            ->selectRaw('english_courses.name AS course, SUM(english_payments.amount) AS income, COUNT(*) AS payments')
            ->orderByDesc('income')
            ->get()
            ->map(fn ($row): array => [
                'course' => (string) $row->course,
                'income' => (int) $row->income,
                'payments' => (int) $row->payments,
            ]);
    }

    /**
     * What each department spent in the period, the largest first.
     *
     * @return Collection<int, array{department: string, total: string, count: int}>
     */
    public function expensesByDepartment(EnglishFinanceFilter $filter): Collection
    {
        return $this->expenses($filter)
            ->join('english_departments', 'english_departments.id', '=', 'english_expenses.english_department_id')
            ->groupBy('english_departments.id', 'english_departments.name')
            ->selectRaw('english_departments.name AS department, SUM(english_expenses.total) AS total, COUNT(*) AS count')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'department' => (string) $row->department,
                'total' => Money::sum((string) $row->total),
                'count' => (int) $row->count,
            ]);
    }

    /**
     * @return Builder<EnglishPayment>
     */
    private function payments(EnglishFinanceFilter $filter): Builder
    {
        return EnglishPayment::query()
            ->when($filter->from, fn (Builder $query, string $from) => $query->where('english_payments.paid_on', '>=', $from))
            ->when($filter->until, fn (Builder $query, string $until) => $query->where('english_payments.paid_on', '<=', $until))
            ->when($filter->courseId, fn (Builder $query, int $courseId) => $query->whereIn(
                'english_payments.english_enrollment_id',
                fn ($sub) => $sub->select('id')->from('english_enrollments')->where('english_course_id', $courseId),
            ));
    }

    /**
     * @return Builder<EnglishExpense>
     */
    private function expenses(EnglishFinanceFilter $filter): Builder
    {
        return EnglishExpense::query()
            ->when($filter->from, fn (Builder $query, string $from) => $query->where('english_expenses.expense_date', '>=', $from))
            ->when($filter->until, fn (Builder $query, string $until) => $query->where('english_expenses.expense_date', '<=', $until))
            ->when($filter->departmentId, fn (Builder $query, int $departmentId) => $query->where('english_expenses.english_department_id', $departmentId));
    }
}
