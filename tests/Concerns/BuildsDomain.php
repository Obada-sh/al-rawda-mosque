<?php

namespace Tests\Concerns;

use App\Actions\Activities\SaveActivity;
use App\Actions\Attendance\RecordManualAttendance;
use App\Actions\Enrollments\EnrollStudent;
use App\Actions\Enrollments\TransferStudent;
use App\Actions\Exams\OpenJuzExam;
use App\Actions\Exams\RecordJuzExamResult;
use App\Actions\Finance\SaveExpense;
use App\Actions\Points\AwardManualPoints;
use App\Actions\Recitations\RecordRecitation;
use App\Enums\ActivityAudience;
use App\Enums\ExamGrade;
use App\Enums\ExpenseKind;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\CircleTransfer;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\JuzExam;
use App\Models\PointTransaction;
use App\Models\Recitation;
use App\Models\Student;
use App\Models\Teacher;
use Closure;
use PHPUnit\Framework\Assert;

/**
 * Small builders so each test states only what matters to it.
 * Everything goes through the real actions.
 */
trait BuildsDomain
{
    /**
     * Enrolls a student through the EnrollStudent action. Missing pieces
     * are created; the enrollment date defaults to the course start.
     */
    protected function enroll(?Student $student = null, ?Course $course = null, ?Circle $circle = null, ?string $enrolledOn = null): Enrollment
    {
        $student ??= Student::factory()->create();
        $course ??= $circle?->course ?? Course::factory()->create();
        $circle ??= Circle::factory()->create(['course_id' => $course->getKey()]);

        return app(EnrollStudent::class)->handle([
            'student_id' => $student->getKey(),
            'course_id' => $course->getKey(),
            'circle_id' => $circle->getKey(),
            'enrolled_on' => $enrolledOn ?? $course->start_date->toDateString(),
        ]);
    }

    /**
     * Records a recitation through the RecordRecitation action.
     */
    protected function recite(Enrollment $enrollment, int $fromPage, int $toPage, ?string $recitedOn = null, ?Teacher $teacher = null): Recitation
    {
        $teacher ??= Teacher::factory()->create();

        return app(RecordRecitation::class)->handle([
            'enrollment_id' => $enrollment->getKey(),
            'teacher_id' => $teacher->getKey(),
            'recited_on' => $recitedOn ?? now()->toDateString(),
            'from_page' => $fromPage,
            'to_page' => $toPage,
        ]);
    }

    /**
     * Records a manual attendance through the RecordManualAttendance action.
     */
    protected function attend(Enrollment $enrollment, ?string $attendedOn = null): Attendance
    {
        return app(RecordManualAttendance::class)->handle([
            'enrollment_id' => $enrollment->getKey(),
            'attended_on' => $attendedOn ?? now()->toDateString(),
        ]);
    }

    /**
     * Adds (or deducts) points by hand through the AwardManualPoints action.
     */
    protected function award(Enrollment $enrollment, int $points, ?string $occurredOn = null): PointTransaction
    {
        return app(AwardManualPoints::class)->handle([
            'enrollment_id' => $enrollment->getKey(),
            'points' => $points,
            'reason' => 'مسابقة',
            'occurred_on' => $occurredOn ?? now()->toDateString(),
        ]);
    }

    /**
     * A juz exam of the student in this course, opened through the
     * OpenJuzExam action and graded through RecordJuzExamResult on the
     * given day; without a day it is left waiting.
     */
    protected function examine(Enrollment $enrollment, int $juz, ?string $examDate = null, ExamGrade $grade = ExamGrade::Good): JuzExam
    {
        $exam = app(OpenJuzExam::class)->handle([
            'student_id' => $enrollment->student_id,
            'juz_no' => $juz,
            'enrollment_id' => $enrollment->getKey(),
        ]);

        if ($examDate === null) {
            return $exam;
        }

        return app(RecordJuzExamResult::class)->handle($exam, [
            'exam_date' => $examDate,
            'examiner_teacher_id' => Teacher::factory()->create()->getKey(),
            'grade' => $grade->value,
        ]);
    }

    /**
     * Records an activity through the SaveActivity action: for the given
     * circle, or for the whole mosque when there is none.
     *
     * @param  list<Enrollment>  $attendees
     * @param  array<string, mixed>  $overrides
     */
    protected function holdActivity(array $attendees = [], ?Circle $circle = null, array $overrides = []): Activity
    {
        return app(SaveActivity::class)->handle($overrides + [
            'title' => 'رحلة الربيع',
            'activity_date' => now()->toDateString(),
            'audience' => ($circle === null ? ActivityAudience::Mosque : ActivityAudience::Circle)->value,
            'circle_id' => $circle?->getKey(),
            'points' => 5,
            'enrollment_ids' => array_map(fn (Enrollment $enrollment): int => $enrollment->getKey(), $attendees),
        ]);
    }

    /**
     * Moves a student to another circle through the TransferStudent action.
     */
    protected function moveToCircle(Enrollment $enrollment, Circle $circle, string $transferredOn): CircleTransfer
    {
        return app(TransferStudent::class)->handle($enrollment, [
            'to_circle_id' => $circle->getKey(),
            'transferred_on' => $transferredOn,
        ]);
    }

    /**
     * Records an expense through the SaveExpense action.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function spend(array $overrides = []): Expense
    {
        $overrides['department_id'] ??= Department::factory()->create()->getKey();

        return app(SaveExpense::class)->handle($overrides + [
            'kind' => ExpenseKind::Itemized->value,
            'item_name' => 'مياه شرب',
            'quantity' => '2',
            'unit_price' => '1500',
            'expense_date' => now()->toDateString(),
        ]);
    }

    /**
     * Asserts the callback breaks the given business rule.
     */
    protected function assertViolates(string $rule, Closure $callback): void
    {
        try {
            $callback();
        } catch (BusinessRuleViolation $violation) {
            Assert::assertSame($rule, $violation->rule, 'A different business rule was violated: '.$violation->getMessage());

            return;
        }

        Assert::fail("Expected business rule [{$rule}] to be violated, but nothing was thrown.");
    }
}
