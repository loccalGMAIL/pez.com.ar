<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'specialty',
        'contact_name',
        'phone',
        'email',
        'address',
        'notes',
    ];

    /**
     * @return HasMany<ExternalRepair, $this>
     */
    public function externalRepairs(): HasMany
    {
        return $this->hasMany(ExternalRepair::class);
    }
}
