<?php

namespace App\Http\Requests\Market;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Rules\Picture;
use Illuminate\Validation\Rule;

/**
 * An item of a points market: its name, a description, its price in
 * points, how many are left to sell (empty: no limit), whether the sales
 * desk offers it, and an optional picture (or a wish to remove the one it
 * has).
 *
 * Context: market_id => the market of the item (required),
 *          ignore_id => the item being edited (null when adding one).
 */
class MarketItemRequest extends BaseRequest
{
    public const MAX_PRICE = 100000;

    public const MAX_STOCK = 100000;

    public const PICTURE_MAX_KILOBYTES = 2048;

    protected array $digitFields = ['price', 'stock'];

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('market_items', 'name')
                    ->where('market_id', $this->context('market_id'))
                    ->ignore($this->context('ignore_id')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PRICE],
            'stock' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_STOCK],
            'is_available' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'file', 'max:'.self::PICTURE_MAX_KILOBYTES, new Picture],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'يوجد في هذا السوق بضاعة بنفس الاسم.',
            'price.min' => 'السعر نقطة واحدة على الأقل.',
            'image.max' => 'حجم الصورة لا يتجاوز 2 ميغابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم البضاعة',
            'description' => 'الوصف',
            'price' => 'السعر بالنقاط',
            'stock' => 'الكمية المتبقية',
            'is_available' => 'معروضة للبيع',
            'image' => 'الصورة',
            'remove_image' => 'إزالة الصورة',
        ];
    }
}
