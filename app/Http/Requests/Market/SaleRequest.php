<?php

namespace App\Http\Requests\Market;

use App\Http\Requests\BaseRequest;

/**
 * A sale at the sales desk: the market, what the scanner typed (the digits
 * of a student's card), and the basket (each item once, with how many).
 * An empty basket only asks for the student's balance.
 */
class SaleRequest extends BaseRequest
{
    public const MAX_LINES = 50;

    public const MAX_QUANTITY = 99;

    protected array $digitFields = ['card_code'];

    public function rules(): array
    {
        return [
            'market_id' => ['required', 'integer', 'exists:markets,id'],
            'card_code' => ['required', 'string', 'regex:/^[0-9]{6,20}$/'],
            'items' => ['present', 'array', 'max:'.self::MAX_LINES],
            'items.*.item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
        ];
    }

    public function attributes(): array
    {
        return [
            'market_id' => 'السوق',
            'card_code' => 'رقم البطاقة',
            'items' => 'السلة',
            'items.*.item_id' => 'البضاعة',
            'items.*.quantity' => 'العدد',
        ];
    }
}
