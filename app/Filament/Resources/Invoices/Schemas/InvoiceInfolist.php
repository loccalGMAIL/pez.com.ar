<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Invoice;
use Closure;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('recipient_name')
                            ->label('Cliente')
                            ->url(fn (Invoice $record) => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (Invoice $record) => $record->isCredited() ? 'Anulada' : $record->status->getLabel())
                            ->color(fn (Invoice $record) => $record->isCredited() ? 'gray' : $record->status->getColor()),
                        TextEntry::make('issued_on')
                            ->label('Fecha')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('total')
                            ->label('Total')
                            ->money('ARS', locale: 'es_AR'),
                        TextEntry::make('cae')
                            ->label('CAE')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('cae_due_on')
                            ->label('Vto. del CAE')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('payment_due_on')
                            ->label('Vto. del pago')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('origin')
                            ->label('Origen')
                            ->state(fn (Invoice $record) => match (true) {
                                $record->creditedInvoice !== null => "Anula la {$record->creditedInvoice->title}",
                                $record->payment !== null => "Recibo {$record->payment->code}",
                                $record->charge !== null => 'Cargo, antes de cobrar',
                                default => null,
                            })
                            ->url(fn (Invoice $record) => match (true) {
                                $record->creditedInvoice !== null => InvoiceResource::getUrl('view', ['record' => $record->credited_invoice_id]),
                                $record->payment_id !== null => PaymentResource::getUrl('view', ['record' => $record->payment_id]),
                                $record->charge_id !== null => ChargeResource::getUrl('view', ['record' => $record->charge_id]),
                                default => null,
                            })
                            ->placeholder('—'),
                        TextEntry::make('creditNote.title')
                            ->label('Anulada con')
                            ->visible(fn (Invoice $record) => $record->creditNote !== null)
                            ->url(fn (Invoice $record) => $record->creditNote ? InvoiceResource::getUrl('view', ['record' => $record->creditNote]) : null),
                        TextEntry::make('messages')
                            ->label('Mensajes de ARCA')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->visible(fn (Invoice $record) => filled($record->messages))
                            ->columnSpanFull(),
                    ]),
                Section::make('Detalle')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Concepto'),
                                TableColumn::make('Cargo'),
                                TableColumn::make('Importe'),
                            ])
                            ->schema([
                                TextEntry::make('description')
                                    ->formatStateUsing(fn ($record, string $state) => $record->detail ? "{$state} ({$record->detail})" : $state),
                                TextEntry::make('charge.concept')
                                    ->placeholder('A cuenta')
                                    ->url(fn ($record) => $record->charge_id ? ChargeResource::getUrl('view', ['record' => $record->charge_id]) : null),
                                TextEntry::make('amount')
                                    ->money('ARS', locale: 'es_AR'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Invoices related to a payment or a charge, linking to each one.
     *
     * @param  Closure(mixed): iterable<Invoice>  $invoices
     */
    public static function related(string $name, Closure $invoices): RepeatableEntry
    {
        return RepeatableEntry::make($name)
            ->hiddenLabel()
            ->state($invoices)
            ->table([
                TableColumn::make('Comprobante'),
                TableColumn::make('Estado'),
                TableColumn::make('Fecha'),
                TableColumn::make('Mensajes de ARCA'),
            ])
            ->schema([
                TextEntry::make('title')
                    ->url(fn (Invoice $record) => InvoiceResource::getUrl('view', ['record' => $record])),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Invoice $record) => $record->isCredited() ? 'Anulada' : $record->status->getLabel())
                    ->color(fn (Invoice $record) => $record->isCredited() ? 'gray' : $record->status->getColor()),
                TextEntry::make('issued_on')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextEntry::make('messages')
                    ->listWithLineBreaks()
                    ->placeholder('—'),
            ]);
    }
}
