<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\ChargeStatus;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Charge extends Model
{
    /** @use HasFactory<\Database\Factories\ChargeFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'subscription_id',
        'quote_id',
        'work_order_id',
        'period',
        'concept',
        'notes',
        'amount',
        'issued_on',
        'due_on',
        'status',
    ];

    protected $attributes = [
        'status' => 'pendiente',
        'paid' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ChargeStatus::class,
            'amount' => Money::class,
            'paid' => Money::class,
            'period' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Charge $charge) {
            $charge->issued_on ??= today();
            $charge->due_on ??= $charge->issued_on->copy()->addDays((int) Settings::get('billing.due_days'));
        });

        // A new or confirmed charge takes any credit the client has.
        static::saved(function (Charge $charge) {
            if ($charge->status->isOpen() && ($charge->wasRecentlyCreated || $charge->wasChanged(['status', 'amount']))) {
                $charge->client->applyCredit();
            }
        });
    }

    public function outstanding(): float
    {
        return max(0, round($this->amount - $this->paid, 2));
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_on->isPast() && ! $this->due_on->isToday();
    }

    /**
     * Syncs the paid amount and status with the payments allocated to it.
     */
    public function refreshPaid(): void
    {
        $this->paid = $this->allocations()->sum('amount') / 100;

        if ($this->status !== ChargeStatus::Draft && $this->status !== ChargeStatus::Cancelled) {
            $this->status = match (true) {
                $this->paid >= $this->amount => ChargeStatus::Paid,
                $this->paid > 0 => ChargeStatus::Partial,
                default => ChargeStatus::Pending,
            };
        }

        $this->saveQuietly();
    }

    /**
     * Turns a draft (variable subscriptions) into a charge the client owes.
     */
    public function confirm(?float $amount = null): void
    {
        if ($amount !== null) {
            $this->amount = $amount;
        }

        $this->status = ChargeStatus::Pending;
        $this->save();
        $this->refreshPaid();
    }

    /**
     * Payments allocated to it go back to the client's credit.
     */
    public function cancel(): void
    {
        $this->status = ChargeStatus::Cancelled;
        $this->save();

        $this->allocations()->get()->each->delete();
        $this->client->applyCredit();
    }

    /**
     * @param  Builder<Charge>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', ChargeStatus::open());
    }

    /**
     * @param  Builder<Charge>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereDate('due_on', '<', today());
    }

    /**
     * Sum still owed, in pesos.
     *
     * @param  Builder<Charge>  $query
     */
    public function scopeOutstandingTotal(Builder $query): float
    {
        return (float) $query->open()->sum(DB::raw('amount - paid')) / 100;
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class)->withTrashed();
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class)->withTrashed();
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
