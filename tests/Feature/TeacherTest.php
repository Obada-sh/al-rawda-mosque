<?php

namespace Tests\Feature;

use App\Actions\TajweedLevels\DeleteTajweedLevel;
use App\Actions\TajweedLevels\SaveTajweedLevel;
use App\Actions\Teachers\DeleteTeacher;
use App\Actions\Teachers\SaveTeacher;
use App\Models\Student;
use App\Models\TajweedLevel;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class TeacherTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    public function test_a_teacher_is_added_to_the_single_teachers_list(): void
    {
        $teacher = app(SaveTeacher::class)->handle(['full_name' => 'الأستاذ خالد', 'phone' => '0999555666']);

        $this->assertSame('الأستاذ خالد', $teacher->full_name);
        $this->assertTrue($teacher->fresh()->is_active);
        $this->assertSame(1, Teacher::query()->active()->count());
    }

    public function test_a_teacher_needs_a_name_and_a_valid_phone(): void
    {
        foreach ([['full_name' => ''], ['full_name' => 'خالد', 'phone' => 'هاتف']] as $input) {
            try {
                app(SaveTeacher::class)->handle($input);
                $this->fail('Invalid teacher input was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_teacher_can_be_deactivated(): void
    {
        $teacher = Teacher::factory()->create();

        app(SaveTeacher::class)->handle(['full_name' => $teacher->full_name, 'is_active' => false], $teacher);

        $this->assertSame(0, Teacher::query()->active()->count());
    }

    public function test_a_teacher_who_heard_a_recitation_cannot_be_deleted(): void
    {
        $teacher = Teacher::factory()->create();
        $this->recite($this->enroll(), 1, 2, null, $teacher);

        $this->assertViolates('teacher.has_records', fn () => app(DeleteTeacher::class)->handle($teacher));

        $unused = Teacher::factory()->create();
        app(DeleteTeacher::class)->handle($unused);
        $this->assertModelMissing($unused);
    }

    public function test_tajweed_levels_can_be_renamed_but_names_stay_unique(): void
    {
        $level = app(SaveTajweedLevel::class)->handle(['name' => 'مبتدئ', 'sort_order' => 1]);
        app(SaveTajweedLevel::class)->handle(['name' => 'متوسط', 'sort_order' => 2]);

        $renamed = app(SaveTajweedLevel::class)->handle(['name' => 'تمهيدي', 'sort_order' => 1], $level);
        $this->assertSame('تمهيدي', $renamed->fresh()->name);

        $this->expectException(ValidationException::class);

        app(SaveTajweedLevel::class)->handle(['name' => 'متوسط'], $level);
    }

    public function test_a_tajweed_level_in_use_cannot_be_deleted(): void
    {
        $level = TajweedLevel::factory()->create();
        Student::factory()->create(['tajweed_level_id' => $level->getKey()]);

        $this->assertViolates('tajweed_level.in_use', fn () => app(DeleteTajweedLevel::class)->handle($level));

        $unused = TajweedLevel::factory()->create();
        app(DeleteTajweedLevel::class)->handle($unused);
        $this->assertModelMissing($unused);
    }
}
