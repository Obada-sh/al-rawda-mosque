<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;

/**
 * The results of a test, student by student: a mark out of the test's
 * full mark, or absent, and a note. A student with neither has no result.
 *
 * Context: max_mark => the full mark of the test (required).
 */
class EnglishTestResultsRequest extends BaseRequest
{
    protected array $digitFields = ['mark'];

    public function rules(): array
    {
        return [
            'marks' => ['present', 'array'],
            'marks.*.mark' => ['nullable', 'numeric', 'min:0', 'max:'.(int) $this->context('max_mark'), 'decimal:0,2', 'prohibited_if_accepted:marks.*.is_absent'],
            'marks.*.is_absent' => ['nullable', 'boolean'],
            'marks.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'marks.*.mark.max' => 'العلامة أكبر من العلامة الكاملة (:max).',
            'marks.*.mark.decimal' => 'العلامة تقبل خانتين عشريتين على الأكثر.',
            'marks.*.mark.prohibited_if_accepted' => 'الطالب الغائب ليس له علامة.',
        ];
    }

    public function attributes(): array
    {
        return [
            'marks' => 'العلامات',
            'marks.*.mark' => 'العلامة',
            'marks.*.is_absent' => 'غائب',
            'marks.*.notes' => 'ملاحظة',
        ];
    }
}
