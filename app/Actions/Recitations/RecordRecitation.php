<?php

namespace App\Actions\Recitations;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Recitations\RecitationRequest;
use App\Models\Enrollment;
use App\Models\Recitation;
use App\Models\Teacher;
use App\Services\Enrollments\EnrollmentCalendar;
use App\Services\Memorization\MemorizationLedger;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a recitation and brings everything that depends on it up to
 * date: the student's memorized pages and counter, the points earned for
 * new pages, and a pending exam when a juz becomes complete.
 *
 * The recitation is stored on the enrollment, so it is always known in
 * which course it happened.
 */
final class RecordRecitation
{
    public function __construct(
        private readonly EnrollmentCalendar $calendar,
        private readonly MemorizationLedger $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation student.archived | teacher.inactive | date.*
     */
    public function handle(array $input): Recitation
    {
        $data = RecitationRequest::validateData($input);

        $enrollment = Enrollment::query()->with(['course', 'student'])->findOrFail($data['enrollment_id']);
        $recitedOn = Dates::parse($data['recited_on']);

        if ($enrollment->student->isArchived()) {
            throw BusinessRuleViolation::because('student.archived', 'الطالب مؤرشف. أعد تفعيله قبل إضافة تسميع له.');
        }

        if (! Teacher::query()->whereKey($data['teacher_id'])->where('is_active', true)->exists()) {
            throw BusinessRuleViolation::because('teacher.inactive', 'الأستاذ المختار غير نشط.');
        }

        $this->calendar->assertDateAllowed($enrollment, $recitedOn);

        return DB::transaction(function () use ($enrollment, $recitedOn, $data): Recitation {
            $fromPage = (int) $data['from_page'];
            $toPage = (int) $data['to_page'];

            $recitation = Recitation::query()->create([
                'enrollment_id' => $enrollment->getKey(),
                'teacher_id' => $data['teacher_id'],
                'recited_on' => $recitedOn->toDateString(),
                'from_page' => $fromPage,
                'to_page' => $toPage,
                'pages_count' => $toPage - $fromPage + 1,
                // The rate is frozen here so a later change to the course
                // rate never rewrites the points of this recitation.
                'points_per_page' => $enrollment->course->points_per_page,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->ledger->rebuildFor($enrollment->student_id);

            return $recitation->refresh();
        });
    }
}
