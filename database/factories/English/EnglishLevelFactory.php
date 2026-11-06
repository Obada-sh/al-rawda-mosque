<?php

namespace Database\Factories\English;

use App\Models\English\EnglishLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A level apart from the six the migration adds (A1 ... C2).
 *
 * @extends Factory<EnglishLevel>
 */
class EnglishLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Level '.Str::upper(Str::random(6)),
            'sort_order' => fake()->numberBetween(10, 99),
        ];
    }
}
