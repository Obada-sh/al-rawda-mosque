<?php

namespace App\Http\Requests\Activities;

use App\Enums\ActivityAudience;
use App\Http\Requests\BaseRequest;
use App\Models\Enrollment;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Recording an activity: what it was, when, for whom (a circle or the
 * whole mosque), what each student who attended gains, and who attended.
 *
 * An activity is recorded once it has taken place, so its date is never
 * in the future.
 */
class ActivityRequest extends BaseRequest
{
    public const MAX_POINTS = 1000;

    /** More students than a mosque has: a guard against a broken request, not a limit of the mosque. */
    public const MAX_ATTENDEES = 5000;

    protected array $digitFields = ['points'];

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'activity_date' => ['required', 'date', 'before_or_equal:today'],
            'audience' => ['required', Rule::enum(ActivityAudience::class)],
            'circle_id' => ['nullable', 'required_if:audience,'.ActivityAudience::Circle->value, 'integer', 'exists:circles,id'],
            'points' => ['required', 'integer', 'min:0', 'max:'.self::MAX_POINTS],
            'notes' => ['nullable', 'string', 'max:5000'],
            'enrollment_ids' => ['nullable', 'array', 'max:'.self::MAX_ATTENDEES, $this->everyStudentExists()],
            'enrollment_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'اكتب اسم النشاط.',
            'activity_date.before_or_equal' => 'يُسجَّل النشاط بعد إقامته: تاريخه لا يمكن أن يكون في المستقبل.',
            'circle_id.required_if' => 'اختر الحلقة التي أقيم لها النشاط.',
            'points.min' => 'نقاط الحضور لا تكون سالبة. اكتب 0 إن كان النشاط بلا نقاط.',
            'points.max' => 'نقاط الحضور لا تتجاوز '.self::MAX_POINTS.'.',
            'enrollment_ids.*.distinct' => 'اختير أحد الطلاب مرتين.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'اسم النشاط',
            'activity_date' => 'تاريخ النشاط',
            'audience' => 'النشاط لـ',
            'circle_id' => 'الحلقة',
            'points' => 'نقاط الحضور',
            'notes' => 'ملاحظات',
            'enrollment_ids' => 'الحاضرون',
        ];
    }

    /**
     * One question to the database for the whole list, instead of one for
     * each student.
     */
    private function everyStudentExists(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $ids = array_values(array_unique(array_filter((array) $value, 'is_numeric')));

            if ($ids !== [] && Enrollment::query()->whereKey($ids)->count() !== count($ids)) {
                $fail('أحد الطلاب المختارين لم يعد موجوداً. حدّث الصفحة واختر من جديد.');
            }
        };
    }
}
