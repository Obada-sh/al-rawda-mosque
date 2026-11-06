<?php

namespace Database\Factories\English;

use App\Enums\CourseStatus;
use App\Enums\Weekday;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishCourseSchedule;
use App\Models\English\EnglishLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active English course of 150,000 SYP that started 60 days ago, with no
 * end date and a class every day of the week (so tests are not tied to a
 * weekday) unless meetingOn() says otherwise.
 *
 * @extends Factory<EnglishCourse>
 */
class EnglishCourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'English '.fake()->unique()->numerify('####'),
            'english_level_id' => EnglishLevel::factory(),
            'price' => 150000,
            'start_date' => now()->subDays(60)->toDateString(),
            'end_date' => null,
            'status' => CourseStatus::Active,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (EnglishCourse $course): void {
            if ($course->schedules()->doesntExist()) {
                $this->createSchedules($course, Weekday::cases());
            }
        });
    }

    /**
     * @param  list<Weekday>  $weekdays
     */
    public function meetingOn(array $weekdays): static
    {
        return $this->afterCreating(function (EnglishCourse $course) use ($weekdays): void {
            $course->schedules()->delete();
            $this->createSchedules($course, $weekdays);
        });
    }

    public function finished(): static
    {
        return $this->state(['status' => CourseStatus::Finished]);
    }

    /**
     * @param  list<Weekday>  $weekdays
     */
    private function createSchedules(EnglishCourse $course, array $weekdays): void
    {
        foreach ($weekdays as $weekday) {
            EnglishCourseSchedule::query()->create([
                'english_course_id' => $course->getKey(),
                'weekday' => $weekday,
                'start_time' => '16:00:00',
                'end_time' => '18:00:00',
            ]);
        }
    }
}
