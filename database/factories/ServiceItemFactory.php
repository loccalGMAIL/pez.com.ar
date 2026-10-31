<?php

namespace Database\Factories;

use App\Enums\CatalogItemType;
use App\Enums\ProductCondition;
use App\Enums\ServiceUnit;
use App\Models\ServiceItem;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceItem>
 */
class ServiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => CatalogItemType::Service,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'unit' => fake()->randomElement(ServiceUnit::cases()),
            'price' => fake()->randomFloat(2, 5000, 500000),
            'is_active' => true,
        ];
    }

    public function product(ProductCondition $condition = ProductCondition::New): static
    {
        return $this->state(fn () => [
            'type' => CatalogItemType::Product,
            'condition' => $condition,
            'unit' => ServiceUnit::Unit,
        ]);
    }

    public function withCost(float $cost): static
    {
        return $this->state(fn () => ['cost' => $cost, 'supplier_id' => Supplier::factory()]);
    }
}
