<?php

namespace App\Filament\Resources\Charges\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Charge;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChargeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('client.business_name')
                            ->label('Cliente')
                            ->url(fn (Charge $record) => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('concept')
                            ->label('Concepto')
                            ->columnSpan(2),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge(),
                        TextEntry::make('issued_on')
                            ->label('Fecha')
                            ->date('d/m/Y'),
                        TextEntry::make('due_on')
                            ->label('Vence')
                            ->date('d/m/Y')
                            ->color(fn (Charge $record) => $record->isOverdue() ? 'danger' : null),
                        TextEntry::make('amount')
                            ->label('Monto')
                            ->money('ARS', locale: 'es_AR'),
                        TextEntry::make('paid')
                            ->label('Pagado')
                            ->money('ARS', locale: 'es_AR'),
                        TextEntry::make('origin')
                            ->label('Origen')
                            ->state(fn (Charge $record) => match (true) {
                                $record->quote_id !== null => "Presupuesto {$record->quote?->code}",
                                $record->work_order_id !== null => "Orden de trabajo {$record->workOrder?->code}",
                                $record->subscription_id !== null => "Abono {$record->subscription?->concept}",
                                default => 'Manual',
                            })
                            ->url(fn (Charge $record) => match (true) {
                                $record->quote_id !== null => QuoteResource::getUrl('view', ['record' => $record->quote_id]),
                                $record->work_order_id !== null => WorkOrderResource::getUrl('view', ['record' => $record->work_order_id]),
                                default => null,
                            }),
                        TextEntry::make('supplier.name')
                            ->label('Comprado a')
                            ->visible(fn (Charge $record) => $record->is_resale)
                            ->placeholder('—'),
                        TextEntry::make('cost')
                            ->label('Costo')
                            ->visible(fn (Charge $record) => $record->is_resale)
                            ->money('ARS', locale: 'es_AR')
                            ->placeholder('Pendiente'),
                        TextEntry::make('margin')
                            ->label('Margen')
                            ->visible(fn (Charge $record) => $record->is_resale)
                            ->state(fn (Charge $record) => $record->margin())
                            ->money('ARS', locale: 'es_AR')
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label('Detalle')
                            ->placeholder('—')
                            ->extraAttributes(['class' => 'whitespace-pre-line'])
                            ->columnSpan(3),
                    ]),
                Section::make('Pagos imputados')
                    ->schema([
                        RepeatableEntry::make('allocations')
                            ->hiddenLabel()
                            ->placeholder('Sin pagos')
                            ->table([
                                TableColumn::make('Fecha'),
                                TableColumn::make('Medio'),
                                TableColumn::make('Imputado'),
                            ])
                            ->schema([
                                TextEntry::make('payment.paid_on')
                                    ->date('d/m/Y')
                                    ->url(fn ($record) => PaymentResource::getUrl('view', ['record' => $record->payment_id])),
                                TextEntry::make('payment.method'),
                                TextEntry::make('amount')
                                    ->money('ARS', locale: 'es_AR'),
                            ]),
                    ]),
            ]);
    }
}
