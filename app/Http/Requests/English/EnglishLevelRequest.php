<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Rules\Picture;
use Illuminate\Validation\Rule;

/**
 * Creating and editing a level of the English section (A1, A2, ...).
 *
 * The card background is optional: the whole face of the attendance card
 * (54 × 86 mm, standing), on which the system draws the student's photo,
 * name, hall and code; or a wish to remove the one the level has.
 *
 * Context: ignore_id => the level being edited (null when creating).
 */
class EnglishLevelRequest extends BaseRequest
{
    /** A design for print is larger than a photo: room for one of good quality. */
    public const BACKGROUND_MAX_KILOBYTES = 5120;

    protected array $digitFields = ['sort_order'];

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('english_levels', 'name')->ignore($this->context('ignore_id')),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'card_background' => ['nullable', 'file', 'max:'.self::BACKGROUND_MAX_KILOBYTES, new Picture],
            'remove_card_background' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'يوجد مستوى بنفس الاسم.',
            'card_background.max' => 'حجم صورة البطاقة لا يتجاوز 5 ميغابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم المستوى',
            'sort_order' => 'الترتيب',
            'card_background' => 'تصميم البطاقة',
            'remove_card_background' => 'إزالة التصميم',
        ];
    }
}
