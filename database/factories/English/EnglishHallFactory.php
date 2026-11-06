<?php

namespace Database\Factories\English;

use App\Models\English\EnglishCourse;
use App\Models\English\EnglishHall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnglishHall>
 */
class EnglishHallFactory extends Factory
{
    public function definition(): array
    {
        return [
            'english_course_id' => EnglishCourse::factory(),
            'name' => 'قاعة '.fake()->unique()->numerify('####'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
