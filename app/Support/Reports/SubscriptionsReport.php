<?php

namespace App\Support\Reports;

use App\Enums\ChargeStatus;
use App\Enums\SubscriptionType;
use App\Models\Charge;
use App\Models\PaymentAllocation;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Monthly subscriptions: what they bring in, what they owe and which ones
 * started or ended in the period.
 */
class SubscriptionsReport extends Report
{
    public static function title(): string
    {
        return 'Abonos';
    }

    public function description(): string
    {
        return 'Abonos vigentes en el período, lo que se cobró de ellos (por fecha de pago) y sus altas y bajas.';
    }

    /**
     * Active subscriptions whose dates overlap the period.
     *
     * @return Collection<int, Subscription>
     */
    private function current(): Collection
    {
        return once(fn () => $this->forClient(Subscription::query())
            ->with('client')
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $this->period->to)
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $this->period->from))
            ->get()
            ->sortBy(fn (Subscription $subscription) => mb_strtolower($subscription->client->business_name.' '.$subscription->concept))
            ->values());
    }

    /**
     * Collected per subscription in the period, in pesos.
     *
     * @return Collection<int, float>
     */
    private function collected(): Collection
    {
        return once(fn () => PaymentAllocation::query()
            ->whereHas('payment', fn ($query) => $this->forClient($query)->whereBetween('paid_on', $this->period->bounds()))
            ->join('charges', 'charges.id', '=', 'payment_allocations.charge_id')
            ->whereNotNull('charges.subscription_id')
            ->groupBy('charges.subscription_id')
            ->selectRaw('charges.subscription_id as subscription_id, SUM(payment_allocations.amount) as cents')
            ->pluck('cents', 'subscription_id')
            ->map(fn ($cents) => round($cents / 100, 2)));
    }

    /**
     * Still owed per subscription today, in pesos.
     *
     * @return Collection<int, float>
     */
    private function owed(): Collection
    {
        return once(fn () => $this->forClient(Charge::query())
            ->open()
            ->whereNotNull('subscription_id')
            ->groupBy('subscription_id')
            ->selectRaw('subscription_id, SUM(amount - paid) as cents')
            ->pluck('cents', 'subscription_id')
            ->map(fn ($cents) => round($cents / 100, 2)));
    }

    public function metrics(): array
    {
        $drafts = $this->forClient(Charge::query())->whereNotNull('subscription_id')->where('status', ChargeStatus::Draft)->count();

        return [
            new Metric('Abonos vigentes', $this->current()->count(), Format::Number),
            new Metric('Mensual de abonos fijos', round($this->current()->where('type', SubscriptionType::Fixed)->sum('amount'), 2), Format::Money, 'Lo que suman por mes los abonos de monto fijo'),
            new Metric('Cobrado de abonos', round($this->collected()->sum(), 2)),
            new Metric('Adeudado de abonos', round($this->owed()->sum(), 2)),
            new Metric('Variables a confirmar', $drafts, Format::Number, $drafts > 0 ? 'Cargos en borrador' : null),
        ];
    }

    public function tables(): array
    {
        $rows = $this->current()->map(fn (Subscription $subscription) => [
            'client' => $subscription->client->business_name,
            'concept' => $subscription->concept,
            'type' => $subscription->type->getLabel(),
            'amount' => $subscription->amount,
            'collected' => $this->collected()->get($subscription->id, 0.0),
            'owed' => $this->owed()->get($subscription->id, 0.0),
        ]);

        $movements = $this->forClient(Subscription::query())
            ->with('client')
            ->where(fn (Builder $query) => $query
                ->whereBetween('starts_on', $this->period->bounds())
                ->orWhereBetween('ends_on', $this->period->bounds()))
            ->get()
            ->flatMap(function (Subscription $subscription) {
                $row = [
                    'client' => $subscription->client->business_name,
                    'concept' => $subscription->concept,
                    'amount' => $subscription->amount,
                ];

                return array_values(array_filter([
                    $this->period->contains($subscription->starts_on) ? ['date' => $subscription->starts_on, 'movement' => 'Alta'] + $row : null,
                    $this->period->contains($subscription->ends_on) ? ['date' => $subscription->ends_on, 'movement' => 'Baja'] + $row : null,
                ]));
            })
            ->sortBy(fn (array $row) => $row['date']->format('Ymd').$row['movement'])
            ->values();

        return [
            new Table('Abonos vigentes', [
                new Column('client', 'Cliente'),
                new Column('concept', 'Concepto'),
                new Column('type', 'Tipo'),
                new Column('amount', 'Monto base', Format::Money),
                new Column('collected', 'Cobrado', Format::Money),
                new Column('owed', 'Debe hoy', Format::Money),
            ], $rows->all(), [
                'client' => 'Total',
                'amount' => round($rows->sum('amount'), 2),
                'collected' => round($rows->sum('collected'), 2),
                'owed' => round($rows->sum('owed'), 2),
            ], emptyMessage: 'No hay abonos vigentes en el período.'),
            new Table('Altas y bajas', [
                new Column('date', 'Fecha', Format::Date),
                new Column('movement', 'Movimiento'),
                new Column('client', 'Cliente'),
                new Column('concept', 'Concepto'),
                new Column('amount', 'Monto base', Format::Money),
            ], $movements->all(), emptyMessage: 'No empezó ni terminó ningún abono en el período.'),
        ];
    }
}
