<?php

namespace App\Models;

use App\Casts\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $fillable = [
        'charge_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => Money::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (PaymentAllocation $allocation) => Charge::find($allocation->charge_id)?->refreshPaid());
        static::deleted(fn (PaymentAllocation $allocation) => Charge::find($allocation->charge_id)?->refreshPaid());
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Charge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
