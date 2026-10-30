<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\ChargeStatus;
use App\Enums\SubscriptionType;
use App\Enums\WorkOrderStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'concept',
        'type',
        'amount',
        'billing_day',
        'starts_on',
        'ends_on',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => SubscriptionType::class,
            'amount' => Money::class,
            'billing_day' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Subscriptions whose charge for the month of $date is due by that day.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeBillableOn(Builder $query, Carbon $date): void
    {
        $query->where('is_active', true)
            ->whereDate('starts_on', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date->copy()->startOfMonth()))
            ->where('billing_day', '<=', $date->day);
    }

    /**
     * Creates the charge for the month of $date, once. Fixed subscriptions are
     * owed right away; variable ones start as a draft listing last month's jobs
     * so the amount can be adjusted before confirming it.
     */
    public function bill(Carbon $date): ?Charge
    {
        $period = $date->copy()->startOfMonth();

        if ($this->charges()->whereDate('period', $period)->exists()) {
            return null;
        }

        $variable = $this->type === SubscriptionType::Variable;

        return $this->charges()->create([
            'client_id' => $this->client_id,
            'period' => $period,
            'concept' => "Abono {$this->concept} — ".ucfirst($period->translatedFormat('F Y')),
            'notes' => $variable ? $this->previousMonthWork($period) : null,
            'amount' => $this->amount,
            'issued_on' => $date,
            'status' => $variable ? ChargeStatus::Draft : ChargeStatus::Pending,
        ]);
    }

    private function previousMonthWork(Carbon $period): string
    {
        $from = $period->copy()->subMonth();
        $orders = $this->client->workOrders()
            ->whereNotIn('status', WorkOrderStatus::open())
            ->whereBetween('finished_at', [$from, $period])
            ->orderBy('finished_at')
            ->get();

        $title = 'Órdenes de trabajo terminadas en '.$from->translatedFormat('F Y').':';

        return $orders->isEmpty()
            ? "{$title} ninguno."
            : $title."\n".$orders->map(fn (WorkOrder $order) => "- {$order->code} {$order->title}".($order->hours ? " ({$order->hours} h)" : ''))->join("\n");
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
