<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\TajweedLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A student with only a name, like one registered in a hurry.
 * Use complete() for a student whose profile is fully filled in.
 *
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'is_profile_complete' => false,
        ];
    }

    public function complete(): static
    {
        return $this->state(fn (): array => [
            'birth_date' => fake()->dateTimeBetween('-18 years', '-7 years')->format('Y-m-d'),
            'phone' => '09'.fake()->numerify('########'),
            'guardian_phone' => '09'.fake()->numerify('########'),
            'address' => fake()->city(),
            'tajweed_level_id' => TajweedLevel::factory(),
            'prior_memorized_pages' => 0,
            'is_profile_complete' => true,
        ]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
