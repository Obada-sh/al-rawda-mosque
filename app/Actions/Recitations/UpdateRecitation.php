<?php

namespace App\Actions\Recitations;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Recitations\RecitationRequest;
use App\Models\Recitation;
use App\Models\Teacher;
use App\Services\Enrollments\EnrollmentCalendar;
use App\Services\Memorization\MemorizationLedger;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrects a recitation (pages, date, teacher, notes) and recomputes the
 * student's pages, points and exams. The student and course of a
 * recitation cannot be changed: delete it and enter it again instead.
 */
final class UpdateRecitation
{
    public function __construct(
        private readonly EnrollmentCalendar $calendar,
        private readonly MemorizationLedger $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation teacher.inactive | date.*
     */
    public function handle(Recitation $recitation, array $input): Recitation
    {
        $data = RecitationRequest::validateData($input, ['updating' => true]);

        $enrollment = $recitation->enrollment()->with(['course', 'student'])->firstOrFail();
        $recitedOn = Dates::parse($data['recited_on']);

        $teacherChanged = (int) $data['teacher_id'] !== (int) $recitation->teacher_id;

        if ($teacherChanged && ! Teacher::query()->whereKey($data['teacher_id'])->where('is_active', true)->exists()) {
            throw BusinessRuleViolation::because('teacher.inactive', 'الأستاذ المختار غير نشط.');
        }

        $this->calendar->assertDateAllowed($enrollment, $recitedOn);

        return DB::transaction(function () use ($recitation, $enrollment, $recitedOn, $data): Recitation {
            $fromPage = (int) $data['from_page'];
            $toPage = (int) $data['to_page'];

            $recitation->fill([
                'teacher_id' => $data['teacher_id'],
                'recited_on' => $recitedOn->toDateString(),
                'from_page' => $fromPage,
                'to_page' => $toPage,
                'pages_count' => $toPage - $fromPage + 1,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->ledger->rebuildFor($enrollment->student_id);

            return $recitation->refresh();
        });
    }
}
