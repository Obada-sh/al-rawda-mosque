<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;

/**
 * Giving an enrolled student a price of their own for the course, or
 * taking it away (no price: the student pays the course's price).
 */
class EnglishPriceRequest extends BaseRequest
{
    protected array $digitFields = ['price'];

    public function rules(): array
    {
        return self::priceRules();
    }

    public function messages(): array
    {
        return self::priceMessages();
    }

    public function attributes(): array
    {
        return self::priceAttributes();
    }

    /**
     * @return array<string, list<string>>
     */
    public static function priceRules(): array
    {
        return [
            'price' => ['nullable', 'integer', 'min:0', 'max:'.EnglishCourseRequest::MAX_PRICE],
            'price_note' => ['nullable', 'required_with:price', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function priceMessages(): array
    {
        return [
            'price.integer' => 'السعر رقم صحيح بالليرة السورية، بلا فواصل.',
            'price_note.required_with' => 'اكتب سبب السعر الخاص (مثلاً: خصم أخوة).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function priceAttributes(): array
    {
        return [
            'price' => 'السعر الخاص',
            'price_note' => 'سبب السعر الخاص',
        ];
    }
}
