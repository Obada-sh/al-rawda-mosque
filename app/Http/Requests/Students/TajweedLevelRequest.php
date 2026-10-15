<?php

namespace App\Http\Requests\Students;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Context: ignore_id => the level being edited (null when creating).
 */
class TajweedLevelRequest extends BaseRequest
{
    protected array $digitFields = ['sort_order'];

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('tajweed_levels', 'name')->ignore($this->context('ignore_id')),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم المستوى',
            'sort_order' => 'الترتيب',
        ];
    }
}
