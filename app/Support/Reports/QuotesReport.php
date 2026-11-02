<?php

namespace App\Support\Reports;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Support\Collection;

/**
 * Quotes issued in the period and how many of them were accepted.
 */
class QuotesReport extends Report
{
    public static function title(): string
    {
        return 'Presupuestos';
    }

    public function description(): string
    {
        return 'Presupuestos emitidos en el período (sin borradores), por fecha del presupuesto.';
    }

    /**
     * @return Collection<int, Quote>
     */
    private function quotes(): Collection
    {
        return once(fn () => $this->forClient(Quote::query())
            ->with('client')
            ->where('status', '!=', QuoteStatus::Draft)
            ->whereBetween('issue_date', $this->period->bounds())
            ->orderBy('issue_date')
            ->orderBy('number')
            ->get());
    }

    /**
     * @return Collection<int, Quote>
     */
    private function withStatus(QuoteStatus ...$statuses): Collection
    {
        return $this->quotes()->filter(fn (Quote $quote) => in_array($quote->status, $statuses, true));
    }

    public function metrics(): array
    {
        $accepted = $this->withStatus(QuoteStatus::Accepted);
        $answered = $this->withStatus(QuoteStatus::Accepted, QuoteStatus::Rejected, QuoteStatus::Expired);

        return [
            new Metric('Presupuestos emitidos', $this->quotes()->count(), Format::Number),
            new Metric('Aceptados', $accepted->count(), Format::Number),
            new Metric('Tasa de aceptación', self::share($accepted->count(), $answered->count()), Format::Percent, 'Sobre los que ya tienen respuesta o vencieron'),
            new Metric('Monto presupuestado', round($this->quotes()->sum('total'), 2)),
            new Metric('Monto aceptado', round($accepted->sum('total'), 2)),
            new Metric('Esperando respuesta', $this->withStatus(QuoteStatus::Sent)->count(), Format::Number),
        ];
    }

    public function tables(): array
    {
        $total = round($this->quotes()->sum('total'), 2);

        $byStatus = collect(QuoteStatus::cases())
            ->reject(fn (QuoteStatus $status) => $status === QuoteStatus::Draft)
            ->map(fn (QuoteStatus $status) => [
                'status' => $status->getLabel(),
                'count' => $this->withStatus($status)->count(),
                'amount' => round($this->withStatus($status)->sum('total'), 2),
                'share' => self::share($this->withStatus($status)->sum('total'), $total),
            ])
            ->values()
            ->all();

        return [
            new Table('Por estado', [
                new Column('status', 'Estado'),
                new Column('count', 'Cantidad', Format::Number),
                new Column('amount', 'Monto', Format::Money),
                new Column('share', '% del monto', Format::Percent),
            ], $byStatus, ['status' => 'Total', 'count' => $this->quotes()->count(), 'amount' => $total, 'share' => $total > 0 ? 100.0 : null]),
            new Table('Detalle', [
                new Column('code', 'Número'),
                new Column('date', 'Fecha', Format::Date),
                new Column('client', 'Cliente'),
                new Column('status', 'Estado'),
                new Column('total', 'Total', Format::Money),
            ], $this->quotes()->map(fn (Quote $quote) => [
                'code' => $quote->code,
                'date' => $quote->issue_date,
                'client' => $quote->client->business_name,
                'status' => $quote->status->getLabel(),
                'total' => $quote->total,
            ])->values()->all(), ['code' => 'Total', 'total' => $total], emptyMessage: 'No se emitieron presupuestos en el período.'),
        ];
    }
}
