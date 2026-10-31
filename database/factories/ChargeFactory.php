<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'concept' => fake()->randomElement(['Visita técnica', 'Instalación de red', 'Mantenimiento']),
            'amount' => fake()->randomFloat(2, 10000, 300000),
            'issued_on' => today(),
        ];
    }

    /**
     * A resale charge; without a cost it is pending until the cost is loaded.
     */
    public function resale(?float $cost = null): static
    {
        return $this->state(fn () => ['is_resale' => true, 'cost' => $cost, 'supplier_id' => $cost === null ? null : Supplier::factory()]);
    }
}
