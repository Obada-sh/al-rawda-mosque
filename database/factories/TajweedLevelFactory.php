<?php

namespace Database\Factories;

use App\Models\TajweedLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TajweedLevel>
 */
class TajweedLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'مستوى '.Str::random(8),
            'sort_order' => fake()->numberBetween(1, 9),
        ];
    }
}
