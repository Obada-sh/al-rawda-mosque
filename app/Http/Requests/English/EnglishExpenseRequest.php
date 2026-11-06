<?php

namespace App\Http\Requests\English;

use App\Http\Requests\Finance\ExpenseRequest;
use Illuminate\Support\Arr;

/**
 * Recording an expense of the English section: the mosque's rules, in a
 * department of the English section.
 */
class EnglishExpenseRequest extends ExpenseRequest
{
    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), 'department_id'),
            'english_department_id' => ['required', 'integer', 'exists:english_departments,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            ...Arr::except(parent::attributes(), 'department_id'),
            'english_department_id' => 'القسم',
        ];
    }
}
