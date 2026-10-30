<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\QuoteStatus;
use App\Mail\QuoteMail;
use App\Models\Concerns\HasSequentialNumber;
use App\Support\QuotePdf;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class Quote extends Model
{
    /** @use HasFactory<\Database\Factories\QuoteFactory> */
    use HasFactory, HasSequentialNumber, SoftDeletes;

    public const NUMBER_PREFIX = 'P';

    public const NUMBER_CONFIG = 'pez.quotes.first_number';

    protected $fillable = [
        'client_id',
        'title',
        'issue_date',
        'valid_until',
        'discount',
        'notes',
        'warranty',
        'payment_terms',
        'delivery_time',
        'terms',
        'installment_plans',
    ];

    protected $attributes = [
        'status' => 'borrador',
        'subtotal' => 0,
        'discount' => 0,
        'total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => Money::class,
            'discount' => Money::class,
            'total' => Money::class,
            'installment_plans' => 'array',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Quote $quote) {
            if ($quote->isDirty('discount')) {
                $quote->total = max(0, $quote->subtotal - $quote->discount);
            }

            $quote->priceInstallments();
        });
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items()->get()->sum('subtotal');
        $this->total = max(0, $this->subtotal - $this->discount);
        $this->priceInstallments();
        $this->saveQuietly();
    }

    /**
     * Plans proposed for new quotes, from the settings page.
     *
     * @return array<int, array{count: int, surcharge: float}>
     */
    public static function defaultInstallmentPlans(): array
    {
        return collect(Settings::get('quotes.installments') ?? [])
            ->map(fn (array $plan) => ['count' => (int) $plan['count'], 'surcharge' => (float) $plan['surcharge']])
            ->all();
    }

    /**
     * Installment options with their amounts, fewest installments first. The
     * amounts are stored with the quote, so they stay as they were offered
     * even if the default surcharges change later.
     *
     * @return Collection<int, array{count: int, surcharge: float, total: float, amount: float}>
     */
    public function installments(): Collection
    {
        return collect($this->installment_plans ?? [])
            ->filter(fn (array $plan) => (int) ($plan['count'] ?? 0) > 1)
            ->map(fn (array $plan) => [
                'count' => (int) $plan['count'],
                'surcharge' => (float) ($plan['surcharge'] ?? 0),
                'total' => (float) ($plan['total'] ?? 0),
                'amount' => (float) ($plan['amount'] ?? 0),
            ])
            ->sortBy('count')
            ->values();
    }

    /**
     * Stores each plan's financed total and installment amount for the current total.
     */
    private function priceInstallments(): void
    {
        if (blank($this->installment_plans)) {
            return;
        }

        $this->installment_plans = collect($this->installment_plans)
            ->map(function (array $plan) {
                $count = max(1, (int) ($plan['count'] ?? 1));
                $surcharge = (float) ($plan['surcharge'] ?? 0);
                $total = round($this->total * (1 + $surcharge / 100), 2);

                return [
                    'count' => $count,
                    'surcharge' => $surcharge,
                    'total' => $total,
                    'amount' => round($total / $count, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Validity as printed on the PDF: "7 días".
     */
    public function validDays(): int
    {
        return (int) $this->issue_date->diffInDays($this->valid_until);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Archives the PDF as delivered and moves a draft to "sent". Re-sending an
     * already sent quote refreshes the archived PDF with its current content.
     */
    public function markAsSent(): void
    {
        $this->pdf_path = QuotePdf::store($this);
        $this->status = QuoteStatus::Sent;
        $this->sent_at = now();
        $this->save();
    }

    /**
     * @param  array<int, string>|string  $to
     */
    public function sendTo(array|string $to, string $body): void
    {
        $this->markAsSent();

        Mail::to($to)->send(new QuoteMail($this, $body));
    }

    /**
     * Link the client can open without logging in. It has no expiry so it keeps
     * working if the client answers late; the signature prevents guessing other quotes.
     */
    public function publicPdfUrl(): string
    {
        return URL::signedRoute('quotes.pdf', $this);
    }

    public function accept(): void
    {
        $this->decide(QuoteStatus::Accepted);
    }

    public function reject(): void
    {
        $this->decide(QuoteStatus::Rejected);
    }

    public function expire(): void
    {
        $this->status = QuoteStatus::Expired;
        $this->save();
    }

    private function decide(QuoteStatus $status): void
    {
        $this->status = $status;
        $this->decided_at = now();
        $this->save();
    }

    /**
     * Opens the job for an accepted quote, or returns the one already opened.
     */
    public function convertToWorkOrder(): WorkOrder
    {
        if ($existing = $this->workOrder()->first()) {
            return $existing;
        }

        return $this->workOrder()->create([
            'client_id' => $this->client_id,
            'title' => $this->title ?: "Presupuesto {$this->code}",
            'description' => $this->items->map(fn (QuoteItem $item) => '- '.rtrim(QuotePdf::quantity($item->quantity).' '.$item->unit).': '.$item->label())->join("\n"),
            'opened_on' => today(),
        ]);
    }

    /**
     * Charges the accepted quote to the client's account, once.
     */
    public function charge(): Charge
    {
        if ($existing = $this->charges()->first()) {
            return $existing;
        }

        return $this->charges()->create([
            'client_id' => $this->client_id,
            'concept' => trim("Presupuesto {$this->code} ".($this->title ? "— {$this->title}" : '')),
            'amount' => $this->total,
        ]);
    }

    /**
     * New draft with the same client and items, dated today.
     */
    public function duplicate(): self
    {
        return DB::transaction(function () {
            $copy = static::create([
                'client_id' => $this->client_id,
                'issue_date' => today(),
                'valid_until' => today()->addDays((int) Settings::get('quotes.valid_days')),
                'discount' => $this->discount,
                'title' => $this->title,
                'notes' => $this->notes,
                'warranty' => $this->warranty,
                'payment_terms' => $this->payment_terms,
                'delivery_time' => $this->delivery_time,
                'terms' => $this->terms,
                'installment_plans' => $this->installment_plans,
            ]);

            foreach ($this->items as $item) {
                $copy->items()->create($item->only([
                    'service_item_id', 'title', 'description', 'quantity', 'unit', 'unit_price', 'sort',
                ]));
            }

            return $copy->refresh();
        });
    }

    /**
     * @param  Builder<Quote>  $query
     */
    public function scopeExpirable(Builder $query): void
    {
        $query->where('status', QuoteStatus::Sent)
            ->whereDate('valid_until', '<', today());
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

    /**
     * @return HasOne<WorkOrder, $this>
     */
    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort');
    }
}
