<?php

namespace Tests\Feature;

use App\Actions\Attendance\RecordManualAttendance;
use App\Actions\Points\AwardManualPoints;
use App\Actions\Points\DeleteManualPoints;
use App\Actions\Points\UpdateManualPoints;
use App\Enums\PointSource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class PointsTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');

        $this->enrollment = $this->enroll(course: Course::factory()->create([
            'points_per_page' => 2,
            'points_per_attendance' => 1,
        ]));
    }

    /** @param array<string, mixed> $overrides */
    private function award(array $overrides = []): PointTransaction
    {
        return app(AwardManualPoints::class)->handle($overrides + [
            'enrollment_id' => $this->enrollment->getKey(),
            'points' => 10,
            'reason' => 'فوز في مسابقة',
            'occurred_on' => '2026-10-06',
        ]);
    }

    public function test_the_admin_can_award_points_with_a_reason(): void
    {
        $transaction = $this->award();

        $this->assertSame(10, $transaction->points);
        $this->assertSame(PointSource::Manual, $transaction->source);
        $this->assertSame('فوز في مسابقة', $transaction->reason);
        $this->assertSame('2026-10-06', $transaction->occurred_on->toDateString());
        $this->assertNull($transaction->attendance_id);
        $this->assertNull($transaction->recitation_id);
    }

    public function test_negative_points_are_a_penalty(): void
    {
        $this->award(['points' => 10]);
        $this->award(['points' => -4, 'reason' => 'تأخر متكرر']);

        $this->assertSame(6, (int) $this->enrollment->pointTransactions()->sum('points'));
    }

    public function test_points_need_a_non_zero_amount_a_reason_and_a_valid_date(): void
    {
        $invalid = [
            'zero points' => ['points' => 0],
            'no reason' => ['reason' => ''],
            'not a number' => ['points' => 'كثير'],
            'future date' => ['occurred_on' => '2026-10-08'],
            'no student' => ['enrollment_id' => null],
        ];

        foreach ($invalid as $case => $overrides) {
            try {
                $this->award($overrides);
                $this->fail("Input was accepted: {$case}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, PointTransaction::query()->count());
    }

    public function test_points_must_fall_inside_the_enrollment(): void
    {
        $dayBefore = $this->enrollment->enrolled_on->subDay()->toDateString();

        $this->assertViolates('date.before_course_start', fn () => $this->award(['occurred_on' => $dayBefore]));
    }

    public function test_the_total_is_the_sum_of_attendance_recitation_and_manual_points(): void
    {
        app(RecordManualAttendance::class)->handle([
            'enrollment_id' => $this->enrollment->getKey(),
            'attended_on' => '2026-10-05',
        ]);
        $this->recite($this->enrollment, 1, 3, '2026-10-05');
        $this->award(['points' => 5]);
        $this->award(['points' => -2, 'reason' => 'عقوبة']);

        // 1 (attendance) + 6 (three new pages) + 5 - 2
        $this->assertSame(10, (int) $this->enrollment->pointTransactions()->sum('points'));

        $bySource = PointTransaction::query()
            ->where('enrollment_id', $this->enrollment->getKey())
            ->toBase()
            ->selectRaw('source, SUM(points) AS total')
            ->groupBy('source')
            ->pluck('total', 'source')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $this->assertEquals(['attendance' => 1, 'recitation' => 6, 'manual' => 3], $bySource);
    }

    public function test_points_are_kept_per_course(): void
    {
        $student = Student::factory()->create();
        $first = $this->enroll($student, Course::factory()->create());
        $second = $this->enroll($student, Course::factory()->create());

        app(AwardManualPoints::class)->handle(['enrollment_id' => $first->getKey(), 'points' => 7, 'reason' => 'سلوك', 'occurred_on' => '2026-10-06']);
        app(AwardManualPoints::class)->handle(['enrollment_id' => $second->getKey(), 'points' => 3, 'reason' => 'سلوك', 'occurred_on' => '2026-10-06']);

        $this->assertSame(7, (int) $first->pointTransactions()->sum('points'));
        $this->assertSame(3, (int) $second->pointTransactions()->sum('points'));
        $this->assertSame(10, (int) $student->pointTransactions()->sum('points'));
    }

    public function test_manual_points_can_be_corrected_and_deleted(): void
    {
        $transaction = $this->award();

        app(UpdateManualPoints::class)->handle($transaction, [
            'points' => -3,
            'reason' => 'تصحيح',
            'occurred_on' => '2026-10-05',
        ]);

        $transaction->refresh();
        $this->assertSame(-3, $transaction->points);
        $this->assertSame('تصحيح', $transaction->reason);
        $this->assertSame('2026-10-05', $transaction->occurred_on->toDateString());

        app(DeleteManualPoints::class)->handle($transaction);
        $this->assertModelMissing($transaction);
    }

    public function test_automatic_points_cannot_be_edited_or_deleted_by_hand(): void
    {
        $this->recite($this->enrollment, 1, 2);
        $automatic = PointTransaction::query()->sole();

        $this->assertViolates('points.not_manual', fn () => app(UpdateManualPoints::class)->handle($automatic, [
            'points' => 100,
            'reason' => 'تعديل',
            'occurred_on' => '2026-10-07',
        ]));
        $this->assertViolates('points.not_manual', fn () => app(DeleteManualPoints::class)->handle($automatic));

        $this->assertSame(4, $automatic->fresh()->points);
    }
}
