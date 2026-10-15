<?php

namespace Tests\Feature;

use App\Actions\Recitations\DeleteRecitation;
use App\Actions\Recitations\RecordRecitation;
use App\Actions\Recitations\UpdateRecitation;
use App\Enums\PointSource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\Recitation;
use App\Models\Student;
use App\Models\StudentPage;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class RecitationTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    private Course $course;

    private Student $student;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');

        $this->course = Course::factory()->create(['points_per_page' => 2]);
        $this->student = Student::factory()->create();
        $this->enrollment = $this->enroll($this->student, $this->course);
    }

    private function pointsOf(Recitation $recitation): int
    {
        return (int) PointTransaction::query()->where('recitation_id', $recitation->getKey())->sum('points');
    }

    public function test_a_recitation_counts_its_pages_and_awards_points_for_them(): void
    {
        $teacher = Teacher::factory()->create();

        $recitation = $this->recite($this->enrollment, 10, 12, '2026-10-05', $teacher);

        $this->assertSame(3, $recitation->pages_count);
        $this->assertSame(3, $recitation->new_pages_count);
        $this->assertSame($teacher->getKey(), $recitation->teacher_id);
        $this->assertSame($this->enrollment->getKey(), $recitation->enrollment_id);
        $this->assertSame(3, $this->student->fresh()->recited_pages_count);
        $this->assertSame([10, 11, 12], StudentPage::query()->orderBy('page_no')->pluck('page_no')->all());

        $points = PointTransaction::query()->sole();
        $this->assertSame(6, $points->points);
        $this->assertSame(PointSource::Recitation, $points->source);
        $this->assertSame($this->enrollment->getKey(), $points->enrollment_id);
        $this->assertSame('2026-10-05', $points->occurred_on->toDateString());
    }

    public function test_pages_recited_before_are_recorded_but_earn_nothing(): void
    {
        $first = $this->recite($this->enrollment, 1, 3, '2026-10-01');
        $overlapping = $this->recite($this->enrollment, 2, 5, '2026-10-02');
        $repeat = $this->recite($this->enrollment, 1, 3, '2026-10-03');

        $this->assertSame(3, $first->fresh()->new_pages_count);
        $this->assertSame(2, $overlapping->new_pages_count);
        $this->assertSame(2, $overlapping->repeatedPagesCount());
        $this->assertSame(0, $repeat->new_pages_count);

        $this->assertSame(5, $this->student->fresh()->recited_pages_count);
        $this->assertSame(6, $this->pointsOf($first));
        $this->assertSame(4, $this->pointsOf($overlapping));
        $this->assertSame(0, $this->pointsOf($repeat));
        $this->assertSame(2, PointTransaction::query()->count());
    }

    public function test_a_back_dated_recitation_takes_the_pages_it_was_first_to_cover(): void
    {
        $later = $this->recite($this->enrollment, 1, 3, '2026-10-05');
        $earlier = $this->recite($this->enrollment, 1, 2, '2026-10-01');

        $this->assertSame(2, $earlier->new_pages_count);
        $this->assertSame(1, $later->fresh()->new_pages_count);
        $this->assertSame(4, $this->pointsOf($earlier));
        $this->assertSame(2, $this->pointsOf($later));
        $this->assertSame(3, $this->student->fresh()->recited_pages_count);

        $owners = StudentPage::query()->orderBy('page_no')->pluck('recitation_id', 'page_no')->all();
        $this->assertSame([1 => $earlier->getKey(), 2 => $earlier->getKey(), 3 => $later->getKey()], $owners);
    }

    public function test_correcting_a_recitation_recomputes_pages_and_points(): void
    {
        $recitation = $this->recite($this->enrollment, 1, 5, '2026-10-05');
        $newTeacher = Teacher::factory()->create();

        $updated = app(UpdateRecitation::class)->handle($recitation, [
            'teacher_id' => $newTeacher->getKey(),
            'recited_on' => '2026-10-04',
            'from_page' => 1,
            'to_page' => 2,
            'notes' => 'تصحيح',
        ]);

        $this->assertSame(2, $updated->pages_count);
        $this->assertSame(2, $updated->new_pages_count);
        $this->assertSame($newTeacher->getKey(), $updated->teacher_id);
        $this->assertSame(2, $this->student->fresh()->recited_pages_count);
        $this->assertSame(2, StudentPage::query()->count());

        $points = PointTransaction::query()->sole();
        $this->assertSame(4, $points->points);
        $this->assertSame('2026-10-04', $points->occurred_on->toDateString());
    }

    public function test_deleting_a_recitation_passes_its_pages_to_the_next_one(): void
    {
        $first = $this->recite($this->enrollment, 1, 3, '2026-10-01');
        $second = $this->recite($this->enrollment, 2, 4, '2026-10-02');
        $this->assertSame(1, $second->new_pages_count);

        app(DeleteRecitation::class)->handle($first);

        $this->assertModelMissing($first);
        $this->assertSame(3, $second->fresh()->new_pages_count);
        $this->assertSame(3, $this->student->fresh()->recited_pages_count);
        $this->assertSame(6, $this->pointsOf($second));
        $this->assertSame(1, PointTransaction::query()->count());
        $this->assertSame([2, 3, 4], StudentPage::query()->orderBy('page_no')->pluck('page_no')->all());
    }

    public function test_deleting_the_only_recitation_clears_everything_it_produced(): void
    {
        $recitation = $this->recite($this->enrollment, 1, 3);

        app(DeleteRecitation::class)->handle($recitation);

        $this->assertSame(0, $this->student->fresh()->recited_pages_count);
        $this->assertSame(0, StudentPage::query()->count());
        $this->assertSame(0, PointTransaction::query()->count());
    }

    public function test_changing_the_course_rate_never_rewrites_old_points(): void
    {
        $old = $this->recite($this->enrollment, 1, 2, '2026-10-01');

        $this->course->update(['points_per_page' => 5]);

        $new = $this->recite($this->enrollment, 3, 3, '2026-10-02');

        $this->assertSame(4, $this->pointsOf($old));
        $this->assertSame(5, $this->pointsOf($new));
        $this->assertSame(2, $old->fresh()->points_per_page);
        $this->assertSame(5, $new->points_per_page);
    }

    public function test_each_recitation_stays_in_the_course_it_was_recited_in(): void
    {
        $otherCourse = Course::factory()->create(['points_per_page' => 10]);
        $otherEnrollment = $this->enroll($this->student, $otherCourse);

        $inFirstCourse = $this->recite($this->enrollment, 1, 2, '2026-10-01');
        $inOtherCourse = $this->recite($otherEnrollment, 2, 3, '2026-10-02');

        // One counter for the student across all courses, each page once.
        $this->assertSame(3, $this->student->fresh()->recited_pages_count);
        $this->assertSame(1, $inOtherCourse->new_pages_count);

        // Per course: what was recited and what was earned there.
        $this->assertSame(2, (int) $this->enrollment->recitations()->sum('new_pages_count'));
        $this->assertSame(1, (int) $otherEnrollment->recitations()->sum('new_pages_count'));
        $this->assertSame(4, (int) $this->enrollment->pointTransactions()->sum('points'));
        $this->assertSame(10, (int) $otherEnrollment->pointTransactions()->sum('points'));

        // Every page can be traced to its course, teacher and date.
        $page = StudentPage::query()->where('page_no', 3)->sole();
        $this->assertSame($inOtherCourse->getKey(), $page->recitation_id);
        $this->assertSame($otherCourse->getKey(), $page->recitation->enrollment->course_id);
        $this->assertSame($inOtherCourse->teacher_id, $page->recitation->teacher_id);
        $this->assertSame('2026-10-02', $page->recitation->recited_on->toDateString());

        $this->assertSame($inFirstCourse->getKey(), StudentPage::query()->where('page_no', 1)->sole()->recitation_id);
    }

    public function test_one_students_pages_never_affect_another_student(): void
    {
        $otherEnrollment = $this->enroll(course: $this->course);

        $this->recite($this->enrollment, 1, 5);
        $recitation = $this->recite($otherEnrollment, 1, 5);

        $this->assertSame(5, $recitation->new_pages_count);
        $this->assertSame(5, $otherEnrollment->student->fresh()->recited_pages_count);
        $this->assertSame(10, StudentPage::query()->count());
    }

    public function test_invalid_page_ranges_and_dates_are_refused(): void
    {
        $teacher = Teacher::factory()->create();

        $invalid = [
            'end before start' => ['from_page' => 10, 'to_page' => 9],
            'page zero' => ['from_page' => 0, 'to_page' => 3],
            'beyond the mushaf' => ['from_page' => 600, 'to_page' => 605],
            'not a number' => ['from_page' => 'x', 'to_page' => 3],
            'future date' => ['from_page' => 1, 'to_page' => 2, 'recited_on' => '2026-10-08'],
            'no teacher' => ['from_page' => 1, 'to_page' => 2, 'teacher_id' => null],
        ];

        foreach ($invalid as $case => $overrides) {
            try {
                app(RecordRecitation::class)->handle($overrides + [
                    'enrollment_id' => $this->enrollment->getKey(),
                    'teacher_id' => $teacher->getKey(),
                    'recited_on' => '2026-10-07',
                ]);
                $this->fail("Input was accepted: {$case}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, Recitation::query()->count());
    }

    public function test_a_recitation_must_fall_inside_the_course_and_the_enrollment(): void
    {
        $course = Course::factory()->create(['start_date' => '2026-09-01']);
        $enrollment = $this->enroll(course: $course, enrolledOn: '2026-09-10');

        $this->assertViolates('date.before_course_start', fn () => $this->recite($enrollment, 1, 2, '2026-08-25'));
        $this->assertViolates('date.before_enrollment', fn () => $this->recite($enrollment, 1, 2, '2026-09-05'));

        $this->assertSame(0, Recitation::query()->count());
    }

    public function test_an_inactive_teacher_cannot_be_chosen_for_a_new_recitation(): void
    {
        $inactive = Teacher::factory()->inactive()->create();

        $this->assertViolates('teacher.inactive', fn () => $this->recite($this->enrollment, 1, 2, null, $inactive));
    }

    public function test_a_recitation_keeps_its_teacher_after_the_teacher_is_deactivated(): void
    {
        $teacher = Teacher::factory()->create();
        $recitation = $this->recite($this->enrollment, 1, 2, '2026-10-05', $teacher);
        $teacher->update(['is_active' => false]);

        $updated = app(UpdateRecitation::class)->handle($recitation, [
            'teacher_id' => $teacher->getKey(),
            'recited_on' => '2026-10-05',
            'from_page' => 1,
            'to_page' => 3,
        ]);

        $this->assertSame(3, $updated->new_pages_count);
    }

    public function test_the_whole_mushaf_can_be_recorded_in_one_range(): void
    {
        $recitation = $this->recite($this->enrollment, 1, 604);

        $this->assertSame(604, $recitation->new_pages_count);
        $this->assertSame(604, $this->student->fresh()->recited_pages_count);
        $this->assertSame(604, StudentPage::query()->count());
        $this->assertSame(1208, $this->pointsOf($recitation));
    }
}
