<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\Course;
use App\Models\CourseSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSession>
 */
class CourseSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'session_date' => now()->toDateString(),
            'status' => SessionStatus::Held,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['status' => SessionStatus::Cancelled]);
    }
}
