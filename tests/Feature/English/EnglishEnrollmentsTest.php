<?php

namespace Tests\Feature\English;

use App\Actions\English\Enrollments\ChangeEnglishHall;
use App\Actions\English\Enrollments\ChangeEnglishPrice;
use App\Actions\English\Enrollments\DeleteEnglishEnrollment;
use App\Actions\English\Enrollments\RegisterEnglishStudent;
use App\Actions\English\Enrollments\UpdateEnglishEnrollmentDates;
use App\Enums\English\PaymentState;
use App\Enums\SessionStatus;
use App\Models\English\EnglishAttendance;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishHall;
use App\Models\English\EnglishSession;
use App\Models\Student;
use App\Services\English\EnglishBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\BuildsEnglish;
use Tests\TestCase;

class EnglishEnrollmentsTest extends TestCase
{
    use BuildsDomain;
    use BuildsEnglish;
    use RefreshDatabase;

    public function test_a_student_is_enrolled_in_the_hall_picked_at_the_course_price_with_a_card(): void
    {
        $course = EnglishCourse::factory()->create(['price' => 175000]);
        $hall = EnglishHall::factory()->create(['english_course_id' => $course->getKey()]);

        $enrollment = $this->enrollInEnglish(course: $course, hall: $hall);

        $this->assertSame($hall->getKey(), $enrollment->english_hall_id);
        $this->assertMatchesRegularExpression('/^[0-9]{10}$/', $enrollment->card_code);
        $this->assertNull($enrollment->price);
        $this->assertSame(175000, $enrollment->duePrice());
    }

    public function test_a_student_may_be_given_a_price_of_their_own_with_its_reason(): void
    {
        $course = EnglishCourse::factory()->create(['price' => 200000]);

        $enrollment = $this->enrollInEnglish(course: $course, price: 150000, priceNote: 'خصم أخوة');

        $this->assertSame(150000, $enrollment->duePrice());
        $this->assertSame('خصم أخوة', $enrollment->price_note);

        // A price without its reason is refused.
        $this->assertThrows(fn () => $this->enrollInEnglish(course: $course, price: 100000, priceNote: ''), ValidationException::class);
        // A free place is a price too, with its reason.
        $this->assertThrows(fn () => $this->enrollInEnglish(course: $course, price: 0, priceNote: ''), ValidationException::class);
        $this->assertSame(0, $this->enrollInEnglish(course: $course, price: 0, priceNote: 'منحة')->duePrice());
    }

    public function test_enrollment_rules(): void
    {
        $student = Student::factory()->english()->create();
        $course = EnglishCourse::factory()->create();
        $this->enrollInEnglish($student, $course);

        $this->assertViolates('english_enrollment.duplicate', fn () => $this->enrollInEnglish($student, $course));
        $this->assertViolates('english_course.finished', fn () => $this->enrollInEnglish(course: EnglishCourse::factory()->finished()->create()));
        $this->assertViolates('english_hall.other_course', fn () => $this->enrollInEnglish(course: $course, hall: EnglishHall::factory()->create()));
        $this->assertViolates('english_hall.inactive', fn () => $this->enrollInEnglish(course: $course, hall: EnglishHall::factory()->inactive()->create(['english_course_id' => $course->getKey()])));
        $this->assertThrows(fn () => $this->enrollInEnglish(course: $course, enrolledOn: now()->addDay()->toDateString()), ValidationException::class);

        $this->assertSame(1, EnglishEnrollment::query()->count());
    }

    public function test_a_new_student_is_registered_and_enrolled_together_or_not_at_all(): void
    {
        $course = EnglishCourse::factory()->create();
        $hall = EnglishHall::factory()->create(['english_course_id' => $course->getKey()]);

        $enrollment = app(RegisterEnglishStudent::class)->handle([
            'full_name' => 'لين حداد',
            'phone' => '0933000111',
            'english_level_id' => $course->english_level_id,
            'english_course_id' => $course->getKey(),
            'english_hall_id' => $hall->getKey(),
            'enrolled_on' => now()->toDateString(),
        ]);

        $student = $enrollment->student;
        $this->assertSame('لين حداد', $student->full_name);
        $this->assertTrue($student->in_english);
        $this->assertFalse($student->in_mosque);
        $this->assertSame($course->english_level_id, $student->english_level_id);

        // The hall is missing: the student is not created either.
        $this->assertThrows(fn () => app(RegisterEnglishStudent::class)->handle([
            'full_name' => 'طالب لن يُحفظ',
            'english_course_id' => $course->getKey(),
            'enrolled_on' => now()->toDateString(),
        ]), ValidationException::class);
        // The course is finished: the student is not created either.
        $finished = EnglishCourse::factory()->finished()->create();
        $this->assertViolates('english_course.finished', fn () => app(RegisterEnglishStudent::class)->handle([
            'full_name' => 'طالب لن يُحفظ',
            'english_course_id' => $finished->getKey(),
            'english_hall_id' => EnglishHall::factory()->create(['english_course_id' => $finished->getKey()])->getKey(),
            'enrolled_on' => now()->toDateString(),
        ]));

        $this->assertSame(0, Student::query()->where('full_name', 'طالب لن يُحفظ')->count());
    }

    public function test_the_price_of_a_student_changes_but_never_below_what_they_paid(): void
    {
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->create(['price' => 200000]));
        $this->alreadyPaid($enrollment, 120000);

        app(ChangeEnglishPrice::class)->handle($enrollment, ['price' => '150000', 'price_note' => 'خصم']);
        $this->assertSame(150000, $enrollment->fresh()->duePrice());

        $this->assertViolates('english_enrollment.price_below_paid', fn () => app(ChangeEnglishPrice::class)->handle($enrollment, ['price' => '100000', 'price_note' => 'خصم أكبر']));

        // Back to the course's price: the reason goes with the special price.
        app(ChangeEnglishPrice::class)->handle($enrollment, ['price' => null, 'price_note' => 'لا يُحفظ']);
        $enrollment->refresh();
        $this->assertNull($enrollment->price);
        $this->assertNull($enrollment->price_note);
        $this->assertSame(200000, $enrollment->duePrice());
    }

    public function test_balances_say_what_is_due_paid_and_left(): void
    {
        $course = EnglishCourse::factory()->create(['price' => 100000]);
        $unpaid = $this->enrollInEnglish(course: $course);
        $partial = $this->enrollInEnglish(course: $course);
        $paid = $this->enrollInEnglish(course: $course, price: 60000, priceNote: 'خصم');
        $free = $this->enrollInEnglish(course: $course, price: 0, priceNote: 'منحة');
        $this->alreadyPaid($partial, 30000);
        $this->alreadyPaid($partial, 20000);
        $this->alreadyPaid($paid, 60000);

        $balances = app(EnglishBalances::class)->ofMany(EnglishEnrollment::query()->with('course')->get());

        $this->assertSame([100000, 0, 100000, PaymentState::Unpaid], [$balances[$unpaid->getKey()]->due, $balances[$unpaid->getKey()]->paid, $balances[$unpaid->getKey()]->remaining(), $balances[$unpaid->getKey()]->state()]);
        $this->assertSame([100000, 50000, 50000, PaymentState::Partial], [$balances[$partial->getKey()]->due, $balances[$partial->getKey()]->paid, $balances[$partial->getKey()]->remaining(), $balances[$partial->getKey()]->state()]);
        $this->assertSame(PaymentState::Paid, $balances[$paid->getKey()]->state());
        $this->assertSame(PaymentState::Paid, $balances[$free->getKey()]->state());
        $this->assertSame(50000, app(EnglishBalances::class)->of($partial)->paid);
    }

    public function test_a_student_moves_to_another_active_hall_of_the_same_course(): void
    {
        $enrollment = $this->enrollInEnglish();
        $other = EnglishHall::factory()->create(['english_course_id' => $enrollment->english_course_id]);

        app(ChangeEnglishHall::class)->handle($enrollment, ['english_hall_id' => $other->getKey()]);
        $this->assertSame($other->getKey(), $enrollment->fresh()->english_hall_id);

        $this->assertViolates('english_hall.same', fn () => app(ChangeEnglishHall::class)->handle($enrollment->fresh(), ['english_hall_id' => $other->getKey()]));
        $this->assertViolates('english_hall.other_course', fn () => app(ChangeEnglishHall::class)->handle($enrollment->fresh(), ['english_hall_id' => EnglishHall::factory()->create()->getKey()]));
        $this->assertViolates('english_hall.inactive', fn () => app(ChangeEnglishHall::class)->handle($enrollment->fresh(), ['english_hall_id' => EnglishHall::factory()->inactive()->create(['english_course_id' => $enrollment->english_course_id])->getKey()]));
    }

    public function test_the_dates_keep_the_attendance_inside_the_enrollment(): void
    {
        $enrollment = $this->enrollInEnglish(enrolledOn: now()->subDays(20)->toDateString());
        $session = EnglishSession::factory()->create([
            'english_course_id' => $enrollment->english_course_id,
            'session_date' => now()->subDays(10)->toDateString(),
            'status' => SessionStatus::Held,
        ]);
        EnglishAttendance::query()->create(['english_session_id' => $session->getKey(), 'english_enrollment_id' => $enrollment->getKey(), 'method' => 'manual']);

        $this->assertViolates('english_enrollment.records_before_enrolled_on', fn () => app(UpdateEnglishEnrollmentDates::class)->handle($enrollment, ['enrolled_on' => now()->subDays(5)->toDateString()]));
        $this->assertViolates('english_enrollment.records_after_left_on', fn () => app(UpdateEnglishEnrollmentDates::class)->handle($enrollment, ['enrolled_on' => now()->subDays(20)->toDateString(), 'left_on' => now()->subDays(15)->toDateString()]));

        app(UpdateEnglishEnrollmentDates::class)->handle($enrollment, ['enrolled_on' => now()->subDays(20)->toDateString(), 'left_on' => now()->subDays(2)->toDateString()]);
        $this->assertTrue($enrollment->fresh()->hasLeft());
    }

    public function test_only_an_enrollment_without_records_is_deleted(): void
    {
        $paid = $this->enrollInEnglish();
        $this->alreadyPaid($paid, 1000);
        $empty = $this->enrollInEnglish();

        $this->assertViolates('english_enrollment.has_records', fn () => app(DeleteEnglishEnrollment::class)->handle($paid));

        app(DeleteEnglishEnrollment::class)->handle($empty);
        $this->assertNull($empty->fresh());
        // The student stays on the English list.
        $this->assertTrue($empty->student->fresh()->in_english);
    }
}
