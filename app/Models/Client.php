<?php

namespace App\Models;

use App\Enums\TaxCondition;
use App\Rules\Cuit;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_name',
        'trade_name',
        'cuit',
        'tax_condition',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tax_condition' => TaxCondition::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Stored as 11 digits, shown as XX-XXXXXXXX-X.
     */
    protected function cuit(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Cuit::format($value),
            set: fn (?string $value) => blank($value) ? null : Cuit::normalize($value),
        );
    }

    /**
     * @return HasMany<ClientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Uses unallocated payments to settle open charges, oldest first.
     */
    public function applyCredit(): void
    {
        DB::transaction(function () {
            $charges = $this->charges()->open()->orderBy('due_on')->orderBy('id')->get();

            if ($charges->isEmpty()) {
                return;
            }

            $this->payments()->orderBy('paid_on')->orderBy('id')->get()
                ->filter(fn (Payment $payment) => $payment->unallocated() > 0)
                ->each(fn (Payment $payment) => $payment->allocateTo($charges));
        });
    }

    /**
     * Owed on open charges, in pesos.
     */
    public function outstanding(): float
    {
        return $this->charges()->outstandingTotal();
    }

    /**
     * Payments not yet applied to any charge, in pesos.
     */
    public function credit(): float
    {
        $paid = $this->payments()->sum('amount');
        $allocated = PaymentAllocation::whereIn('payment_id', $this->payments()->select('id'))->sum('amount');

        return round(($paid - $allocated) / 100, 2);
    }

    /**
     * Positive when the client owes money, negative when it has credit.
     */
    public function balance(): float
    {
        return round($this->outstanding() - $this->credit(), 2);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return HasOne<ClientContact, $this>
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }
}
