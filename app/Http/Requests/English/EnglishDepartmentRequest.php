<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * A department of the English section's spending.
 *
 * Context: ignore_id => the department being edited (null when creating).
 */
class EnglishDepartmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('english_departments', 'name')->ignore($this->context('ignore_id')),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم القسم',
            'is_active' => 'الحالة',
        ];
    }
}
