<?php

namespace App\Http\Requests\Export;

use App\Enums\RankingCriterion;
use App\Http\Requests\BaseRequest;
use App\Services\Export\LeadersTemplates;
use App\Services\Statistics\CircleLeaders;
use Illuminate\Validation\Rule;

/**
 * What the admin chooses when exporting the slides of the leaders of
 * each circle.
 */
class LeadersSlidesRequest extends BaseRequest
{
    public const DEFAULT_TOP = 3;

    public const DEFAULT_SECONDS_PER_SLIDE = 8;

    public const MIN_SECONDS_PER_SLIDE = 2;

    public const MAX_SECONDS_PER_SLIDE = 300;

    protected array $digitFields = ['top', 'seconds_per_slide'];

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
            'top' => ['required', 'integer', 'min:1', 'max:'.LeadersTemplates::MAX_LEADERS],
            'criterion' => ['required', Rule::in(array_map(
                fn (RankingCriterion $criterion): string => $criterion->value,
                CircleLeaders::CRITERIA,
            ))],
            'seconds_per_slide' => ['required', 'integer', 'min:'.self::MIN_SECONDS_PER_SLIDE, 'max:'.self::MAX_SECONDS_PER_SLIDE],
            'opens_as_show' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'until.after_or_equal' => 'نهاية الفترة لا يمكن أن تكون قبل بدايتها.',
        ];
    }

    public function attributes(): array
    {
        return [
            'course_id' => 'الدورة',
            'from' => 'من تاريخ',
            'until' => 'إلى تاريخ',
            'top' => 'عدد الأوائل',
            'criterion' => 'معيار الترتيب',
            'seconds_per_slide' => 'مدة عرض الشريحة',
            'opens_as_show' => 'بدء العرض عند فتح الملف',
        ];
    }
}
