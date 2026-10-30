<?php

namespace App\Filament\Resources\WorkOrders\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\WorkOrder;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkOrderInfolist
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
                        TextEntry::make('quote.number')
                            ->label('Presupuesto')
                            ->formatStateUsing(fn ($record) => $record->quote?->code)
                            ->url(fn ($record) => $record->quote_id ? QuoteResource::getUrl('view', ['record' => $record->quote_id]) : null)
                            ->placeholder('—'),
                        TextEntry::make('hours')
                            ->label('Horas')
                            ->placeholder('—'),
                        TextEntry::make('opened_on')
                            ->label('Ingreso')
                            ->date('d/m/Y'),
                        TextEntry::make('finished_at')
                            ->label('Terminado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('delivered_at')
                            ->label('Entregado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('devices.name')
                            ->label('Equipos')
                            ->state(fn ($record) => $record->devices->map(fn ($device) => $device->label)->all())
                            ->listWithLineBreaks()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('description')
                            ->label('Pedido / problema')
                            ->placeholder('—'),
                        TextEntry::make('resolution')
                            ->label('Diagnóstico y trabajo realizado')
                            ->placeholder('—'),
                        ImageEntry::make('reception_photos')
                            ->label('Fotos de recepción')
                            ->disk('local')
                            ->visibility('private')
                            ->imageHeight(120)
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('attachments')
                            ->label('Adjuntos')
                            ->state(fn (WorkOrder $record) => collect($record->attachments ?? [])->map(fn (string $path) => basename($path))->all())
                            ->listWithLineBreaks()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
