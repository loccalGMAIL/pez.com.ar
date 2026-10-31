<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'description' => fake()->sentence(4),
            'quantity' => fake()->numberBetween(1, 5),
            'unit' => 'unidad',
            'unit_price' => fake()->randomFloat(2, 1000, 100000),
            'sort' => 0,
        ];
    }

    public function withCost(float $unitCost): static
    {
        return $this->state(fn () => ['unit_cost' => $unitCost, 'supplier_id' => Supplier::factory()]);
    }
}
