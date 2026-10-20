<?php

namespace Tests\Feature;

use App\Actions\Students\ArchiveStudent;
use App\Actions\Students\DeleteStudent;
use App\Actions\Students\RestoreStudent;
use App\Actions\Students\SaveStudent;
use App\Models\Student;
use App\Models\TajweedLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    public function test_a_student_can_be_registered_with_only_a_name(): void
    {
        $student = app(SaveStudent::class)->handle(['full_name' => '  أحمد محمد  ']);

        $this->assertSame('أحمد محمد', $student->full_name);
        $this->assertFalse($student->is_profile_complete);
        $this->assertSame(0, $student->fresh()->recited_pages_count);
    }

    public function test_the_profile_becomes_complete_when_every_field_is_filled(): void
    {
        $student = app(SaveStudent::class)->handle(['full_name' => 'أحمد محمد']);

        $student = app(SaveStudent::class)->handle([
            'full_name' => 'أحمد محمد',
            'birth_date' => '2012-03-15',
            'phone' => '0999111222',
            'guardian_phone' => '0999333444',
            'address' => 'دمشق - الميدان',
            'tajweed_level_id' => TajweedLevel::factory()->create()->getKey(),
            'prior_memorized_pages' => 0,
        ], $student);

        $this->assertTrue($student->fresh()->is_profile_complete);
    }

    public function test_clearing_a_field_makes_the_profile_incomplete_again(): void
    {
        $student = Student::factory()->complete()->create();

        app(SaveStudent::class)->handle([
            'full_name' => $student->full_name,
            'birth_date' => $student->birth_date->toDateString(),
            'phone' => $student->phone,
            'guardian_phone' => null,
            'address' => $student->address,
            'tajweed_level_id' => $student->tajweed_level_id,
            'prior_memorized_pages' => $student->prior_memorized_pages,
        ], $student);

        $this->assertFalse($student->fresh()->is_profile_complete);
    }

    public function test_the_fathers_name_is_optional_and_never_needed_for_a_complete_profile(): void
    {
        $student = app(SaveStudent::class)->handle(['full_name' => 'كرم صواف', 'father_name' => '  محمد  ']);
        $this->assertSame('محمد', $student->fresh()->father_name);

        // Saving without it in the input keeps it; an empty one clears it.
        app(SaveStudent::class)->handle(['full_name' => 'كرم صواف'], $student);
        $this->assertSame('محمد', $student->fresh()->father_name);
        app(SaveStudent::class)->handle(['full_name' => 'كرم صواف', 'father_name' => ''], $student);
        $this->assertNull($student->fresh()->father_name);

        // Every field of a complete profile, and no father's name: complete.
        $complete = Student::factory()->complete()->create();
        $this->assertNull($complete->father_name);
        $saved = app(SaveStudent::class)->handle([
            'full_name' => $complete->full_name,
            'birth_date' => $complete->birth_date->toDateString(),
            'phone' => $complete->phone,
            'guardian_phone' => $complete->guardian_phone,
            'address' => $complete->address,
            'tajweed_level_id' => $complete->tajweed_level_id,
            'prior_memorized_pages' => $complete->prior_memorized_pages,
        ], $complete);
        $this->assertTrue($saved->is_profile_complete);

        try {
            app(SaveStudent::class)->handle(['full_name' => 'كرم صواف', 'father_name' => 'م']);
            $this->fail('A one-letter father\'s name was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['father_name'], array_keys($exception->errors()));
        }
    }

    public function test_arabic_digits_in_phone_numbers_are_stored_as_latin_digits(): void
    {
        $student = app(SaveStudent::class)->handle([
            'full_name' => 'أحمد محمد',
            'phone' => '٠٩٩٩١١١٢٢٢',
            'prior_memorized_pages' => '٤٠',
        ]);

        $this->assertSame('0999111222', $student->phone);
        $this->assertSame(40, $student->fresh()->prior_memorized_pages);
    }

    public function test_invalid_input_is_refused(): void
    {
        $invalid = [
            'missing name' => ['full_name' => ''],
            'letters in phone' => ['full_name' => 'أحمد', 'phone' => 'abc12345'],
            'birth date in the future' => ['full_name' => 'أحمد', 'birth_date' => now()->addDay()->toDateString()],
            'more pages than the mushaf' => ['full_name' => 'أحمد', 'prior_memorized_pages' => 605],
            'unknown tajweed level' => ['full_name' => 'أحمد', 'tajweed_level_id' => 999999],
        ];

        foreach ($invalid as $case => $input) {
            try {
                app(SaveStudent::class)->handle($input);
                $this->fail("Input was accepted: {$case}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, Student::query()->count());
    }

    public function test_memorized_total_adds_prior_pages_and_recited_pages(): void
    {
        $student = Student::factory()->create(['prior_memorized_pages' => 40]);
        $enrollment = $this->enroll($student);

        $this->recite($enrollment, 1, 5);

        $this->assertSame(45, $student->fresh()->memorized_pages_total);
    }

    public function test_a_student_can_be_archived_and_restored(): void
    {
        $student = Student::factory()->create();

        app(ArchiveStudent::class)->handle($student);
        $this->assertTrue($student->fresh()->isArchived());
        $this->assertSame(0, Student::query()->active()->count());

        app(RestoreStudent::class)->handle($student);
        $this->assertFalse($student->fresh()->isArchived());
    }

    public function test_a_student_with_no_history_can_be_deleted(): void
    {
        $student = Student::factory()->create();

        app(DeleteStudent::class)->handle($student);

        $this->assertModelMissing($student);
    }

    public function test_a_student_with_an_enrollment_cannot_be_deleted(): void
    {
        $student = Student::factory()->create();
        $this->enroll($student);

        $this->assertViolates('student.has_records', fn () => app(DeleteStudent::class)->handle($student));

        $this->assertModelExists($student);
    }
}
