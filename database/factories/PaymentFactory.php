<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'paid_on' => today(),
            'amount' => fake()->randomFloat(2, 10000, 300000),
            'method' => fake()->randomElement(PaymentMethod::cases()),
        ];
    }
}
