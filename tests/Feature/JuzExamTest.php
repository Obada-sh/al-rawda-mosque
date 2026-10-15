<?php

namespace Tests\Feature;

use App\Actions\Exams\DeleteJuzExam;
use App\Actions\Exams\OpenJuzExam;
use App\Actions\Exams\RecordJuzExamResult;
use App\Actions\Recitations\DeleteRecitation;
use App\Actions\Recitations\UpdateRecitation;
use App\Enums\ExamGrade;
use App\Enums\ExamOrigin;
use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\JuzExam;
use App\Models\QuranJuz;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class JuzExamTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    private Student $student;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');

        $this->student = Student::factory()->create();
        $this->enrollment = $this->enroll($this->student);
    }

    public function test_the_juz_reference_data_is_installed_with_the_schema(): void
    {
        $this->assertSame(30, QuranJuz::query()->count());

        $lastJuz = QuranJuz::query()->findOrFail(30);
        $this->assertSame(582, $lastJuz->from_page);
        $this->assertSame(604, $lastJuz->to_page);

        $this->assertSame(604, (int) QuranJuz::query()->get()->sum(fn (QuranJuz $juz): int => $juz->pagesCount()));
    }

    public function test_completing_a_juz_opens_a_pending_exam_and_notifies_the_admin(): void
    {
        $this->recite($this->enrollment, 582, 604, '2026-10-05');

        $exam = JuzExam::query()->sole();
        $this->assertSame(30, $exam->juz_no);
        $this->assertSame($this->student->getKey(), $exam->student_id);
        $this->assertSame($this->enrollment->getKey(), $exam->enrollment_id);
        $this->assertSame(ExamStatus::Pending, $exam->status);
        $this->assertSame(ExamOrigin::Auto, $exam->origin);
        $this->assertSame('2026-10-05', $exam->triggered_on->toDateString());

        $this->assertCount(1, $this->adminNotifier->pendingExams);
        $this->assertTrue($this->adminNotifier->pendingExams[0]->is($exam));
    }

    public function test_an_incomplete_juz_opens_no_exam(): void
    {
        $this->recite($this->enrollment, 582, 603);

        $this->assertSame(0, JuzExam::query()->count());
        $this->assertCount(0, $this->adminNotifier->pendingExams);
    }

    public function test_a_juz_completed_over_several_recitations_is_dated_by_the_last_one(): void
    {
        $this->recite($this->enrollment, 1, 10, '2026-10-01');
        $this->assertSame(0, JuzExam::query()->count());

        $this->recite($this->enrollment, 11, 21, '2026-10-06');

        $exam = JuzExam::query()->sole();
        $this->assertSame(1, $exam->juz_no);
        $this->assertSame('2026-10-06', $exam->triggered_on->toDateString());
    }

    public function test_a_juz_can_be_completed_across_two_courses(): void
    {
        $secondEnrollment = $this->enroll($this->student, Course::factory()->create());

        $this->recite($this->enrollment, 22, 30, '2026-10-01');
        $this->recite($secondEnrollment, 31, 41, '2026-10-02');

        $exam = JuzExam::query()->sole();
        $this->assertSame(2, $exam->juz_no);
        $this->assertSame($secondEnrollment->getKey(), $exam->enrollment_id);
    }

    public function test_one_recitation_can_complete_several_juz(): void
    {
        $this->recite($this->enrollment, 1, 41);

        $this->assertSame([1, 2], JuzExam::query()->orderBy('juz_no')->pluck('juz_no')->all());
        $this->assertCount(2, $this->adminNotifier->pendingExams);
    }

    public function test_the_same_juz_never_gets_a_second_automatic_exam(): void
    {
        $this->recite($this->enrollment, 582, 604, '2026-10-01');
        $this->recite($this->enrollment, 590, 600, '2026-10-02');
        $this->recite($this->enrollment, 1, 5, '2026-10-03');

        $this->assertSame(1, JuzExam::query()->count());
        $this->assertCount(1, $this->adminNotifier->pendingExams);
    }

    public function test_a_pending_automatic_exam_is_withdrawn_when_the_juz_is_no_longer_complete(): void
    {
        $recitation = $this->recite($this->enrollment, 582, 604);
        $this->assertSame(1, JuzExam::query()->count());

        app(UpdateRecitation::class)->handle($recitation, [
            'teacher_id' => $recitation->teacher_id,
            'recited_on' => $recitation->recited_on->toDateString(),
            'from_page' => 582,
            'to_page' => 600,
        ]);

        $this->assertSame(0, JuzExam::query()->count());
    }

    public function test_an_exam_already_taken_survives_the_deletion_of_its_recitation(): void
    {
        $recitation = $this->recite($this->enrollment, 582, 604);
        $exam = JuzExam::query()->sole();

        app(RecordJuzExamResult::class)->handle($exam, [
            'exam_date' => '2026-10-07',
            'examiner_teacher_id' => Teacher::factory()->create()->getKey(),
            'grade' => ExamGrade::Excellent->value,
        ]);

        app(DeleteRecitation::class)->handle($recitation);

        $this->assertModelExists($exam);
        $this->assertSame(ExamStatus::Done, $exam->fresh()->status);
    }

    public function test_entering_a_result_marks_the_exam_done(): void
    {
        $this->recite($this->enrollment, 582, 604);
        $exam = JuzExam::query()->sole();
        $examiner = Teacher::factory()->create();

        $exam = app(RecordJuzExamResult::class)->handle($exam, [
            'exam_date' => '2026-10-06',
            'examiner_teacher_id' => $examiner->getKey(),
            'grade' => 'very_good',
            'notes' => 'تثبيت جيد',
        ]);

        $this->assertSame(ExamStatus::Done, $exam->status);
        $this->assertSame(ExamGrade::VeryGood, $exam->grade);
        $this->assertSame($examiner->getKey(), $exam->examiner_teacher_id);
        $this->assertSame('2026-10-06', $exam->exam_date->toDateString());
        $this->assertSame(0, JuzExam::query()->pending()->count());
    }

    public function test_an_exam_result_needs_a_date_an_examiner_and_a_valid_grade(): void
    {
        $exam = app(OpenJuzExam::class)->handle(['student_id' => $this->student->getKey(), 'juz_no' => 5]);
        $examiner = Teacher::factory()->create();

        $invalid = [
            'no grade' => ['grade' => null],
            'unknown grade' => ['grade' => 'superb'],
            'no examiner' => ['examiner_teacher_id' => null],
            'future date' => ['exam_date' => '2026-10-08'],
        ];

        foreach ($invalid as $case => $overrides) {
            try {
                app(RecordJuzExamResult::class)->handle($exam, $overrides + [
                    'exam_date' => '2026-10-07',
                    'examiner_teacher_id' => $examiner->getKey(),
                    'grade' => 'good',
                ]);
                $this->fail("Input was accepted: {$case}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(ExamStatus::Pending, $exam->fresh()->status);
    }

    public function test_the_admin_can_open_an_exam_by_hand(): void
    {
        $exam = app(OpenJuzExam::class)->handle([
            'student_id' => $this->student->getKey(),
            'juz_no' => 29,
            'enrollment_id' => $this->enrollment->getKey(),
        ]);

        $this->assertSame(ExamOrigin::Manual, $exam->origin);
        $this->assertSame(ExamStatus::Pending, $exam->status);
        $this->assertSame('2026-10-07', $exam->triggered_on->toDateString());
        $this->assertCount(0, $this->adminNotifier->pendingExams);
    }

    public function test_a_manual_exam_is_never_withdrawn_by_the_system(): void
    {
        app(OpenJuzExam::class)->handle(['student_id' => $this->student->getKey(), 'juz_no' => 29]);

        $recitation = $this->recite($this->enrollment, 1, 3);
        app(DeleteRecitation::class)->handle($recitation);

        $this->assertSame(1, JuzExam::query()->count());
    }

    public function test_a_juz_with_a_pending_exam_cannot_get_another(): void
    {
        app(OpenJuzExam::class)->handle(['student_id' => $this->student->getKey(), 'juz_no' => 29]);

        $this->assertViolates('exam.pending_exists', fn () => app(OpenJuzExam::class)->handle([
            'student_id' => $this->student->getKey(),
            'juz_no' => 29,
        ]));
    }

    public function test_a_retake_can_be_opened_after_the_first_exam_is_done(): void
    {
        $first = app(OpenJuzExam::class)->handle(['student_id' => $this->student->getKey(), 'juz_no' => 29]);
        app(RecordJuzExamResult::class)->handle($first, [
            'exam_date' => '2026-10-07',
            'examiner_teacher_id' => Teacher::factory()->create()->getKey(),
            'grade' => ExamGrade::Retake->value,
        ]);

        $retake = app(OpenJuzExam::class)->handle(['student_id' => $this->student->getKey(), 'juz_no' => 29]);

        $this->assertSame(ExamStatus::Pending, $retake->status);
        $this->assertSame(2, JuzExam::query()->where('juz_no', 29)->count());
    }

    public function test_the_course_of_a_manual_exam_must_be_one_of_the_students_courses(): void
    {
        $someoneElse = $this->enroll();

        $this->assertViolates('exam.enrollment_mismatch', fn () => app(OpenJuzExam::class)->handle([
            'student_id' => $this->student->getKey(),
            'juz_no' => 3,
            'enrollment_id' => $someoneElse->getKey(),
        ]));
    }

    public function test_an_exam_can_be_deleted(): void
    {
        $exam = app(OpenJuzExam::class)->handle(['student_id' => $this->student->getKey(), 'juz_no' => 29]);

        app(DeleteJuzExam::class)->handle($exam);

        $this->assertModelMissing($exam);
    }
}
