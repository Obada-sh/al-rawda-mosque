<?php

namespace Database\Factories\English;

use App\Enums\SessionStatus;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnglishSession>
 */
class EnglishSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'english_course_id' => EnglishCourse::factory(),
            'session_date' => now()->toDateString(),
            'status' => SessionStatus::Held,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['status' => SessionStatus::Cancelled]);
    }
}
