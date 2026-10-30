<?php

namespace Database\Factories;

use App\Enums\TaxCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'trade_name' => fake()->optional()->company(),
            'cuit' => null,
            'tax_condition' => fake()->randomElement(TaxCondition::cases()),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'province' => 'Buenos Aires',
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
