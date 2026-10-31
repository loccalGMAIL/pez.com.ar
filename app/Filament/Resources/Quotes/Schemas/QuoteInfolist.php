<?php

namespace App\Filament\Resources\Quotes\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use App\Support\QuotePdf;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class QuoteInfolist
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
                            ->url(fn ($record) => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge(),
                        TextEntry::make('issue_date')
                            ->label('Fecha')
                            ->date('d/m/Y'),
                        TextEntry::make('valid_until')
                            ->label('Válido hasta')
                            ->date('d/m/Y'),
                        TextEntry::make('sent_at')
                            ->label('Enviado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('decided_at')
                            ->label('Respuesta')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),
                Section::make('Ítems')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Componente / descripción'),
                                TableColumn::make('Cantidad')->width('11%')->alignEnd(),
                                TableColumn::make('Precio unitario')->width('20%')->alignEnd(),
                                TableColumn::make('Importe')->width('20%')->alignEnd(),
                            ])
                            ->schema([
                                TextEntry::make('description')
                                    ->state(fn ($record) => array_values(array_filter([
                                        $record->label(),
                                        // Internal: never printed, only shown here.
                                        $record->supplier || $record->unit_cost !== null
                                            ? 'Compra: '.collect([$record->supplier?->name, $record->unit_cost !== null ? 'costo '.QuotePdf::money($record->unit_cost) : null])->filter()->join(' · ')
                                            : null,
                                    ])))
                                    ->listWithLineBreaks(),
                                TextEntry::make('quantity')
                                    ->formatStateUsing(fn ($state, $record) => trim(rtrim(rtrim((string) $state, '0'), '.').' '.$record->unit)),
                                TextEntry::make('unit_price')
                                    ->money('ARS', locale: 'es_AR'),
                                TextEntry::make('subtotal')
                                    ->formatStateUsing(fn ($state, $record) => $record->isFree() ? 'SIN CARGO' : Number::currency($state, 'ARS', 'es_AR')),
                            ]),
                    ]),
                Grid::make(2)
                    ->schema([
                        Section::make('Condiciones')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('warranty')
                                    ->label('Garantía')
                                    ->placeholder('—'),
                                TextEntry::make('payment_terms')
                                    ->label('Forma de pago')
                                    ->placeholder('—'),
                                TextEntry::make('delivery_time')
                                    ->label('Plazo de entrega')
                                    ->placeholder('—'),
                                TextEntry::make('notes')
                                    ->label('Observaciones')
                                    ->placeholder('—'),
                                TextEntry::make('terms')
                                    ->label('Nota al pie')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Totales')
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('ARS', locale: 'es_AR'),
                                TextEntry::make('discount')
                                    ->label('Descuento')
                                    ->money('ARS', locale: 'es_AR'),
                                TextEntry::make('total')
                                    ->label('Total (precio final)')
                                    ->money('ARS', locale: 'es_AR')
                                    ->size('lg')
                                    ->weight('bold'),
                                TextEntry::make('cost')
                                    ->label('Costo y margen (interno)')
                                    ->visible(fn ($record) => $record->items->contains(fn ($item) => $item->unit_cost !== null))
                                    ->state(function ($record) {
                                        $cost = $record->items->sum(fn ($item) => $item->costTotal() ?? 0);

                                        return 'Costo '.QuotePdf::money($cost).' · Margen '.QuotePdf::money($record->total - $cost);
                                    }),
                                TextEntry::make('installments')
                                    ->label('Cuotas')
                                    ->state(fn ($record) => $record->installments()
                                        ->map(fn (array $plan) => "{$plan['count']} cuotas de ".QuotePdf::money($plan['amount']).($plan['surcharge'] > 0 ? " (recargo {$plan['surcharge']} %)" : ' (sin recargo)'))
                                        ->all())
                                    ->listWithLineBreaks()
                                    ->placeholder('Sin cuotas'),
                            ]),
                    ]),
            ]);
    }
}
