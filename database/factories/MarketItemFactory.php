<?php

namespace Database\Factories;

use App\Models\Market;
use App\Models\MarketItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketItem>
 */
class MarketItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'market_id' => Market::factory(),
            'name' => 'بضاعة '.fake()->unique()->numerify('####'),
            'price' => 10,
            'stock' => null,
            'is_available' => true,
        ];
    }
}
