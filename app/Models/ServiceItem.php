<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\CatalogItemType;
use App\Enums\ProductCondition;
use App\Enums\ServiceUnit;
use Database\Factories\ServiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceItem extends Model
{
    /** @use HasFactory<ServiceItemFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'condition',
        'supplier_id',
        'name',
        'description',
        'notes',
        'unit',
        'price',
        'cost',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CatalogItemType::class,
            'condition' => ProductCondition::class,
            'unit' => ServiceUnit::class,
            'price' => Money::class,
            'cost' => Money::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
