<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\QuoteStatus;
use App\Mail\QuoteMail;
use App\Support\QuotePdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class Quote extends Model
{
    /** @use HasFactory<\Database\Factories\QuoteFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'issue_date',
        'valid_until',
        'discount',
        'notes',
        'terms',
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
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Quote $quote) {
            $quote->number ??= static::nextNumber();
        });

        static::saving(function (Quote $quote) {
            if ($quote->isDirty('discount')) {
                $quote->total = max(0, $quote->subtotal - $quote->discount);
            }
        });
    }

    public static function nextNumber(): int
    {
        $last = static::withTrashed()->max('number');

        return max((int) $last + 1, config('pez.quotes.first_number'));
    }

    /**
     * Number as printed on documents: P-00100.
     */
    protected function code(): Attribute
    {
        return Attribute::get(fn () => 'P-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT));
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items()->get()->sum('subtotal');
        $this->total = max(0, $this->subtotal - $this->discount);
        $this->saveQuietly();
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
     * New draft with the same client and items, dated today.
     */
    public function duplicate(): self
    {
        return DB::transaction(function () {
            $copy = static::create([
                'client_id' => $this->client_id,
                'issue_date' => today(),
                'valid_until' => today()->addDays(config('pez.quotes.valid_days')),
                'discount' => $this->discount,
                'notes' => $this->notes,
                'terms' => $this->terms,
            ]);

            foreach ($this->items as $item) {
                $copy->items()->create($item->only([
                    'service_item_id', 'description', 'quantity', 'unit', 'unit_price', 'sort',
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
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort');
    }
}
