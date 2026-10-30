<?php

namespace App\Filament\Resources\Charges\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class ChargeForm
{
    public static function configure(Schema $schema): Schema
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
                    ->disabledOn('edit')
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager)
                    ->columnSpanFull(),
                TextInput::make('concept')
                    ->label('Concepto')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->label('Monto')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->required(),
                DatePicker::make('issued_on')
                    ->label('Fecha')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->required(),
                DatePicker::make('due_on')
                    ->label('Vencimiento')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('issued_on')
                    ->helperText('Vacío: según los días de la configuración.'),
                Textarea::make('notes')
                    ->label('Detalle')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
