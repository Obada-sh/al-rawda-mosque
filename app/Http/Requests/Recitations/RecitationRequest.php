<?php

namespace App\Http\Requests\Recitations;

use App\Http\Requests\BaseRequest;
use App\Support\QuranPages;

/**
 * Recording a recitation: a page range heard by a teacher.
 *
 * Context: updating => true when editing (the enrollment cannot change;
 * a recitation entered for the wrong student is deleted and re-entered).
 */
class RecitationRequest extends BaseRequest
{
    protected array $digitFields = ['from_page', 'to_page'];

    public function rules(): array
    {
        $rules = [
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'recited_on' => ['required', 'date', 'before_or_equal:today'],
            'from_page' => ['required', 'integer', 'min:'.QuranPages::FIRST, 'max:'.QuranPages::LAST],
            'to_page' => ['required', 'integer', 'gte:from_page', 'max:'.QuranPages::LAST],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if (! $this->context('updating', false)) {
            $rules['enrollment_id'] = ['required', 'integer', 'exists:enrollments,id'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'to_page.gte' => 'صفحة النهاية يجب أن تكون بعد صفحة البداية أو مساوية لها.',
            'recited_on.before_or_equal' => 'تاريخ التسميع لا يمكن أن يكون في المستقبل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'enrollment_id' => 'الطالب',
            'teacher_id' => 'الأستاذ المسمِّع',
            'recited_on' => 'تاريخ التسميع',
            'from_page' => 'من صفحة',
            'to_page' => 'إلى صفحة',
            'notes' => 'ملاحظات',
        ];
    }
}
