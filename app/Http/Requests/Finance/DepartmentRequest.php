<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Context: ignore_id => the department being edited (null when creating).
 */
class DepartmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('departments', 'name')->ignore($this->context('ignore_id')),
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
