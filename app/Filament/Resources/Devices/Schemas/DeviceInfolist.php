<?php

namespace App\Filament\Resources\Devices\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeviceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('client.business_name')
                            ->label('Cliente')
                            ->url(fn ($record) => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('type')
                            ->label('Tipo'),
                        TextEntry::make('name')
                            ->label('Identificación')
                            ->placeholder('—'),
                        TextEntry::make('brand')
                            ->label('Marca')
                            ->placeholder('—'),
                        TextEntry::make('model')
                            ->label('Modelo')
                            ->placeholder('—'),
                        TextEntry::make('serial_number')
                            ->label('Número de serie')
                            ->placeholder('—')
                            ->copyable(),
                        IconEntry::make('is_active')
                            ->label('En uso')
                            ->boolean(),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('—')
                            ->columnSpan(2),
                        ImageEntry::make('photos')
                            ->label('Fotos')
                            ->disk('local')
                            ->visibility('private')
                            ->imageHeight(120)
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
