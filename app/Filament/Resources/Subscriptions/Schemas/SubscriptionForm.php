<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Enums\SubscriptionType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'business_name', fn ($query) => $query->where('is_active', true))
                    ->searchable(['business_name', 'trade_name'])
                    ->preload()
                    ->default(fn () => request()->query('client'))
                    ->required()
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager)
                    ->columnSpanFull(),
                TextInput::make('concept')
                    ->label('Concepto')
                    ->placeholder('Mantenimiento mensual, soporte remoto…')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('type')
                    ->label('Tipo')
                    ->options(SubscriptionType::class)
                    ->default(SubscriptionType::Fixed)
                    ->required()
                    ->live()
                    ->helperText(fn (Get $get) => $get('type') === SubscriptionType::Variable || $get('type') === SubscriptionType::Variable->value
                        ? 'El cargo se genera como borrador con los trabajos del mes anterior, para ajustar el monto y confirmarlo.'
                        : 'El cargo se genera con este monto y queda pendiente de cobro.'),
                TextInput::make('amount')
                    ->label(fn (Get $get) => in_array($get('type'), [SubscriptionType::Variable, SubscriptionType::Variable->value], true) ? 'Monto base' : 'Monto mensual')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->required(),
                TextInput::make('billing_day')
                    ->label('Día de facturación')
                    ->helperText('Día del mes en que se genera el cargo (1 a 28).')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(28)
                    ->default(1)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->inline(false),
                DatePicker::make('starts_on')
                    ->label('Desde')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today()->startOfMonth())
                    ->required(),
                DatePicker::make('ends_on')
                    ->label('Hasta')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('starts_on')
                    ->helperText('Vacío si no tiene fecha de fin.'),
                Textarea::make('notes')
                    ->label('Notas')
                    ->helperText('Qué incluye el abono, horas, condiciones…')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
