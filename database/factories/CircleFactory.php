<?php

namespace Database\Factories;

use App\Models\Circle;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Circle>
 */
class CircleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => 'حلقة '.fake()->unique()->numerify('####'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
