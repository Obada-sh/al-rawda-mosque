<?php

namespace App\Http\Requests\Finance;

use App\Enums\ExpenseKind;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Recording an expense, in Syrian pounds: an item with its quantity and
 * unit price (the total is never entered: it is computed), or only the
 * amount paid with a description.
 */
class ExpenseRequest extends BaseRequest
{
    public const MAX_QUANTITY = '999999.999';

    public const MAX_UNIT_PRICE = '999999999999.99';

    /** The largest amount the total column holds (DECIMAL(16,2)). */
    public const MAX_AMOUNT = '99999999999999.99';

    protected array $digitFields = ['quantity', 'unit_price', 'amount'];

    public function rules(): array
    {
        $itemized = 'exclude_unless:kind,'.ExpenseKind::Itemized->value;
        $lumpSum = 'exclude_unless:kind,'.ExpenseKind::LumpSum->value;

        return [
            'kind' => ['required', Rule::enum(ExpenseKind::class)],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'item_name' => ['required', 'string', 'min:2', 'max:255'],
            'quantity' => [$itemized, 'required', 'numeric', 'gt:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
            'unit_price' => [$itemized, 'required', 'numeric', 'min:0', 'max:'.self::MAX_UNIT_PRICE, 'decimal:0,2'],
            'amount' => [$lumpSum, 'required', 'numeric', 'gt:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,2'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.gt' => 'الكمية يجب أن تكون أكبر من صفر.',
            'quantity.decimal' => 'الكمية تقبل ثلاث خانات عشرية على الأكثر.',
            'unit_price.decimal' => 'السعر يقبل خانتين عشريتين على الأكثر.',
            'amount.gt' => 'المبلغ المدفوع يجب أن يكون أكبر من صفر.',
            'amount.decimal' => 'المبلغ يقبل خانتين عشريتين على الأكثر.',
            'expense_date.before_or_equal' => 'تاريخ المصروف لا يمكن أن يكون في المستقبل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'kind' => 'طريقة التسجيل',
            'department_id' => 'القسم',
            'item_name' => 'الصنف / الوصف',
            'quantity' => 'الكمية',
            'unit_price' => 'سعر الواحدة',
            'amount' => 'المبلغ المدفوع',
            'expense_date' => 'تاريخ المصروف',
            'notes' => 'ملاحظات',
        ];
    }
}
