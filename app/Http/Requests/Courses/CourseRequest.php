<?php

namespace App\Http\Requests\Courses;

use App\Enums\CourseStatus;
use App\Enums\Weekday;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Creating and editing a course together with its weekly timetable.
 */
class CourseRequest extends BaseRequest
{
    public const MAX_POINTS = 1000;

    protected array $digitFields = ['points_per_page', 'points_per_attendance'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'points_per_page' => ['required', 'integer', 'min:0', 'max:'.self::MAX_POINTS],
            'points_per_attendance' => ['required', 'integer', 'min:0', 'max:'.self::MAX_POINTS],
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
            'start_date' => 'تاريخ البداية',
            'end_date' => 'تاريخ النهاية',
            'points_per_page' => 'نقاط الصفحة',
            'points_per_attendance' => 'نقاط الحضور',
            'status' => 'حالة الدورة',
            'schedules' => 'أيام الدوام',
            'schedules.*.weekday' => 'اليوم',
            'schedules.*.start_time' => 'بداية الدوام',
            'schedules.*.end_time' => 'نهاية الدوام',
        ];
    }
}
