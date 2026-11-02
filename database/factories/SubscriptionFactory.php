<?php

namespace Database\Factories;

use App\Enums\SubscriptionType;
use App\Models\Client;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'concept' => 'Mantenimiento mensual',
            'type' => SubscriptionType::Fixed,
            'amount' => 80000,
            'billing_day' => 1,
            'starts_on' => today()->subMonths(3)->startOfMonth(),
            'is_active' => true,
        ];
    }

    public function variable(): static
    {
        return $this->state(fn () => ['type' => SubscriptionType::Variable]);
    }

    /**
     * The client needs the invoice to pay, so its charges are invoiced first.
     */
    public function invoicedBeforePayment(): static
    {
        return $this->state(fn () => ['invoice_before_payment' => true]);
    }
}
