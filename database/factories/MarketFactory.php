<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Market>
 */
class MarketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => 'سوق '.fake()->unique()->numerify('####'),
            'is_open' => true,
        ];
    }

    public function closed(): static
    {
        return $this->state(['is_open' => false]);
    }
}
