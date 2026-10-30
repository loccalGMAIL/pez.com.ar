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
     * @return HasOne<ClientContact, $this>
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }
}
