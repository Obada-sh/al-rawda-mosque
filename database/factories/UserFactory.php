<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => 'user_'.Str::lower(Str::random(10)),
            'password' => 'password',
            'remember_token' => Str::random(10),
        ];
    }
}
