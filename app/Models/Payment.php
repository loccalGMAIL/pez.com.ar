<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\PaymentMethod;
use App\Models\Concerns\HasSequentialNumber;
use Carbon\CarbonInterface;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasSequentialNumber;

    public const NUMBER_PREFIX = 'R';

    public const NUMBER_CONFIG = 'pez.payments.first_number';

    protected $fillable = [
        'client_id',
        'paid_on',
        'amount',
        'method',
        'reference',
        'receipt_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => Money::class,
            'paid_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // One by one so each charge recomputes what it has been paid.
        static::deleting(fn (Payment $payment) => $payment->allocations()->get()->each->delete());
    }

    /**
     * Records a payment, settles the chosen charges first and then the
     * client's oldest open charges. Whatever is left stays as credit.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int>  $chargeIds
     */
    public static function register(array $data, array $chargeIds = []): self
    {
        return DB::transaction(function () use ($data, $chargeIds) {
            $payment = static::create($data);

            $payment->allocateTo(
                $payment->client->charges()->open()->whereKey($chargeIds)->orderBy('due_on')->orderBy('id')->get(),
            );
            $payment->client->applyCredit();

            return $payment;
        });
    }

    /**
     * Link the client can open without logging in; the signature prevents guessing other receipts.
     */
    public function publicReceiptUrl(): string
    {
        return URL::signedRoute('receipts.pdf', $this);
    }

    public function unallocated(): float
    {
        return max(0, round($this->amount - $this->allocations()->sum('amount') / 100, 2));
    }

    /**
     * @param  iterable<Charge>  $charges
     */
    public function allocateTo(iterable $charges): void
    {
        foreach ($charges as $charge) {
            $available = $this->unallocated();

            if ($available <= 0) {
                return;
            }

            $amount = min($available, $charge->refresh()->outstanding());

            if ($charge->status->isOpen() && $amount > 0) {
                $this->allocations()->create(['charge_id' => $charge->id, 'amount' => $amount]);
            }
        }
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Purchase cost behind the payments of a period, in pesos. A payment that
     * settles part of a resale charge carries that part of the charge's cost;
     * charges without a loaded cost add nothing.
     */
    public static function costBetween(CarbonInterface $from, CarbonInterface $to): float
    {
        return round(PaymentAllocation::query()
            ->whereHas('payment', fn ($query) => $query->whereBetween('paid_on', [$from, $to]))
            ->with('charge')
            ->get()
            ->sum(fn (PaymentAllocation $allocation) => $allocation->charge?->cost !== null && $allocation->charge->amount > 0
                ? $allocation->amount * $allocation->charge->cost / $allocation->charge->amount
                : 0), 2);
    }
}
