<?php

namespace App\Http\Requests\Points;

use App\Http\Requests\BaseRequest;

/**
 * What the scanner types at the points desk: the digits of a student's
 * card (Arabic digits are read as Latin ones).
 */
class CardPointsRequest extends BaseRequest
{
    protected array $digitFields = ['card_code'];

    public function rules(): array
    {
        return [
            'card_code' => ['required', 'string', 'regex:/^[0-9]{6,20}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'card_code' => 'رقم البطاقة',
        ];
    }
}
