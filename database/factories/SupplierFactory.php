<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'specialty' => fake()->randomElement(['Impresoras', 'Fuentes y placas', 'Notebooks', 'Celulares']),
            'contact_name' => fake()->name(),
            'phone' => fake()->numerify('11 ####-####'),
            'email' => fake()->companyEmail(),
        ];
    }
}
