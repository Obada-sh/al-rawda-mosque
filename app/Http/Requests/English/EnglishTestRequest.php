<?php

namespace App\Http\Requests\English;

use App\Enums\English\TestKind;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * An exam or a recitation of an English course: what it was, when, and
 * its full mark. The test itself happens on paper or aloud; the system
 * keeps each student's result.
 */
class EnglishTestRequest extends BaseRequest
{
    public const MAX_MARK = 1000;

    protected array $digitFields = ['max_mark'];

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'kind' => ['required', Rule::enum(TestKind::class)],
            'held_on' => ['required', 'date'],
            'max_mark' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MARK],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'العنوان',
            'kind' => 'النوع',
            'held_on' => 'التاريخ',
            'max_mark' => 'العلامة الكاملة',
            'notes' => 'ملاحظات',
        ];
    }
}
