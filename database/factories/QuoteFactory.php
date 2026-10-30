<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'issue_date' => today(),
            'valid_until' => today()->addDays(15),
            'notes' => null,
            'terms' => null,
        ];
    }

    public function withItems(int $count = 2): static
    {
        return $this->afterCreating(function (Quote $quote) use ($count) {
            QuoteItem::factory()->count($count)->for($quote)->create();
        });
    }

    public function status(QuoteStatus $status): static
    {
        return $this->afterMaking(function (Quote $quote) use ($status) {
            $quote->status = $status;
            $quote->sent_at = $status === QuoteStatus::Draft ? null : now();
        });
    }
}
