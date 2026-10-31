<?php

namespace App\Models;

use App\Enums\PurchaseChannel;
use App\Enums\SupplierKind;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'kind',
        'channel',
        'specialty',
        'contact_name',
        'phone',
        'email',
        'address',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SupplierKind::class,
            'channel' => PurchaseChannel::class,
        ];
    }

    /**
     * @return HasMany<ExternalRepair, $this>
     */
    public function externalRepairs(): HasMany
    {
        return $this->hasMany(ExternalRepair::class);
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
