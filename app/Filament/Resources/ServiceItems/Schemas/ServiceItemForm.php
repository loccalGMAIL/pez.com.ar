<?php

namespace App\Filament\Resources\ServiceItems\Schemas;

use App\Enums\ServiceUnit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServiceItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Descripción')
                    ->helperText('Se copia como detalle al agregarlo a un presupuesto.')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('unit')
                    ->label('Unidad')
                    ->options(ServiceUnit::class)
                    ->default(ServiceUnit::Hour)
                    ->required(),
                TextInput::make('price')
                    ->label('Precio base')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('$')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->helperText('Los inactivos no se ofrecen al armar presupuestos.')
                    ->default(true),
            ]);
    }
}
