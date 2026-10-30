<?php

namespace Database\Factories;

use App\Enums\DeviceType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'type' => fake()->randomElement(DeviceType::cases()),
            'name' => fake()->optional()->randomElement(['PC Recepción', 'Notebook Gerencia', 'Impresora Depósito']),
            'brand' => fake()->randomElement(['Dell', 'HP', 'Lenovo', 'Epson', 'TP-Link']),
            'model' => strtoupper(fake()->bothify('??-###')),
            'serial_number' => strtoupper(fake()->bothify('??########')),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
