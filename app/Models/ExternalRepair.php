<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\ExternalRepairStatus;
use Database\Factories\ExternalRepairFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalRepair extends Model
{
    /** @use HasFactory<ExternalRepairFactory> */
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'supplier_id',
        'device_id',
        'problem',
        'supplier_reference',
        'status',
        'sent_on',
        'returned_on',
        'cost',
        'price',
        'notes',
    ];

    protected $attributes = [
        'status' => 'enviado',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExternalRepairStatus::class,
            'sent_on' => 'date',
            'returned_on' => 'date',
            'cost' => Money::class,
            'price' => Money::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ExternalRepair $repair) {
            if ($repair->status->isOut()) {
                $repair->returned_on = null;
            } else {
                $repair->returned_on ??= today();
            }
        });

        // Query the order again: a cached relation may hold a stale status.
        static::saved(fn (ExternalRepair $repair) => $repair->workOrder()->first()?->syncExternalStatus());
        static::deleted(fn (ExternalRepair $repair) => $repair->workOrder()->first()?->syncExternalStatus());
    }

    /**
     * @param  Builder<ExternalRepair>  $query
     */
    public function scopeOut(Builder $query): void
    {
        $query->whereIn('status', ExternalRepairStatus::out());
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }
}
