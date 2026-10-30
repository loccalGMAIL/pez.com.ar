<?php

namespace App\Models;

use App\Enums\DeviceType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'type',
        'name',
        'brand',
        'model',
        'serial_number',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => DeviceType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * "PC Recepción — Dell OptiPlex 3080 (S/N ABC123)".
     */
    protected function label(): Attribute
    {
        return Attribute::get(function () {
            $model = trim("{$this->brand} {$this->model}");
            $label = $this->name ?: ($model ?: $this->type->getLabel());

            if ($this->name && $model !== '') {
                $label .= " — {$model}";
            }

            return $this->serial_number ? "{$label} (S/N {$this->serial_number})" : $label;
        });
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<WorkOrder, $this>
     */
    public function workOrders(): BelongsToMany
    {
        return $this->belongsToMany(WorkOrder::class);
    }

    /**
     * @return HasMany<ExternalRepair, $this>
     */
    public function externalRepairs(): HasMany
    {
        return $this->hasMany(ExternalRepair::class);
    }
}
