<?php

namespace Tests\Feature;

use App\Actions\Enrollments\TransferStudent;
use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Models\Circle;
use App\Models\CircleTransfer;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\Recitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    private Course $course;

    private Circle $circleA;

    private Circle $circleB;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->course = Course::factory()->create();
        $this->circleA = Circle::factory()->create(['course_id' => $this->course->getKey()]);
        $this->circleB = Circle::factory()->create(['course_id' => $this->course->getKey()]);
        $this->enrollment = $this->enroll(course: $this->course, circle: $this->circleA);
    }

    /** @param array<string, mixed> $overrides */
    private function transfer(array $overrides = []): CircleTransfer
    {
        return app(TransferStudent::class)->handle($this->enrollment, $overrides + [
            'to_circle_id' => $this->circleB->getKey(),
            'transferred_on' => now()->toDateString(),
        ]);
    }

    public function test_a_transfer_moves_the_student_and_is_logged(): void
    {
        $transfer = $this->transfer(['transferred_on' => now()->subDays(3)->toDateString(), 'notes' => 'طلب ولي الأمر']);

        $this->assertSame($this->circleB->getKey(), $this->enrollment->fresh()->circle_id);
        $this->assertSame($this->circleA->getKey(), $transfer->from_circle_id);
        $this->assertSame($this->circleB->getKey(), $transfer->to_circle_id);
        $this->assertSame(now()->subDays(3)->toDateString(), $transfer->transferred_on->toDateString());
        $this->assertSame('طلب ولي الأمر', $transfer->notes);
    }

    public function test_everything_the_student_did_follows_them_to_the_new_circle(): void
    {
        // 10 pages while in circle A.
        $this->recite($this->enrollment, 1, 10, now()->subDays(10)->toDateString());

        $this->transfer(['transferred_on' => now()->subDays(5)->toDateString()]);

        // 5 more pages while in circle B.
        $this->recite($this->enrollment, 11, 15, now()->subDay()->toDateString());

        $pagesOf = fn (Circle $circle): int => (int) Recitation::query()
            ->whereIn('enrollment_id', $circle->enrollments()->select('id'))
            ->sum('new_pages_count');

        $pointsOf = fn (Circle $circle): int => (int) PointTransaction::query()
            ->whereIn('enrollment_id', $circle->enrollments()->select('id'))
            ->sum('points');

        $this->assertSame(0, $pagesOf($this->circleA));
        $this->assertSame(15, $pagesOf($this->circleB));
        $this->assertSame(0, $pointsOf($this->circleA));
        $this->assertSame(15 * $this->course->points_per_page, $pointsOf($this->circleB));
    }

    public function test_the_transfer_date_is_required_and_has_no_default(): void
    {
        $this->expectException(ValidationException::class);

        app(TransferStudent::class)->handle($this->enrollment, ['to_circle_id' => $this->circleB->getKey()]);
    }

    public function test_the_transfer_date_cannot_be_in_the_future(): void
    {
        $this->expectException(ValidationException::class);

        $this->transfer(['transferred_on' => now()->addDay()->toDateString()]);
    }

    public function test_a_student_cannot_be_transferred_to_their_own_circle(): void
    {
        $this->assertViolates('transfer.same_circle', fn () => $this->transfer(['to_circle_id' => $this->circleA->getKey()]));

        $this->assertSame(0, CircleTransfer::query()->count());
    }

    public function test_a_student_cannot_be_transferred_to_a_circle_of_another_course(): void
    {
        $otherCourseCircle = Circle::factory()->create();

        $this->assertViolates('circle.other_course', fn () => $this->transfer(['to_circle_id' => $otherCourseCircle->getKey()]));
    }

    public function test_a_student_cannot_be_transferred_to_an_inactive_circle(): void
    {
        $inactive = Circle::factory()->inactive()->create(['course_id' => $this->course->getKey()]);

        $this->assertViolates('circle.inactive', fn () => $this->transfer(['to_circle_id' => $inactive->getKey()]));
    }

    public function test_a_transfer_cannot_be_dated_before_the_enrollment(): void
    {
        $dayBeforeEnrollment = $this->enrollment->enrolled_on->subDay()->toDateString();

        $this->assertViolates('transfer.before_enrollment', fn () => $this->transfer(['transferred_on' => $dayBeforeEnrollment]));
    }

    public function test_transfers_must_be_in_chronological_order(): void
    {
        $this->transfer(['transferred_on' => now()->subDays(2)->toDateString()]);

        $this->assertViolates('transfer.before_previous', fn () => $this->transfer([
            'to_circle_id' => $this->circleA->getKey(),
            'transferred_on' => now()->subDays(5)->toDateString(),
        ]));
    }

    public function test_a_student_who_left_the_course_cannot_be_transferred(): void
    {
        app(UpdateEnrollmentDates::class)->handle($this->enrollment, [
            'enrolled_on' => $this->enrollment->enrolled_on->toDateString(),
            'left_on' => now()->toDateString(),
        ]);

        $this->assertViolates('enrollment.left', fn () => $this->transfer());
    }

    public function test_a_student_can_move_back_and_forth_and_every_move_is_logged(): void
    {
        $this->transfer(['transferred_on' => now()->subDays(4)->toDateString()]);
        $this->transfer(['to_circle_id' => $this->circleA->getKey(), 'transferred_on' => now()->subDays(2)->toDateString()]);

        $this->assertSame($this->circleA->getKey(), $this->enrollment->fresh()->circle_id);
        $this->assertSame(2, $this->enrollment->transfers()->count());
        $this->assertSame(1, $this->circleA->incomingTransfers()->count());
        $this->assertSame(1, $this->circleA->outgoingTransfers()->count());
    }
}
