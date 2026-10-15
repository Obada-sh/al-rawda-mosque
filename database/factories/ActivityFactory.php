<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'نشاط '.fake()->unique()->numerify('####'),
            'activity_date' => now()->toDateString(),
            'circle_id' => null,
            'points' => 5,
        ];
    }
}
