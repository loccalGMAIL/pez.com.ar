<?php

namespace App\Filament\Resources\Devices\Schemas;

use App\Enums\DeviceType;
use App\Filament\Forms\PhotosUpload;
use App\Filament\Forms\SerialNumberInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class DeviceForm
{
    /**
     * @param  bool  $withClient  false when the client is implied (inline creation from a work order)
     */
    public static function configure(Schema $schema, bool $withClient = true): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'business_name')
                    ->searchable(['business_name', 'trade_name'])
                    ->preload()
                    ->default(fn () => request()->query('client'))
                    ->required()
                    // Inside the client's page the client is implied.
                    ->hidden(fn ($livewire) => ! $withClient || $livewire instanceof RelationManager)
                    ->columnSpanFull(),
                Select::make('type')
                    ->label('Tipo')
                    ->options(DeviceType::class)
                    ->required(),
                TextInput::make('name')
                    ->label('Identificación')
                    ->placeholder('PC Recepción')
                    ->maxLength(255),
                TextInput::make('brand')
                    ->label('Marca')
                    ->maxLength(255),
                TextInput::make('model')
                    ->label('Modelo')
                    ->maxLength(255),
                SerialNumberInput::make(),
                Toggle::make('is_active')
                    ->label('En uso')
                    ->helperText('Desactivalo cuando el cliente lo da de baja.')
                    ->default(true)
                    ->inline(false),
                Textarea::make('notes')
                    ->label('Notas')
                    ->helperText('Configuración, accesos, ubicación…')
                    ->rows(3)
                    ->columnSpanFull(),
                PhotosUpload::make('photos', 'devices')
                    ->label('Fotos')
                    ->helperText('Opcional: el equipo, la etiqueta con el número de serie…')
                    ->columnSpanFull(),
            ]);
    }
}
