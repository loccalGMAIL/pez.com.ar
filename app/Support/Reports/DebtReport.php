<?php

namespace App\Support\Reports;

use App\Models\Charge;
use Illuminate\Support\Collection;

/**
 * What clients owe today, grouped by how long it has been overdue.
 */
class DebtReport extends Report
{
    /**
     * Upper bound of days overdue of each bucket => its label.
     */
    public const BUCKETS = [
        'current' => 'A vencer',
        'd30' => '1 a 30 días',
        'd60' => '31 a 60 días',
        'd90' => '61 a 90 días',
        'older' => 'Más de 90 días',
    ];

    public static function title(): string
    {
        return 'Deuda por antigüedad';
    }

    public function description(): string
    {
        return 'Saldos pendientes de hoy, según los días que pasaron desde el vencimiento de cada cargo.';
    }

    public static function usesPeriod(): bool
    {
        return false;
    }

    public static function bucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => 'd30',
            $daysOverdue <= 60 => 'd60',
            $daysOverdue <= 90 => 'd90',
            default => 'older',
        };
    }

    /**
     * @return Collection<int, array{charge: Charge, owed: float, days: int, bucket: string}>
     */
    private function charges(): Collection
    {
        return once(fn () => $this->forClient(Charge::query())
            ->open()
            ->with('client')
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(function (Charge $charge) {
                $days = (int) $charge->due_on->startOfDay()->diffInDays(today(), false);

                return [
                    'charge' => $charge,
                    'owed' => $charge->outstanding(),
                    'days' => max(0, $days),
                    'bucket' => self::bucket($days),
                ];
            })
            ->filter(fn (array $row) => $row['owed'] > 0)
            ->values());
    }

    public function metrics(): array
    {
        $total = round($this->charges()->sum('owed'), 2);
        $overdue = round($this->charges()->where('bucket', '!=', 'current')->sum('owed'), 2);

        return [
            new Metric('Por cobrar', $total),
            new Metric('Vencido', $overdue, Format::Money, self::share($overdue, $total) !== null ? Format::Percent->render(self::share($overdue, $total)).' de lo que se debe' : null),
            new Metric('Más de 90 días', round($this->charges()->where('bucket', 'older')->sum('owed'), 2)),
            new Metric('Clientes con deuda', $this->charges()->pluck('charge.client_id')->unique()->count(), Format::Number),
        ];
    }

    public function tables(): array
    {
        $empty = array_fill_keys(array_keys(self::BUCKETS), 0.0);

        $byClient = $this->charges()
            ->groupBy(fn (array $row) => $row['charge']->client_id)
            ->map(function (Collection $rows) use ($empty) {
                $buckets = $rows->groupBy('bucket')->map(fn (Collection $rows) => round($rows->sum('owed'), 2))->all();

                return ['client' => $rows->first()['charge']->client->business_name]
                    + array_replace($empty, $buckets)
                    + ['total' => round($rows->sum('owed'), 2)];
            })
            ->sortByDesc('total')
            ->values();

        $totals = ['client' => 'Total']
            + array_map(fn (string $bucket) => round($byClient->sum($bucket), 2), array_combine(array_keys(self::BUCKETS), array_keys(self::BUCKETS)))
            + ['total' => round($byClient->sum('total'), 2)];

        $overdue = $this->charges()
            ->where('bucket', '!=', 'current')
            ->map(fn (array $row) => [
                'client' => $row['charge']->client->business_name,
                'concept' => $row['charge']->concept,
                'due' => $row['charge']->due_on,
                'days' => $row['days'],
                'owed' => $row['owed'],
            ])
            ->values();

        return [
            new Table('Por cliente', [
                new Column('client', 'Cliente'),
                ...array_map(fn (string $key, string $label) => new Column($key, $label, Format::Money), array_keys(self::BUCKETS), self::BUCKETS),
                new Column('total', 'Total', Format::Money),
            ], $byClient->all(), $totals, emptyMessage: 'Ningún cliente debe nada.'),
            new Table('Cargos vencidos', [
                new Column('client', 'Cliente'),
                new Column('concept', 'Concepto'),
                new Column('due', 'Vencimiento', Format::Date),
                new Column('days', 'Atraso', Format::Days),
                new Column('owed', 'Saldo', Format::Money),
            ], $overdue->all(), ['client' => 'Total', 'owed' => round($overdue->sum('owed'), 2)], emptyMessage: 'No hay cargos vencidos.'),
        ];
    }
}
