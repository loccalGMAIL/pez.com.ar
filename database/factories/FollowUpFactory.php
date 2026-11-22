<?php

namespace Database\Factories;

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Models\Client;
use App\Models\FollowUp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUp>
 */
class FollowUpFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'kind' => FollowUpKind::WorkOrder,
            'occurred_on' => today()->subWeekdays(7),
            'due_on' => today(),
        ];
    }

    public function sale(): static
    {
        return $this->state(fn () => ['kind' => FollowUpKind::Sale]);
    }

    public function status(FollowUpStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
