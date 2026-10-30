<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ExternalRepair>
 */
class ExternalRepairFactory extends Factory
{
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'problem' => fake()->sentence(),
            'supplier_reference' => fake()->optional()->numerify('OT-####'),
            'sent_on' => today(),
            'cost' => fake()->optional()->randomFloat(2, 5000, 100000),
        ];
    }
}
