<?php

namespace App\Filament\Widgets;

use App\Enums\ChargeStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\ExternalRepairs\ExternalRepairResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Charge;
use App\Models\ExternalRepair;
use App\Models\Payment;
use App\Models\WorkOrder;
use App\Support\QuotePdf;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BusinessOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $overdue = Charge::overdue()->get()->sum(fn (Charge $charge) => $charge->outstanding());
        $collected = Payment::whereBetween('paid_on', [today()->startOfMonth(), today()->endOfMonth()])->sum('amount') / 100;
        $costs = Payment::costBetween(today()->startOfMonth(), today()->endOfMonth());
        $drafts = Charge::where('status', ChargeStatus::Draft)->count();
        $costPending = Charge::costPending()->count();

        return [
            Stat::make('Por cobrar', QuotePdf::money(Charge::totalOutstanding()))
                ->description($overdue > 0 ? 'Vencido: '.QuotePdf::money($overdue) : 'Nada vencido')
                ->color($overdue > 0 ? 'danger' : 'success')
                ->url(ChargeResource::getUrl('index')),
            Stat::make('Cobrado este mes', QuotePdf::money($collected))
                ->url(PaymentResource::getUrl('index')),
            Stat::make('Percibido este mes', QuotePdf::money($collected - $costs))
                ->description($costPending > 0 ? "{$costPending} ".($costPending === 1 ? 'venta sin costo cargado' : 'ventas sin costo cargado') : null)
                ->color($costPending > 0 ? 'warning' : 'success'),
            Stat::make('Abonos a confirmar', $drafts)
                ->description($drafts > 0 ? 'Cargos variables en borrador' : 'Ninguno pendiente')
                ->color($drafts > 0 ? 'warning' : 'gray')
                ->url(ChargeResource::getUrl('index')),
            ...($costPending > 0 ? [
                Stat::make('Ventas sin costo', $costPending)
                    ->description('Reventas a las que les falta cargar el costo')
                    ->color('warning')
                    ->url(ChargeResource::getUrl('index', ['filters' => ['cost_pending' => ['isActive' => true]]])),
            ] : []),
            Stat::make('Órdenes de trabajo abiertas', WorkOrder::whereIn('status', WorkOrderStatus::open())->count())
                ->url(WorkOrderResource::getUrl('index')),
            Stat::make('Equipos en service externo', ExternalRepair::out()->count())
                ->url(ExternalRepairResource::getUrl('index')),
        ];
    }
}
