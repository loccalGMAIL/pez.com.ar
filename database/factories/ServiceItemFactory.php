<?php

namespace Database\Factories;

use App\Enums\ServiceUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ServiceItem>
 */
class ServiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'unit' => fake()->randomElement(ServiceUnit::cases()),
            'price' => fake()->randomFloat(2, 5000, 500000),
            'is_active' => true,
        ];
    }
}
