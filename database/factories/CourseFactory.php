<?php

namespace Database\Factories;

use App\Enums\CourseStatus;
use App\Enums\Weekday;
use App\Models\Course;
use App\Models\CourseSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active course that started 60 days ago, with no end date and a class
 * every day of the week (so tests are not tied to a weekday) unless
 * meetingOn() says otherwise.
 *
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'دورة '.fake()->unique()->numerify('####'),
            'start_date' => now()->subDays(60)->toDateString(),
            'end_date' => null,
            'points_per_page' => 2,
            'points_per_attendance' => 1,
            'status' => CourseStatus::Active,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Course $course): void {
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
        return $this->afterCreating(function (Course $course) use ($weekdays): void {
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
    private function createSchedules(Course $course, array $weekdays): void
    {
        foreach ($weekdays as $weekday) {
            CourseSchedule::query()->create([
                'course_id' => $course->getKey(),
                'weekday' => $weekday,
                'start_time' => '16:00:00',
                'end_time' => '19:00:00',
            ]);
        }
    }
}
