<?php

namespace App\Support\Reports;

use App\Enums\WorkOrderStatus;
use App\Models\ExternalRepair;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Work orders and external services: how much came in, how long it took
 * and what is still at a supplier's.
 */
class WorkshopReport extends Report
{
    public static function title(): string
    {
        return 'Taller';
    }

    public function description(): string
    {
        return 'Órdenes abiertas y terminadas en el período, horas cargadas y equipos enviados a service externo.';
    }

    /**
     * @return Builder<WorkOrder>
     */
    private function orders(): Builder
    {
        return $this->forClient(WorkOrder::query());
    }

    /**
     * Orders finished (or delivered) in the period.
     *
     * @return Collection<int, WorkOrder>
     */
    private function finished(): Collection
    {
        return once(fn () => $this->orders()->whereBetween('finished_at', $this->period->bounds())->get());
    }

    /**
     * @return Builder<ExternalRepair>
     */
    private function repairs(): Builder
    {
        return ExternalRepair::query()
            ->when($this->client, fn (Builder $query) => $query->whereHas('workOrder', fn (Builder $query) => $query->where('client_id', $this->client->id)));
    }

    /**
     * @return Collection<int, ExternalRepair>
     */
    private function sent(): Collection
    {
        return once(fn () => $this->repairs()->with('supplier')->whereBetween('sent_on', $this->period->bounds())->get());
    }

    /**
     * @param  Collection<int, float>  $days
     */
    private static function averageDays(Collection $days): ?float
    {
        return $days->isEmpty() ? null : round($days->avg(), 1);
    }

    public function metrics(): array
    {
        return [
            new Metric('Órdenes ingresadas', $this->orders()->whereBetween('opened_on', $this->period->bounds())->count(), Format::Number),
            new Metric('Órdenes terminadas', $this->finished()->count(), Format::Number),
            new Metric('Horas cargadas', round((float) $this->finished()->sum('hours'), 1), Format::Decimal, 'De las órdenes terminadas en el período'),
            new Metric('Resolución promedio', self::averageDays(
                $this->finished()->map(fn (WorkOrder $order) => $order->opened_on->diffInDays($order->finished_at->startOfDay())),
            ), Format::Days, 'Desde el ingreso hasta que se terminó'),
            new Metric('Órdenes abiertas hoy', $this->orders()->whereIn('status', WorkOrderStatus::open())->count(), Format::Number),
            new Metric('En service externo hoy', $this->repairs()->out()->count(), Format::Number),
        ];
    }

    public function tables(): array
    {
        $opened = $this->orders()->whereBetween('opened_on', $this->period->bounds())->get()->countBy(fn (WorkOrder $order) => $order->status->value);

        $byStatus = collect(WorkOrderStatus::cases())
            ->map(fn (WorkOrderStatus $status) => ['status' => $status->getLabel(), 'count' => $opened->get($status->value, 0)])
            ->all();

        $bySupplier = $this->sent()
            ->groupBy('supplier_id')
            ->map(function (Collection $repairs) {
                $cost = round($repairs->sum('cost'), 2);
                $price = round($repairs->sum('price'), 2);

                return [
                    'supplier' => $repairs->first()->supplier->name ?? 'Sin proveedor',
                    'count' => $repairs->count(),
                    'days' => self::averageDays($repairs->whereNotNull('returned_on')->map(fn (ExternalRepair $repair) => $repair->sent_on->diffInDays($repair->returned_on))),
                    'cost' => $cost,
                    'price' => $price,
                    'difference' => round($price - $cost, 2),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $out = $this->repairs()
            ->out()
            ->with(['supplier', 'device', 'workOrder.client'])
            ->orderBy('sent_on')
            ->get()
            ->map(fn (ExternalRepair $repair) => [
                'order' => $repair->workOrder->code,
                'client' => $repair->workOrder->client->business_name,
                'device' => $repair->device->label ?? $repair->problem,
                'supplier' => $repair->supplier->name ?? 'Sin proveedor',
                'sent' => $repair->sent_on,
                'days' => (int) $repair->sent_on->diffInDays(today()),
            ])
            ->all();

        return [
            new Table('Órdenes ingresadas por estado', [
                new Column('status', 'Estado actual'),
                new Column('count', 'Órdenes', Format::Number),
            ], $byStatus, ['status' => 'Total', 'count' => $opened->sum()]),
            new Table('Service externo por proveedor', [
                new Column('supplier', 'Proveedor'),
                new Column('count', 'Envíos', Format::Number),
                new Column('days', 'Demora promedio', Format::Days),
                new Column('cost', 'Costo', Format::Money),
                new Column('price', 'Precio al cliente', Format::Money),
                new Column('difference', 'Diferencia', Format::Money),
            ], $bySupplier->all(), [
                'supplier' => 'Total',
                'count' => $bySupplier->sum('count'),
                'cost' => round($bySupplier->sum('cost'), 2),
                'price' => round($bySupplier->sum('price'), 2),
                'difference' => round($bySupplier->sum('difference'), 2),
            ], 'Equipos enviados en el período. La demora cuenta solo los que ya volvieron.', 'No se envió ningún equipo en el período.'),
            new Table('Equipos en service externo hoy', [
                new Column('order', 'Orden'),
                new Column('client', 'Cliente'),
                new Column('device', 'Equipo'),
                new Column('supplier', 'Proveedor'),
                new Column('sent', 'Enviado', Format::Date),
                new Column('days', 'Hace', Format::Days),
            ], $out, emptyMessage: 'No hay equipos en service externo.'),
        ];
    }
}
