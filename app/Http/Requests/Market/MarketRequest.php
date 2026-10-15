<?php

namespace App\Http\Requests\Market;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Opening a points market for a course, or renaming, opening and closing
 * one. A market stays in the course it was opened for: the course is part
 * of the input only when the market is opened.
 *
 * Context: opening => true when a market is opened,
 *          course_id => the course whose markets the name must differ
 *                       from (the market's course, or the one chosen),
 *          ignore_id => the market being edited.
 */
class MarketRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'course_id' => $this->context('opening', false) ? ['required', 'integer', 'exists:courses,id'] : ['exclude'],
            'name' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('markets', 'name')
                    ->where('course_id', (int) $this->context('course_id', 0))
                    ->ignore($this->context('ignore_id')),
            ],
            'is_open' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'يوجد في هذه الدورة سوق بنفس الاسم.',
        ];
    }

    public function attributes(): array
    {
        return [
            'course_id' => 'الدورة',
            'name' => 'اسم السوق',
            'is_open' => 'السوق مفتوح',
        ];
    }
}
