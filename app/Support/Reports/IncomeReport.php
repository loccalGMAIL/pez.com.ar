<?php

namespace App\Support\Reports;

use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * What clients paid in the period, by payment method and by month.
 */
class IncomeReport extends Report
{
    public static function title(): string
    {
        return 'Ingresos';
    }

    public function description(): string
    {
        return 'Pagos recibidos en el período, según la fecha de cada pago.';
    }

    /**
     * @return Collection<int, Payment>
     */
    private function payments(): Collection
    {
        return once(fn () => $this->forClient(Payment::query())
            ->with('client')
            ->whereBetween('paid_on', $this->period->bounds())
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get());
    }

    private function total(): float
    {
        return round($this->payments()->sum('amount'), 2);
    }

    public function metrics(): array
    {
        $count = $this->payments()->count();

        return [
            new Metric('Cobrado', $this->total()),
            new Metric('Pagos', $count, Format::Number),
            new Metric('Promedio por pago', $count > 0 ? round($this->total() / $count, 2) : null),
        ];
    }

    public function tables(): array
    {
        $total = $this->total();

        $byMethod = $this->payments()
            ->groupBy(fn (Payment $payment) => $payment->method->value)
            ->map(fn (Collection $payments) => [
                'method' => $payments->first()->method->getLabel(),
                'count' => $payments->count(),
                'amount' => round($payments->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->map(fn (array $row) => $row + ['share' => self::share($row['amount'], $total)])
            ->values()
            ->all();

        $byMonth = $this->payments()->groupBy(fn (Payment $payment) => $payment->paid_on->format('Y-m'));

        $months = collect($this->period->months())
            ->map(fn (string $label, string $month) => [
                'month' => $label,
                'count' => $byMonth->get($month)?->count() ?? 0,
                'amount' => round($byMonth->get($month)?->sum('amount') ?? 0, 2),
            ])
            ->values()
            ->all();

        $totals = ['count' => $this->payments()->count(), 'amount' => $total];

        return [
            new Table('Por medio de pago', [
                new Column('method', 'Medio'),
                new Column('count', 'Pagos', Format::Number),
                new Column('amount', 'Importe', Format::Money),
                new Column('share', '% del total', Format::Percent),
            ], $byMethod, ['method' => 'Total', 'share' => $total > 0 ? 100.0 : null] + $totals),
            new Table('Por mes', [
                new Column('month', 'Mes'),
                new Column('count', 'Pagos', Format::Number),
                new Column('amount', 'Importe', Format::Money),
            ], $months, ['month' => 'Total'] + $totals),
            new Table('Detalle de pagos', [
                new Column('date', 'Fecha', Format::Date),
                new Column('code', 'Recibo'),
                new Column('client', 'Cliente'),
                new Column('method', 'Medio'),
                new Column('reference', 'Referencia'),
                new Column('amount', 'Importe', Format::Money),
            ], $this->payments()->map(fn (Payment $payment) => [
                'date' => $payment->paid_on,
                'code' => $payment->code,
                'client' => $payment->client->business_name,
                'method' => $payment->method->getLabel(),
                'reference' => $payment->reference,
                'amount' => $payment->amount,
            ])->values()->all(), ['date' => 'Total', 'amount' => $total]),
        ];
    }
}
