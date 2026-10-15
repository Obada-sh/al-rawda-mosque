<?php

namespace App\Http\Requests\Points;

use App\Http\Requests\BaseRequest;

/**
 * Points the admin adds by hand. Negative points are a penalty.
 *
 * Context: updating => true when editing (the enrollment cannot change).
 */
class ManualPointsRequest extends BaseRequest
{
    public const MAX_POINTS = 100000;

    protected array $digitFields = ['points'];

    public function rules(): array
    {
        $rules = [
            'points' => ['required', 'integer', 'not_in:0', 'min:-'.self::MAX_POINTS, 'max:'.self::MAX_POINTS],
            'reason' => ['required', 'string', 'min:2', 'max:255'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
        ];

        if (! $this->context('updating', false)) {
            $rules['enrollment_id'] = ['required', 'integer', 'exists:enrollments,id'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'points.not_in' => 'عدد النقاط لا يمكن أن يكون صفراً.',
            'reason.required' => 'اكتب سبب إضافة النقاط أو خصمها.',
            'occurred_on.before_or_equal' => 'التاريخ لا يمكن أن يكون في المستقبل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'enrollment_id' => 'الطالب',
            'points' => 'عدد النقاط',
            'reason' => 'السبب',
            'occurred_on' => 'التاريخ',
        ];
    }
}
