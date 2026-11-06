<?php

namespace Tests\Feature\English;

use App\Actions\English\Courses\DeleteEnglishCourse;
use App\Actions\English\Courses\SaveEnglishCourse;
use App\Actions\English\Halls\DeleteEnglishHall;
use App\Actions\English\Halls\SaveEnglishHall;
use App\Actions\English\Teachers\DeleteEnglishTeacher;
use App\Actions\English\Teachers\SaveEnglishTeacher;
use App\Enums\CourseStatus;
use App\Enums\Weekday;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishHall;
use App\Models\English\EnglishLevel;
use App\Models\English\EnglishSession;
use App\Models\English\EnglishTeacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\BuildsEnglish;
use Tests\TestCase;

class EnglishCoursesTest extends TestCase
{
    use BuildsDomain;
    use BuildsEnglish;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function courseInput(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'A1 صباحي',
            'english_level_id' => EnglishLevel::query()->where('name', 'A1')->value('id'),
            'price' => '٢٠٠٠٠٠',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'schedules' => [
                ['weekday' => Weekday::Sunday->value, 'start_time' => '10:00', 'end_time' => '12:00'],
                ['weekday' => Weekday::Tuesday->value, 'start_time' => '10:00', 'end_time' => '12:00'],
            ],
        ];
    }

    public function test_the_six_levels_are_there_from_the_start(): void
    {
        $this->assertSame(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'], EnglishLevel::query()->orderBy('sort_order')->pluck('name')->all());
    }

    public function test_a_course_is_created_with_its_level_price_and_class_days(): void
    {
        $course = app(SaveEnglishCourse::class)->handle($this->courseInput());

        $this->assertSame('A1 صباحي', $course->name);
        $this->assertSame(200000, $course->price);
        $this->assertSame('A1', $course->level->name);
        $this->assertSame(CourseStatus::Active, $course->status);
        $this->assertEqualsCanonicalizing([Weekday::Sunday, Weekday::Tuesday], $course->schedules->pluck('weekday')->all());
    }

    public function test_a_course_needs_a_level_a_whole_price_and_a_class_day(): void
    {
        foreach ([
            ['english_level_id' => null],
            ['price' => '150,000'],
            ['price' => '-5'],
            ['schedules' => []],
        ] as $wrong) {
            try {
                app(SaveEnglishCourse::class)->handle($this->courseInput($wrong));
                $this->fail('Saved a course with '.json_encode($wrong));
            } catch (ValidationException) {
                // expected
            }
        }

        $this->assertSame(0, EnglishCourse::query()->count());
    }

    public function test_the_price_never_goes_below_what_a_student_paid(): void
    {
        $course = EnglishCourse::factory()->create(['price' => 200000, 'start_date' => '2026-09-01']);
        $this->alreadyPaid($this->enrollInEnglish(course: $course), 150000);
        // A student with a price of their own does not count.
        $this->alreadyPaid($this->enrollInEnglish(course: $course, price: 300000, priceNote: 'دروس إضافية'), 250000);

        $this->assertViolates('english_course.price_below_paid', fn () => app(SaveEnglishCourse::class)->handle($this->courseInput(['price' => '100000']), $course));
        $this->assertSame(200000, $course->fresh()->price);

        app(SaveEnglishCourse::class)->handle($this->courseInput(['price' => '150000']), $course);
        $this->assertSame(150000, $course->fresh()->price);
    }

    public function test_the_dates_keep_the_class_days_inside_the_course(): void
    {
        $course = EnglishCourse::factory()->create(['start_date' => '2026-09-01']);
        EnglishSession::factory()->create(['english_course_id' => $course->getKey(), 'session_date' => '2026-09-10']);

        $this->assertViolates('english_course.records_before_start', fn () => app(SaveEnglishCourse::class)->handle($this->courseInput(['start_date' => '2026-09-15']), $course));
        $this->assertViolates('english_course.records_after_end', fn () => app(SaveEnglishCourse::class)->handle($this->courseInput(['end_date' => '2026-09-05']), $course));
    }

    public function test_only_an_unused_course_is_deleted_with_its_halls(): void
    {
        $unused = EnglishCourse::factory()->create();
        EnglishHall::factory()->create(['english_course_id' => $unused->getKey()]);
        $used = $this->enrollInEnglish()->course;

        app(DeleteEnglishCourse::class)->handle($unused);
        $this->assertNull($unused->fresh());
        $this->assertSame(0, EnglishHall::query()->where('english_course_id', $unused->getKey())->count());

        $this->assertViolates('english_course.has_records', fn () => app(DeleteEnglishCourse::class)->handle($used));
        $this->assertNotNull($used->fresh());
    }

    public function test_halls_have_a_teacher_and_an_assistant_from_the_english_teachers(): void
    {
        $course = EnglishCourse::factory()->create();
        $teacher = app(SaveEnglishTeacher::class)->handle(['full_name' => 'Ms. Rana', 'phone' => '٠٩٣٣١١١٢٢٢']);
        $assistant = EnglishTeacher::factory()->create();

        $hall = app(SaveEnglishHall::class)->handle($course, [
            'name' => 'القاعة 1',
            'teacher_id' => $teacher->getKey(),
            'assistant_id' => $assistant->getKey(),
        ]);

        $this->assertSame('0933111222', $teacher->phone);
        $this->assertSame($teacher->getKey(), $hall->teacher_id);
        $this->assertSame($assistant->getKey(), $hall->assistant_id);
        $this->assertSame($hall->getKey(), $teacher->halls()->value('id'));
    }

    public function test_hall_rules(): void
    {
        $course = EnglishCourse::factory()->create();
        $teacher = EnglishTeacher::factory()->create();
        app(SaveEnglishHall::class)->handle($course, ['name' => 'القاعة 1']);

        // The same name in the same course.
        $this->assertThrows(fn () => app(SaveEnglishHall::class)->handle($course, ['name' => 'القاعة 1']), ValidationException::class);
        // The same name in another course is fine.
        app(SaveEnglishHall::class)->handle(EnglishCourse::factory()->create(), ['name' => 'القاعة 1']);
        // The assistant is not the teacher.
        $this->assertThrows(fn () => app(SaveEnglishHall::class)->handle($course, ['name' => 'القاعة 2', 'teacher_id' => $teacher->getKey(), 'assistant_id' => $teacher->getKey()]), ValidationException::class);
        // A teacher who is not on the list.
        $this->assertThrows(fn () => app(SaveEnglishHall::class)->handle($course, ['name' => 'القاعة 3', 'teacher_id' => $teacher->getKey() + 1000]), ValidationException::class);
        // A newly chosen teacher is active.
        $this->assertViolates('english_teacher.inactive', fn () => app(SaveEnglishHall::class)->handle($course, ['name' => 'القاعة 4', 'teacher_id' => EnglishTeacher::factory()->inactive()->create()->getKey()]));
    }

    public function test_a_teacher_who_stays_on_a_hall_may_be_deactivated(): void
    {
        $course = EnglishCourse::factory()->create();
        $teacher = EnglishTeacher::factory()->create();
        $hall = app(SaveEnglishHall::class)->handle($course, ['name' => 'القاعة 1', 'teacher_id' => $teacher->getKey()]);
        $teacher->update(['is_active' => false]);

        app(SaveEnglishHall::class)->handle($course, ['name' => 'القاعة الأولى', 'teacher_id' => $teacher->getKey()], $hall);

        $this->assertSame('القاعة الأولى', $hall->fresh()->name);
    }

    public function test_only_an_empty_hall_and_an_unassigned_teacher_are_deleted(): void
    {
        $teacher = EnglishTeacher::factory()->create();
        $enrollment = $this->enrollInEnglish();
        $enrollment->hall->update(['teacher_id' => $teacher->getKey()]);
        $empty = EnglishHall::factory()->create(['english_course_id' => $enrollment->english_course_id]);

        $this->assertViolates('english_hall.has_students', fn () => app(DeleteEnglishHall::class)->handle($enrollment->hall));
        $this->assertViolates('english_teacher.in_use', fn () => app(DeleteEnglishTeacher::class)->handle($teacher));

        app(DeleteEnglishHall::class)->handle($empty);
        $this->assertNull($empty->fresh());

        $free = EnglishTeacher::factory()->create();
        app(DeleteEnglishTeacher::class)->handle($free);
        $this->assertNull($free->fresh());
    }
}
