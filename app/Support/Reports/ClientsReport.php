<?php

namespace App\Support\Reports;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * Which clients brought in the most money in the period.
 */
class ClientsReport extends Report
{
    public static function title(): string
    {
        return 'Clientes';
    }

    public function description(): string
    {
        return 'Lo que pagó cada cliente en el período (por fecha de pago), lo que se le facturó y lo que debe hoy.';
    }

    /**
     * @return Collection<int, array{client: string, billed: float, count: int, collected: float, share: float|null, owed: float}>
     */
    private function rows(): Collection
    {
        return once(function () {
            $bounds = $this->period->bounds();

            $collected = $this->forClient(Payment::query())
                ->whereBetween('paid_on', $bounds)
                ->groupBy('client_id')
                ->selectRaw('client_id, SUM(amount) as cents, COUNT(*) as payments')
                ->get()
                ->keyBy('client_id');

            $billed = $this->forClient(Charge::query())
                ->whereBetween('issued_on', $bounds)
                ->whereNotIn('status', [ChargeStatus::Draft, ChargeStatus::Cancelled])
                ->groupBy('client_id')
                ->selectRaw('client_id, SUM(amount) as cents')
                ->pluck('cents', 'client_id');

            $owed = $this->forClient(Charge::query())
                ->open()
                ->groupBy('client_id')
                ->selectRaw('client_id, SUM(amount - paid) as cents')
                ->pluck('cents', 'client_id');

            $ids = $collected->keys()->merge($billed->keys())->unique();
            $total = $collected->sum('cents') / 100;

            return Client::withTrashed()
                ->whereKey($ids)
                ->get()
                ->map(function (Client $client) use ($collected, $billed, $owed, $total) {
                    $paid = round(($collected->get($client->id)->cents ?? 0) / 100, 2);

                    return [
                        'client' => $client->business_name,
                        'billed' => round(($billed->get($client->id) ?? 0) / 100, 2),
                        'count' => (int) ($collected->get($client->id)->payments ?? 0),
                        'collected' => $paid,
                        'share' => self::share($paid, $total),
                        'owed' => round(($owed->get($client->id) ?? 0) / 100, 2),
                    ];
                })
                ->sortBy([['collected', 'desc'], ['billed', 'desc'], ['client', 'asc']])
                ->values();
        });
    }

    public function metrics(): array
    {
        $paying = $this->rows()->where('collected', '>', 0);
        $total = round($this->rows()->sum('collected'), 2);
        $top = $paying->first();

        return [
            new Metric('Cobrado', $total),
            new Metric('Clientes que pagaron', $paying->count(), Format::Number),
            new Metric('Promedio por cliente', $paying->isNotEmpty() ? round($total / $paying->count(), 2) : null),
            new Metric('Principal cliente', $top['client'] ?? null, Format::Text, $top ? Format::Percent->render($top['share']).' de lo cobrado' : null),
        ];
    }

    public function tables(): array
    {
        return [
            new Table('Ranking', [
                new Column('client', 'Cliente'),
                new Column('billed', 'Facturado', Format::Money),
                new Column('count', 'Pagos', Format::Number),
                new Column('collected', 'Cobrado', Format::Money),
                new Column('share', '% de lo cobrado', Format::Percent),
                new Column('owed', 'Debe hoy', Format::Money),
            ], $this->rows()->all(), [
                'client' => 'Total',
                'billed' => round($this->rows()->sum('billed'), 2),
                'count' => $this->rows()->sum('count'),
                'collected' => round($this->rows()->sum('collected'), 2),
                'share' => $this->rows()->sum('collected') > 0 ? 100.0 : null,
                'owed' => round($this->rows()->sum('owed'), 2),
            ], 'Facturado: cargos emitidos en el período (sin borradores ni anulados).'),
        ];
    }
}
