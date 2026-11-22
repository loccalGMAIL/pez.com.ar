<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Concerns\HasSequentialNumber;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
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
        'reception_photos',
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
            'reception_photos' => 'array',
            'attachments' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkOrder $order) {
            $order->opened_on ??= today();
        });

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

        // After-sales message some business days after delivery; reopening the job drops it.
        static::created(function (WorkOrder $order) {
            if ($order->quote_id !== null) {
                // The job is followed up on delivery instead of the quote's sale.
                FollowUp::query()->pending()->where('quote_id', $order->quote_id)->get()->each->cancel();
            }

            if ($order->status === WorkOrderStatus::Delivered) {
                FollowUp::scheduleForWorkOrder($order);
            }
        });

        static::updated(function (WorkOrder $order) {
            if (! $order->wasChanged('status')) {
                return;
            }

            if ($order->status === WorkOrderStatus::Delivered) {
                FollowUp::scheduleForWorkOrder($order);
            } else {
                $order->followUps()->pending()->get()->each->cancel();
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
     * What external services cost the client, as a starting amount to charge.
     */
    public function externalRepairsPrice(): float
    {
        return $this->externalRepairs()->sum('price') / 100;
    }

    /**
     * Link the client can open without logging in; the signature prevents guessing other orders.
     */
    public function publicPdfUrl(): string
    {
        return URL::signedRoute('work-orders.pdf', $this);
    }

    /**
     * @return HasMany<FollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
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
