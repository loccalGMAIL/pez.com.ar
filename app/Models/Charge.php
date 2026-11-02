<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\ChargeStatus;
use App\Enums\InvoiceType;
use App\Support\Settings;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use LogicException;

class Charge extends Model
{
    /** @use HasFactory<ChargeFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'subscription_id',
        'quote_id',
        'work_order_id',
        'supplier_id',
        'period',
        'concept',
        'notes',
        'amount',
        'cost',
        'is_resale',
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
            'cost' => Money::class,
            'is_resale' => 'boolean',
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

    /**
     * Sale price minus what it cost, or null while the cost is unknown.
     */
    public function margin(): ?float
    {
        return $this->cost === null ? null : round($this->amount - $this->cost, 2);
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
        // An invoice is a fiscal document: it is annulled with a credit note first.
        if ($this->invoicesInForce()->isNotEmpty()) {
            throw new LogicException('El cargo está facturado: anulá su factura con nota de crédito antes de anularlo.');
        }

        $this->status = ChargeStatus::Cancelled;
        $this->save();

        $this->allocations()->get()->each->delete();
        $this->client->applyCredit();
    }

    /**
     * Invoices that bill this charge (issued from it or from a payment) and
     * still count: authorized or on their way to ARCA, and not annulled.
     *
     * @return EloquentCollection<int, Invoice>
     */
    public function invoicesInForce(): EloquentCollection
    {
        return Invoice::query()
            ->counting()
            ->whereHas('items', fn (Builder $items) => $items->where('charge_id', $this->id))
            ->orderBy('id')
            ->get();
    }

    /**
     * How much of the charge is already invoiced, in pesos: the items that bill
     * it, plus what it got from payments invoiced with credit left on account
     * (that invoice already billed the money). $except leaves out an invoice
     * being retried.
     */
    public function invoicedAmount(?Invoice $except = null): float
    {
        $counting = fn (Builder $query) => Invoice::whereCounting($query, $except);

        $itemized = InvoiceItem::query()
            ->where('charge_id', $this->id)
            ->whereHas('invoice', $counting)
            ->sum('amount') / 100;

        $fromCredit = $this->allocations()
            ->whereHas('payment.invoices', $counting)
            ->whereDoesntHave('payment.invoices', fn (Builder $query) => $counting($query)->whereHas('items', fn (Builder $items) => $items->where('charge_id', $this->id)))
            ->sum('amount') / 100;

        return min((float) $this->amount, round($itemized + $fromCredit, 2));
    }

    public function pendingToInvoice(?Invoice $except = null): float
    {
        return max(0, round($this->amount - $this->invoicedAmount($except), 2));
    }

    /**
     * Charge of a subscription whose client needs the invoice to pay, still not invoiced.
     */
    public function needsInvoice(): bool
    {
        return in_array($this->status, [ChargeStatus::Pending, ChargeStatus::Partial, ChargeStatus::Paid], true)
            && (bool) $this->subscription?->invoice_before_payment
            && $this->pendingToInvoice() > 0;
    }

    /**
     * Charges waiting for "Facturar cargo". Few by nature (one per marked
     * subscription and month), so the invoiced amount is worked out per charge.
     *
     * @return EloquentCollection<int, static>
     */
    public static function awaitingInvoice(): EloquentCollection
    {
        return static::query()
            ->whereIn('status', [ChargeStatus::Pending, ChargeStatus::Partial, ChargeStatus::Paid])
            ->whereHas('subscription', fn (Builder $query) => $query->where('invoice_before_payment', true))
            ->with('subscription')
            ->get()
            ->filter(fn (Charge $charge) => $charge->needsInvoice())
            ->values();
    }

    /**
     * @param  Builder<Charge>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', ChargeStatus::open());
    }

    /**
     * Resale charges still missing what they cost.
     *
     * @param  Builder<Charge>  $query
     */
    public function scopeCostPending(Builder $query): void
    {
        $query->where('is_resale', true)->whereNull('cost')->where('status', '!=', ChargeStatus::Cancelled);
    }

    /**
     * @param  Builder<Charge>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereDate('due_on', '<', today());
    }

    /**
     * Sum still owed on open charges, in pesos; all charges or those of $query.
     *
     * @param  Builder<Charge>|null  $query
     */
    public static function totalOutstanding(?Builder $query = null): float
    {
        return (float) ($query ?? static::query())->open()->sum(DB::raw('amount - paid')) / 100;
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    /**
     * Invoices issued from this charge (before it was paid), latest last.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->where('type', InvoiceType::FacturaC);
    }

    /**
     * The latest invoice issued (or attempted) from this charge.
     *
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->ofMany(['id' => 'max'], fn (Builder $query) => $query->where('type', InvoiceType::FacturaC));
    }
}
