<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos fiscales')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('business_name')
                            ->label('Razón social'),
                        TextEntry::make('trade_name')
                            ->label('Nombre de fantasía')
                            ->placeholder('—'),
                        TextEntry::make('cuit')
                            ->label('CUIT')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('tax_condition')
                            ->label('Condición frente al IVA')
                            ->placeholder('—'),
                    ]),
                Section::make('Contacto')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('phone')
                            ->label('Teléfono')
                            ->placeholder('—'),
                        TextEntry::make('address')
                            ->label('Dirección')
                            ->placeholder('—'),
                        TextEntry::make('city')
                            ->label('Localidad')
                            ->placeholder('—'),
                        TextEntry::make('province')
                            ->label('Provincia')
                            ->placeholder('—'),
                    ]),
                Section::make('Otros')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        IconEntry::make('is_active')
                            ->label('Activo')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Alta')
                            ->dateTime('d/m/Y'),
                    ]),
            ]);
    }
}
