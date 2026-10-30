<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Concerns\HasSequentialNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    /** @use HasFactory<\Database\Factories\WorkOrderFactory> */
    use HasFactory, HasSequentialNumber, SoftDeletes;

    public const NUMBER_PREFIX = 'T';

    public const NUMBER_CONFIG = 'pez.work_orders.first_number';

    protected $fillable = [
        'client_id',
        'quote_id',
        'title',
        'description',
        'resolution',
        'status',
        'opened_on',
        'due_on',
        'hours',
        'attachments',
    ];

    protected $attributes = [
        'status' => 'pendiente',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'opened_on' => 'date',
            'due_on' => 'date',
            'finished_at' => 'datetime',
            'delivered_at' => 'datetime',
            'hours' => 'decimal:2',
            'attachments' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (WorkOrder $order) {
            if (! $order->isDirty('status')) {
                return;
            }

            if ($order->status->isOpen()) {
                $order->finished_at = null;
                $order->delivered_at = null;

                return;
            }

            $order->finished_at ??= now();

            if ($order->status === WorkOrderStatus::Delivered) {
                $order->delivered_at ??= now();
            } else {
                $order->delivered_at = null;
            }
        });
    }

    /**
     * Back to "in progress" once nothing is left at an external service.
     */
    public function syncExternalStatus(): void
    {
        $hasDevicesOut = $this->externalRepairs()->get()->contains(fn (ExternalRepair $repair) => $repair->status->isOut());

        if ($hasDevicesOut && in_array($this->status, [WorkOrderStatus::Pending, WorkOrderStatus::InProgress], true)) {
            $this->update(['status' => WorkOrderStatus::AwaitingExternal]);
        } elseif (! $hasDevicesOut && $this->status === WorkOrderStatus::AwaitingExternal) {
            $this->update(['status' => WorkOrderStatus::InProgress]);
        }
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<Device, $this>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class);
    }

    /**
     * @return HasMany<ExternalRepair, $this>
     */
    public function externalRepairs(): HasMany
    {
        return $this->hasMany(ExternalRepair::class);
    }
}
