<?php

namespace Database\Factories;

use App\Enums\InvoiceConcept;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\RecipientDocumentType;
use App\Enums\TaxCondition;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'environment' => config('arca.environment'),
            'type' => InvoiceType::FacturaC,
            'point_of_sale' => 3,
            'concept' => InvoiceConcept::Services,
            'service_from' => today()->startOfMonth(),
            'service_to' => today(),
            'recipient_document_type' => RecipientDocumentType::Unidentified,
            'recipient_document_number' => '0',
            'recipient_tax_condition' => TaxCondition::ConsumidorFinal,
            'recipient_name' => fake()->name(),
            'total' => fake()->randomFloat(2, 10000, 300000),
        ];
    }

    /**
     * Authorized by ARCA, with its number and CAE.
     */
    public function authorized(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Authorized,
            'number' => fake()->unique()->numberBetween(1, 99999),
            'issued_on' => today(),
            'cae' => fake()->numerify('7##############'),
            'cae_due_on' => today()->addDays(10),
        ]);
    }
}
