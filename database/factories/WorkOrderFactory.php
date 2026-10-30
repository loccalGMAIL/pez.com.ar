<?php

namespace Database\Factories;

use App\Enums\WorkOrderStatus;
use App\Models\Client;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'title' => fake()->randomElement(['No enciende', 'Configurar red WiFi', 'Mantenimiento preventivo', 'Instalar impresora']),
            'description' => fake()->optional()->sentence(),
            'opened_on' => today(),
        ];
    }

    public function status(WorkOrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
