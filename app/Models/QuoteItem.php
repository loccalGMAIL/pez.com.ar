<?php

namespace App\Models;

use App\Casts\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    /** @use HasFactory<\Database\Factories\QuoteItemFactory> */
    use HasFactory;

    protected $fillable = [
        'service_item_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => Money::class,
            'subtotal' => Money::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (QuoteItem $item) {
            $item->subtotal = round((float) $item->quantity * $item->unit_price, 2);
        });

        // Query the quote again: a cached relation may hold a stale discount.
        static::saved(fn (QuoteItem $item) => $item->quote()->first()?->recalculateTotals());
        static::deleted(fn (QuoteItem $item) => $item->quote()->first()?->recalculateTotals());
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<ServiceItem, $this>
     */
    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class);
    }
}
