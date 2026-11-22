<?php

namespace App\Models;

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Support\Settings;
use App\Support\WhatsApp;
use Carbon\CarbonInterface;
use Database\Factories\FollowUpFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * After-sales message ("¿cómo está funcionando el equipo?") proposed some
 * business days after a job is delivered or something is sold. Nothing is
 * sent automatically: the panel opens WhatsApp with the text written.
 */
class FollowUp extends Model
{
    /** @use HasFactory<FollowUpFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'work_order_id',
        'quote_id',
        'charge_id',
        'kind',
        'occurred_on',
        'due_on',
        'status',
        'sent_at',
        'message',
    ];

    protected $attributes = [
        'status' => 'pendiente',
    ];

    protected function casts(): array
    {
        return [
            'kind' => FollowUpKind::class,
            'status' => FollowUpStatus::class,
            'occurred_on' => 'date',
            'due_on' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public static function isEnabled(): bool
    {
        return (bool) Settings::get('follow_ups.enabled');
    }

    /**
     * Business days to wait, counting Monday to Friday only.
     */
    public static function dueDateFrom(CarbonInterface $date): Carbon
    {
        return Carbon::parse($date)->startOfDay()->addWeekdays(self::businessDays());
    }

    /**
     * Once a job is delivered. Called on every delivery: a job reopened and
     * delivered again gets a new one, unless the client had one recently.
     */
    public static function scheduleForWorkOrder(WorkOrder $order): ?self
    {
        return self::schedule($order->client, [
            'kind' => FollowUpKind::WorkOrder,
            'work_order_id' => $order->id,
        ], $order->delivered_at ?? now());
    }

    /**
     * A sale is a charge from an accepted quote (one per quote, whatever its
     * installments) or a resale charge loaded by hand. Quotes turned into a
     * job are followed up when the job is delivered; subscriptions never.
     */
    public static function scheduleForSale(Charge $charge): ?self
    {
        if ($charge->subscription_id !== null || $charge->work_order_id !== null) {
            return null;
        }

        if ($charge->quote_id !== null) {
            $quote = $charge->quote;

            if ($quote->workOrder()->exists() || $quote->followUps()->where('status', '!=', FollowUpStatus::Cancelled)->exists()) {
                return null;
            }

            return self::schedule($charge->client, ['kind' => FollowUpKind::Sale, 'quote_id' => $quote->id], $charge->issued_on);
        }

        if (! $charge->is_resale) {
            return null;
        }

        return self::schedule($charge->client, ['kind' => FollowUpKind::Sale, 'charge_id' => $charge->id], $charge->issued_on);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function schedule(Client $client, array $attributes, CarbonInterface $occurredOn): ?self
    {
        if (! self::isEnabled() || ! $client->receivesFollowUps()) {
            return null;
        }

        $dueOn = self::dueDateFrom($occurredOn);

        // One message per client at a time: a job and a sale in the same week get a single one.
        if ($client->followUps()->blocking($dueOn)->exists()) {
            return null;
        }

        return $client->followUps()->create([
            ...$attributes,
            'occurred_on' => $occurredOn,
            'due_on' => $dueOn,
        ]);
    }

    /**
     * Pending ones and those sent or skipped within the waiting period before $dueOn.
     *
     * @param  Builder<FollowUp>  $query
     */
    public function scopeBlocking(Builder $query, CarbonInterface $dueOn): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('status', FollowUpStatus::Pending)
            ->orWhere(fn (Builder $query) => $query
                ->whereIn('status', [FollowUpStatus::Sent, FollowUpStatus::Skipped])
                ->whereDate('due_on', '>', Carbon::parse($dueOn)->subWeekdays(self::businessDays()))));
    }

    /**
     * @param  Builder<FollowUp>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', FollowUpStatus::Pending);
    }

    /**
     * To send today. A client who started a subscription or opted out after it
     * was scheduled no longer shows up.
     *
     * @param  Builder<FollowUp>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->pending()
            ->whereDate('due_on', '<=', today())
            ->whereHas('client', fn (Builder $query) => $query->receivingFollowUps());
    }

    /**
     * The configured text for its kind, with the client's name in place of {nombre}.
     */
    public function composeMessage(): string
    {
        return strtr((string) Settings::get($this->kind->messageKey()), [
            '{nombre}' => $this->client->greetingName(),
        ]);
    }

    public function whatsappUrl(): string
    {
        return WhatsApp::url($this->client->whatsappPhone(), $this->message ?? $this->composeMessage());
    }

    /**
     * What it follows up, for the panel.
     */
    public function subject(): string
    {
        return match (true) {
            $this->workOrder !== null => "Trabajo {$this->workOrder->code}: {$this->workOrder->title}",
            $this->quote !== null => trim("Presupuesto {$this->quote->code} {$this->quote->title}"),
            $this->charge !== null => "Venta: {$this->charge->concept}",
            default => $this->kind->getLabel(),
        };
    }

    public function markAsSent(): void
    {
        if ($this->status === FollowUpStatus::Sent) {
            return;
        }

        $this->update([
            'status' => FollowUpStatus::Sent,
            'sent_at' => now(),
            'message' => $this->composeMessage(),
        ]);
    }

    public function skip(): void
    {
        $this->update(['status' => FollowUpStatus::Skipped]);
    }

    public function postpone(CarbonInterface $dueOn): void
    {
        $this->update(['status' => FollowUpStatus::Pending, 'due_on' => $dueOn]);
    }

    public function cancel(): void
    {
        $this->update(['status' => FollowUpStatus::Cancelled]);
    }

    private static function businessDays(): int
    {
        return max(1, (int) Settings::get('follow_ups.business_days'));
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<Charge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
