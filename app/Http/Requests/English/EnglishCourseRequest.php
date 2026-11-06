<?php

namespace App\Http\Requests\English;

use App\Enums\CourseStatus;
use App\Enums\Weekday;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Creating and editing an English course together with its weekly class
 * days. The price is what a student pays for the whole course, in Syrian
 * pounds (a student may be given a price of their own when enrolled).
 */
class EnglishCourseRequest extends BaseRequest
{
    /** One hundred million pounds: far above any course, far below what the column holds. */
    public const MAX_PRICE = 100_000_000;

    protected array $digitFields = ['price'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'english_level_id' => ['required', 'integer', 'exists:english_levels,id'],
            'price' => ['required', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::enum(CourseStatus::class)],

            'schedules' => ['required', 'array', 'min:1', 'max:7'],
            'schedules.*.weekday' => ['required', 'integer', 'distinct', Rule::in(Weekday::values())],
            'schedules.*.start_time' => ['required', 'date_format:H:i,H:i:s'],
            'schedules.*.end_time' => ['required', 'date_format:H:i,H:i:s', 'after:schedules.*.start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'price.integer' => 'سعر الدورة رقم صحيح بالليرة السورية، بلا فواصل.',
            'schedules.required' => 'حدّد يوم دوام واحداً على الأقل.',
            'schedules.min' => 'حدّد يوم دوام واحداً على الأقل.',
            'schedules.*.weekday.distinct' => 'لا يمكن تكرار نفس اليوم في أيام الدوام.',
            'schedules.*.end_time.after' => 'نهاية الدوام يجب أن تكون بعد بدايته.',
            'end_date.after_or_equal' => 'تاريخ نهاية الدورة يجب أن يكون بعد بدايتها.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الدورة',
            'english_level_id' => 'المستوى',
            'price' => 'سعر الدورة',
            'start_date' => 'تاريخ البداية',
            'end_date' => 'تاريخ النهاية',
            'status' => 'حالة الدورة',
            'schedules' => 'أيام الدوام',
            'schedules.*.weekday' => 'اليوم',
            'schedules.*.start_time' => 'بداية الدوام',
            'schedules.*.end_time' => 'نهاية الدوام',
        ];
    }
}
