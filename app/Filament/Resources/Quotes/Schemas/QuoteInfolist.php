<?php

namespace App\Filament\Resources\Quotes\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                                TableColumn::make('Descripción'),
                                TableColumn::make('Cantidad'),
                                TableColumn::make('Precio unitario'),
                                TableColumn::make('Importe'),
                            ])
                            ->schema([
                                TextEntry::make('description'),
                                TextEntry::make('quantity')
                                    ->formatStateUsing(fn ($state, $record) => trim(rtrim(rtrim((string) $state, '0'), '.').' '.$record->unit)),
                                TextEntry::make('unit_price')
                                    ->money('ARS', locale: 'es_AR'),
                                TextEntry::make('subtotal')
                                    ->money('ARS', locale: 'es_AR'),
                            ]),
                    ]),
                Grid::make(2)
                    ->schema([
                        Section::make()
                            ->schema([
                                TextEntry::make('notes')
                                    ->label('Observaciones')
                                    ->placeholder('—'),
                                TextEntry::make('terms')
                                    ->label('Condiciones')
                                    ->placeholder('—'),
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
                            ]),
                    ]),
            ]);
    }
}
