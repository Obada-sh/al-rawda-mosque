<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone' => '09'.fake()->numerify('########'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
