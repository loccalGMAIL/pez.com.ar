<?php

namespace App\Support\Reports;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;

/**
 * What remains of the money collected once the cost of what was resold is
 * taken out. A payment that settles part of a charge carries that part of
 * its cost, the same as "Percibido" on the dashboard.
 */
class MarginReport extends Report
{
    public static function title(): string
    {
        return 'Margen';
    }

    public function description(): string
    {
        return 'Lo cobrado en el período menos el costo de lo revendido, en proporción a lo que se pagó de cada cargo.';
    }

    public static function origin(Charge $charge): string
    {
        return match (true) {
            $charge->subscription_id !== null => 'Abonos',
            $charge->quote_id !== null => 'Presupuestos',
            $charge->work_order_id !== null => 'Órdenes de trabajo',
            default => 'Cargos manuales',
        };
    }

    /**
     * Each payment allocation of the period with the cost it carries
     * (null while the resold item has no cost loaded).
     *
     * @return Collection<int, array{allocation: PaymentAllocation, collected: float, cost: float|null, pending: bool}>
     */
    private function allocations(): Collection
    {
        return once(fn () => PaymentAllocation::query()
            ->whereHas('payment', fn ($query) => $this->forClient($query)->whereBetween('paid_on', $this->period->bounds()))
            ->with(['payment', 'charge.client'])
            ->get()
            ->filter(fn (PaymentAllocation $allocation) => $allocation->charge !== null)
            ->sortBy(fn (PaymentAllocation $allocation) => $allocation->payment->paid_on->format('Ymd').str_pad((string) $allocation->id, 10, '0', STR_PAD_LEFT))
            ->map(function (PaymentAllocation $allocation) {
                $charge = $allocation->charge;

                return [
                    'allocation' => $allocation,
                    'collected' => $allocation->amount ?? 0.0,
                    'cost' => $charge->cost !== null && $charge->amount > 0
                        ? round(($allocation->amount ?? 0.0) * $charge->cost / $charge->amount, 2)
                        : null,
                    'pending' => $charge->is_resale && $charge->cost === null,
                ];
            })
            ->values());
    }

    private function collected(): float
    {
        return once(fn () => round($this->forClient(Payment::query())->whereBetween('paid_on', $this->period->bounds())->sum('amount') / 100, 2));
    }

    private function cost(): float
    {
        return round($this->allocations()->sum('cost'), 2);
    }

    public function metrics(): array
    {
        $collected = $this->collected();
        $kept = round($collected - $this->cost(), 2);
        $pending = $this->allocations()->where('pending', true)->count();

        return [
            new Metric('Cobrado', $collected),
            new Metric('Costo de lo revendido', $this->cost()),
            new Metric('Percibido', $kept),
            new Metric('Margen', self::share($kept, $collected), Format::Percent, 'Percibido sobre lo cobrado'),
            new Metric('A cuenta', round($collected - $this->allocations()->sum('collected'), 2), Format::Money, 'Pagos que todavía no se aplicaron a ningún cargo'),
            new Metric('Cobros sin costo cargado', $pending, Format::Number, $pending > 0 ? 'El margen real es menor: falta cargar esos costos' : null),
        ];
    }

    public function tables(): array
    {
        $byOrigin = $this->allocations()
            ->groupBy(fn (array $row) => self::origin($row['allocation']->charge))
            ->map(function (Collection $rows, string $origin) {
                $collected = round($rows->sum('collected'), 2);
                $cost = round($rows->sum('cost'), 2);

                return [
                    'origin' => $origin,
                    'collected' => $collected,
                    'cost' => $cost,
                    'kept' => round($collected - $cost, 2),
                    'margin' => self::share($collected - $cost, $collected),
                ];
            })
            ->sortByDesc('collected')
            ->values()
            ->all();

        $allocated = round($this->allocations()->sum('collected'), 2);

        $resales = $this->allocations()
            ->filter(fn (array $row) => $row['cost'] !== null || $row['pending'])
            ->map(fn (array $row) => [
                'date' => $row['allocation']->payment->paid_on,
                'client' => $row['allocation']->charge->client->business_name,
                'concept' => $row['allocation']->charge->concept,
                'collected' => $row['collected'],
                'cost' => $row['cost'],
                'kept' => $row['cost'] === null ? null : round($row['collected'] - $row['cost'], 2),
            ])
            ->values();

        return [
            new Table('Por origen', [
                new Column('origin', 'Origen'),
                new Column('collected', 'Cobrado', Format::Money),
                new Column('cost', 'Costo', Format::Money),
                new Column('kept', 'Percibido', Format::Money),
                new Column('margin', 'Margen', Format::Percent),
            ], $byOrigin, [
                'origin' => 'Total imputado',
                'collected' => $allocated,
                'cost' => $this->cost(),
                'kept' => round($allocated - $this->cost(), 2),
                'margin' => self::share($allocated - $this->cost(), $allocated),
            ], 'Solo lo que ya se aplicó a cargos; lo que quedó a cuenta no tiene origen todavía.'),
            new Table('Ventas con costo', [
                new Column('date', 'Fecha de pago', Format::Date),
                new Column('client', 'Cliente'),
                new Column('concept', 'Concepto'),
                new Column('collected', 'Cobrado', Format::Money),
                new Column('cost', 'Costo', Format::Money),
                new Column('kept', 'Percibido', Format::Money),
            ], $resales->all(), [
                'date' => 'Total',
                'collected' => round($resales->sum('collected'), 2),
                'cost' => round($resales->sum('cost'), 2),
                'kept' => round($resales->sum('kept'), 2),
            ], 'Reventas y cargos con costo cargado. "—" en el costo: reventa con costo pendiente.', 'No se cobraron reventas en el período.'),
        ];
    }
}
