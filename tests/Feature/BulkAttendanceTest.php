<?php

namespace Tests\Feature;

use App\Actions\Attendance\BulkAttendanceResult;
use App\Actions\Attendance\RecordBulkAttendance;
use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Actions\Teachers\SaveTeacher;
use App\Enums\AttendanceMethod;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\Student;
use App\Services\Teachers\TeacherCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

/**
 * The attendance of many students of a course recorded at once: chosen
 * by name, or typed (or scanned) as card numbers.
 */
class BulkAttendanceTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    private Course $course;

    private Circle $circle;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday afternoon.
        $this->travelTo('2026-10-07 17:00:00');

        $this->course = Course::factory()->create(['start_date' => '2026-09-01', 'points_per_attendance' => 2]);
        $this->circle = Circle::factory()->create(['course_id' => $this->course->getKey()]);
    }

    private function student(string $name): Enrollment
    {
        return $this->enroll(Student::factory()->create(['full_name' => $name]), $this->course, $this->circle, '2026-09-01');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function record(array $input): BulkAttendanceResult
    {
        return app(RecordBulkAttendance::class)->handle($input + [
            'course_id' => $this->course->getKey(),
            'attended_on' => '2026-10-05',
        ]);
    }

    public function test_students_chosen_by_name_are_recorded_on_the_day_with_their_points(): void
    {
        $sami = $this->student('سامي');
        $ahmad = $this->student('أحمد');
        $this->student('غائب');

        $result = $this->record(['enrollment_ids' => [$sami->getKey(), $ahmad->getKey()]]);

        $this->assertSame(['أحمد', 'سامي'], $result->recorded);
        $this->assertFalse($result->hasProblems());
        $this->assertSame('سُجّل حضور طالبان ليوم 05/10/2026', $result->title());
        $this->assertSame([], $result->details());

        $this->assertSame(2, Attendance::query()->count());
        $this->assertSame(['2026-10-05'], CourseSession::query()->pluck('session_date')->map->toDateString()->all());
        $this->assertTrue(Attendance::query()->get()->every(fn (Attendance $attendance): bool => $attendance->method === AttendanceMethod::Manual));
        $this->assertSame(4, (int) PointTransaction::query()->sum('points'));
    }

    public function test_card_numbers_are_read_from_any_text_once_each(): void
    {
        $sami = $this->student('سامي');
        $ahmad = $this->student('أحمد');
        $arabicDigits = strtr($ahmad->card_code, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

        // One a line as the scanner types them, with spaces, commas, Arabic digits, and one twice.
        $result = $this->record(['card_codes' => "{$sami->card_code}\n {$arabicDigits} ، {$sami->card_code}\n"]);

        $this->assertSame(['أحمد', 'سامي'], $result->recorded);
        $this->assertSame(2, Attendance::query()->count());

        // A student both ticked and typed is recorded once.
        $this->assertSame(['أحمد'], $this->record([
            'attended_on' => '2026-10-07',
            'enrollment_ids' => [$ahmad->getKey()],
            'card_codes' => $ahmad->card_code,
        ])->recorded);
    }

    public function test_what_cannot_be_recorded_is_said_and_does_not_stop_the_others(): void
    {
        $present = $this->student('حاضر مسبقاً');
        $this->attend($present, '2026-10-05');
        $gone = $this->student('خارج');
        app(UpdateEnrollmentDates::class)->handle($gone, ['enrolled_on' => '2026-09-01', 'left_on' => '2026-10-02']);
        $elsewhere = $this->enroll(Student::factory()->create(['full_name' => 'من دورة أخرى']));
        $teacher = app(SaveTeacher::class)->handle(['full_name' => 'الشيخ ياسر']);
        $teacherCard = app(TeacherCards::class)->of($teacher, $this->course)->card_code;
        $sami = $this->student('سامي');

        $result = $this->record([
            'enrollment_ids' => [$present->getKey(), $gone->getKey(), $sami->getKey()],
            'card_codes' => "{$elsewhere->card_code} 9999999999 123 {$teacherCard}",
        ]);

        $this->assertSame(['سامي'], $result->recorded);
        $this->assertSame(['حاضر مسبقاً'], $result->alreadyRecorded);
        $this->assertSame([['name' => 'خارج', 'reason' => 'التاريخ بعد خروج الطالب من الدورة.']], $result->refused);
        $this->assertSame(["من دورة أخرى ({$elsewhere->card_code})"], $result->notInCourse);
        $this->assertSame(['9999999999', '123'], $result->unknownCodes);
        $this->assertSame([$teacherCard], $result->teacherCodes);
        $this->assertTrue($result->hasProblems());
        $this->assertSame([
            'حضورهم مسجَّل مسبقاً: حاضر مسبقاً',
            'لم يُسجَّل خارج: التاريخ بعد خروج الطالب من الدورة.',
            "ليسوا في هذه الدورة: من دورة أخرى ({$elsewhere->card_code})",
            'أرقام لا تعود إلى بطاقة طالب: 9999999999، 123',
            "بطاقات أساتذة (يُسجَّل حضورهم من صفحة المسح): {$teacherCard}",
        ], $result->details());

        $this->assertSame(2, Attendance::query()->count());
    }

    public function test_a_cancelled_session_refuses_everyone_with_its_reason(): void
    {
        $sami = $this->student('سامي');
        CourseSession::factory()->cancelled()->create(['course_id' => $this->course->getKey(), 'session_date' => '2026-10-05']);

        $result = $this->record(['enrollment_ids' => [$sami->getKey()]]);

        $this->assertSame([], $result->recorded);
        $this->assertSame('لم يُسجَّل حضور أي طالب جديد ليوم 05/10/2026', $result->title());
        $this->assertSame([['name' => 'سامي', 'reason' => 'جلسة هذا اليوم ملغاة. أعد فتحها أولاً.']], $result->refused);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_the_choice_is_validated(): void
    {
        $sami = $this->student('سامي');

        foreach ([
            [['enrollment_ids' => [], 'card_codes' => null], ['enrollment_ids', 'card_codes']],
            [['enrollment_ids' => [$sami->getKey()], 'attended_on' => '2026-10-08'], ['attended_on']],
            [['enrollment_ids' => [$sami->getKey()], 'course_id' => 999999], ['course_id']],
            [['enrollment_ids' => [$sami->getKey(), $sami->getKey()]], ['enrollment_ids.0', 'enrollment_ids.1']],
        ] as [$input, $fields]) {
            try {
                $this->record($input);
                $this->fail('The input was accepted: '.json_encode($input, JSON_UNESCAPED_UNICODE));
            } catch (ValidationException $exception) {
                $this->assertSame($fields, array_keys($exception->errors()));
            }
        }

        $this->assertSame(0, Attendance::query()->count());
    }
}
