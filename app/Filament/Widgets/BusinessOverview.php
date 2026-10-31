<?php

namespace App\Filament\Widgets;

use App\Enums\ChargeStatus;
use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\ExternalRepairs\ExternalRepairResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Charge;
use App\Models\ExternalRepair;
use App\Models\Payment;
use App\Models\Quote;
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
        $drafts = Charge::where('status', ChargeStatus::Draft)->count();
        $pendingQuotes = Quote::where('status', QuoteStatus::Sent);
        $costPending = Charge::costPending()->count();

        return [
            Stat::make('Por cobrar', QuotePdf::money(Charge::totalOutstanding()))
                ->description($overdue > 0 ? 'Vencido: '.QuotePdf::money($overdue) : 'Nada vencido')
                ->color($overdue > 0 ? 'danger' : 'success')
                ->url(ChargeResource::getUrl('index')),
            Stat::make('Cobrado este mes', QuotePdf::money($collected))
                ->url(PaymentResource::getUrl('index')),
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
            Stat::make('Presupuestos sin respuesta', $pendingQuotes->count())
                ->description(QuotePdf::money($pendingQuotes->sum('total') / 100).' en juego')
                ->url(QuoteResource::getUrl('index')),
            Stat::make('Órdenes de trabajo abiertas', WorkOrder::whereIn('status', WorkOrderStatus::open())->count())
                ->url(WorkOrderResource::getUrl('index')),
            Stat::make('Equipos en service externo', ExternalRepair::out()->count())
                ->url(ExternalRepairResource::getUrl('index')),
        ];
    }
}
